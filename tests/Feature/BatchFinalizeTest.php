<?php

namespace Tests\Feature;

use App\Models\Barcode;
use App\Models\Batch;
use App\Models\Item;
use App\Models\StorageBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchFinalizeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function batchWithItem(string $itemStatus = Item::STATUS_PROCESSED): Batch
    {
        $batch = Batch::create(['user_id' => $this->user->id, 'source' => 'bulk']);
        Item::create(['user_id' => $this->user->id, 'batch_id' => $batch->id, 'status' => $itemStatus]);

        return $batch;
    }

    private function assignBag(Batch $batch, string $code)
    {
        return $this->actingAs($this->user)->post("/batches/{$batch->id}/bag", ['code' => $code]);
    }

    private function assertScan($response, bool $ok, string $messagePart): void
    {
        $response->assertSessionHas('scan', function ($scan) use ($ok, $messagePart) {
            return $scan['ok'] === $ok && str_contains($scan['message'], $messagePart);
        });
    }

    public function test_scanning_a_bag_finalizes_the_batch(): void
    {
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000001']);
        $batch = $this->batchWithItem();

        $this->assertScan($this->assignBag($batch, ' bag-000001 '), true, 'finalized as BAG-000001');

        $batch->refresh();
        $this->assertSame(Batch::STATUS_CLOSED, $batch->status);
        $this->assertNotNull($batch->finalized_at);
        // The bag code is now the batch's permanent identity.
        $this->assertSame('BAG-000001', $batch->displayLabel());
        // Finalized but not yet archived to Dropbox.
        $this->assertNull($batch->archived_at);
    }

    public function test_needs_review_does_not_block_but_unprocessed_items_do(): void
    {
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000001']);
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000002']);

        // Review can happen after bagging.
        $reviewBatch = $this->batchWithItem(Item::STATUS_NEEDS_REVIEW);
        $this->assertScan($this->assignBag($reviewBatch, 'BAG-000001'), true, 'finalized');

        // Unprocessed work blocks.
        $pendingBatch = $this->batchWithItem(Item::STATUS_CAPTURED);
        $this->assertScan($this->assignBag($pendingBatch, 'BAG-000002'), false, 'not processed yet');
    }

    public function test_bag_rules_unknown_wrong_type_taken_and_empty_batch(): void
    {
        Barcode::create(['type' => 'box', 'code' => 'BOX-000001']);
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000001']);

        $batch = $this->batchWithItem();
        $this->assertScan($this->assignBag($batch, 'BAG-999999'), false, 'not in the registry');
        $this->assertScan($this->assignBag($batch, 'BOX-000001'), false, 'not a bag barcode');

        $this->assignBag($batch, 'BAG-000001');
        $other = $this->batchWithItem();
        $this->assertScan($this->assignBag($other, 'BAG-000001'), false, 'already assigned to BAG-000001');

        $empty = Batch::create(['user_id' => $this->user->id, 'source' => 'bulk']);
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000003']);
        $this->assertScan($this->assignBag($empty, 'BAG-000003'), false, 'no items');
    }

    public function test_bag_can_be_changed_until_sealed_in_a_closed_box(): void
    {
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000001']);
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000002']);
        $boxBarcode = Barcode::create(['type' => 'box', 'code' => 'BOX-000001']);

        $batch = $this->batchWithItem();
        $this->assignBag($batch, 'BAG-000001');

        // Reassignment is fine while the bag is loose.
        $this->assertScan($this->assignBag($batch, 'BAG-000002'), true, 'finalized as BAG-000002');

        // Seal it inside a closed box; now the bag identity is locked.
        $box = StorageBox::create(['user_id' => $this->user->id, 'barcode_id' => $boxBarcode->id, 'status' => 'closed', 'closed_at' => now()]);
        $section = $box->sections()->create(['position' => 1]);
        $batch->update(['storage_section_id' => $section->id]);

        $this->assertScan($this->assignBag($batch, 'BAG-000001'), false, 'sealed in box BOX-000001');
    }

    public function test_batch_page_shows_bag_and_location(): void
    {
        $bag = Barcode::create(['type' => 'bag', 'code' => 'BAG-000001']);
        $boxBarcode = Barcode::create(['type' => 'box', 'code' => 'BOX-000001']);
        $cat = Barcode::create(['type' => 'category', 'code' => 'CAT-000001', 'label' => 'Baseball 80s']);

        $batch = $this->batchWithItem();
        $batch->update(['barcode_id' => $bag->id, 'status' => Batch::STATUS_CLOSED, 'finalized_at' => now()]);

        $box = StorageBox::create(['user_id' => $this->user->id, 'barcode_id' => $boxBarcode->id, 'status' => 'closed', 'closed_at' => now()]);
        $section = $box->sections()->create(['position' => 1, 'category_barcode_id' => $cat->id]);
        $batch->update(['storage_section_id' => $section->id]);

        $this->actingAs($this->user)
            ->get("/batches/{$batch->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('batch.bagCode', 'BAG-000001')
                ->where('batch.location.box', 'BOX-000001')
                ->where('batch.location.section', 'Baseball 80s')
                ->where('batch.location.sealed', true)
            );
    }
}
