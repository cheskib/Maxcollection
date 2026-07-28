<?php

namespace Tests\Feature;

use App\Models\Barcode;
use App\Models\Batch;
use App\Models\Item;
use App\Models\StorageBox;
use App\Models\StorageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function barcode(string $type, string $code, ?string $label = null): Barcode
    {
        return Barcode::create(['type' => $type, 'code' => $code, 'label' => $label]);
    }

    /** A finalized batch (bag) ready to be boxed. */
    private function finalizedBatch(string $bagCode): Batch
    {
        $barcode = $this->barcode('bag', $bagCode);
        $batch = Batch::create([
            'user_id' => $this->user->id, 'source' => 'bulk',
            'barcode_id' => $barcode->id, 'status' => Batch::STATUS_CLOSED, 'finalized_at' => now(),
        ]);
        $item = Item::create(['user_id' => $this->user->id, 'batch_id' => $batch->id, 'status' => Item::STATUS_PROCESSED]);
        $item->metadata()->create(['category' => 'sports_card']);

        return $batch;
    }

    private function scan(string $code, ?User $as = null)
    {
        // Real scans are seconds apart; step past the double-read guard.
        $this->travel(5)->seconds();

        return $this->actingAs($as ?? $this->user)->post('/storage/scan', ['code' => $code]);
    }

    private function assertScan($response, bool $ok, string $messagePart): void
    {
        $response->assertSessionHas('scan', function ($scan) use ($ok, $messagePart) {
            return $scan['ok'] === $ok && str_contains($scan['message'], $messagePart);
        });
    }

    public function test_full_packing_flow_box_bags_divider_complete(): void
    {
        $this->barcode('box', 'BOX-000001');
        $this->barcode('category', 'CAT-000001', 'Baseball 80s');
        $this->finalizedBatch('BAG-000001');
        $this->finalizedBatch('BAG-000002');

        $this->assertScan($this->scan('BOX-000001'), true, 'opened');
        $this->assertScan($this->scan('BAG-000001'), true, '1 bag(s)');
        $this->assertScan($this->scan('BAG-000002'), true, '2 bag(s)');
        $this->assertScan($this->scan('CAT-000001'), true, 'Baseball 80s');

        $response = $this->actingAs($this->user)->post('/storage/complete', ['confirmed' => false]);
        $this->assertScan($response, true, 'completed');

        $box = StorageBox::first();
        $this->assertSame('closed', $box->status);
        $this->assertSame(2, $box->bag_count);
        // The empty next section opened by the divider scan is discarded.
        $this->assertSame(1, $box->section_count);
        $this->assertSame(2, $box->card_count);

        // Audit trail covers the whole flow.
        $this->assertSame(
            ['box_opened', 'bag_added', 'bag_added', 'divider_scanned', 'box_completed'],
            StorageEvent::orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_scans_are_normalized_and_malformed_codes_rejected(): void
    {
        $this->barcode('box', 'BOX-000001');

        // Scanner noise: whitespace, line endings, lowercase.
        $this->assertScan($this->scan("  bag-000001\r\n"), false, 'not in the registry');
        $this->assertScan($this->scan(' box-000001 '), true, 'opened');
        $this->assertScan($this->scan('HELLO'), false, 'Not a valid barcode');
    }

    public function test_wrong_context_scans_are_rejected(): void
    {
        $this->barcode('box', 'BOX-000001');
        $this->barcode('box', 'BOX-000002');
        $this->barcode('category', 'CAT-000001');
        $this->finalizedBatch('BAG-000001');

        // Bag before any box is open.
        $this->assertScan($this->scan('BAG-000001'), false, 'Scan a BOX barcode first');

        $this->scan('BOX-000001');

        // A second box while one is open.
        $this->assertScan($this->scan('BOX-000002'), false, 'Complete box BOX-000001');

        // Divider before any bags in the section.
        $this->assertScan($this->scan('CAT-000001'), false, 'No bags scanned yet');
    }

    public function test_bag_cannot_be_boxed_twice_and_divider_used_once(): void
    {
        $this->barcode('box', 'BOX-000001');
        $this->barcode('box', 'BOX-000002');
        $this->barcode('category', 'CAT-000001');
        $this->finalizedBatch('BAG-000001');
        $this->finalizedBatch('BAG-000002');

        $this->scan('BOX-000001');
        $this->scan('BAG-000001');
        $this->scan('CAT-000001');
        $this->actingAs($this->user)->post('/storage/complete', ['confirmed' => false]);

        $this->scan('BOX-000002');
        $this->scan('BAG-000002');
        $this->assertScan($this->scan('BAG-000001'), false, 'already in box BOX-000001');
        $this->assertScan($this->scan('CAT-000001'), false, 'already used in box BOX-000001');
    }

    public function test_unassigned_bag_and_closed_box_are_rejected(): void
    {
        $this->barcode('box', 'BOX-000001');
        $this->barcode('bag', 'BAG-000009'); // registered, never assigned
        $this->finalizedBatch('BAG-000001');

        $this->scan('BOX-000001');
        $this->assertScan($this->scan('BAG-000009'), false, 'has not been assigned to a batch');

        $this->scan('BAG-000001');
        $this->barcode('category', 'CAT-000001');
        $this->scan('CAT-000001');
        $this->actingAs($this->user)->post('/storage/complete', ['confirmed' => false]);

        // Re-scanning a sealed box is an error.
        $this->assertScan($this->scan('BOX-000001'), false, 'sealed');
    }

    public function test_duplicate_read_within_seconds_is_ignored(): void
    {
        $this->barcode('box', 'BOX-000001');
        $this->finalizedBatch('BAG-000001');
        $this->scan('BOX-000001');
        $this->scan('BAG-000001');

        // Immediate second read (scanner bounce) — no travel between scans.
        $response = $this->actingAs($this->user)->post('/storage/scan', ['code' => 'BAG-000001']);
        $this->assertScan($response, true, 'Duplicate read');
        $this->assertSame(1, StorageEvent::where('action', 'bag_added')->count());
    }

    public function test_one_open_box_per_user_not_globally(): void
    {
        $other = User::factory()->create();
        $this->barcode('box', 'BOX-000001');
        $this->barcode('box', 'BOX-000002');

        $this->assertScan($this->scan('BOX-000001'), true, 'opened');
        // A second admin packs their own box at the same time.
        $this->assertScan($this->scan('BOX-000002', $other), true, 'opened');
        // But cannot touch a box another user is packing.
        $this->assertScan($this->scan('BOX-000001', $other), false, 'another user');
    }

    public function test_undo_removes_last_bag_divider_or_empty_box(): void
    {
        $this->barcode('box', 'BOX-000001');
        $this->barcode('category', 'CAT-000001');
        $batch = $this->finalizedBatch('BAG-000001');

        $this->scan('BOX-000001');
        $this->scan('BAG-000001');

        // Undo the bag.
        $response = $this->actingAs($this->user)->post('/storage/undo');
        $this->assertScan($response, true, 'bag BAG-000001 removed');
        $this->assertNull($batch->fresh()->storage_section_id);

        // Re-add, close the section, then undo the divider.
        $this->scan('BAG-000001');
        $this->scan('CAT-000001');
        $response = $this->actingAs($this->user)->post('/storage/undo');
        $this->assertScan($response, true, 'divider CAT-000001 removed');
        $box = StorageBox::first();
        $this->assertSame(1, $box->sections()->count());
        $this->assertNull($box->sections()->first()->category_barcode_id);

        // Empty the box, then undo the box open itself.
        $this->actingAs($this->user)->post('/storage/undo'); // removes the bag again
        $response = $this->actingAs($this->user)->post('/storage/undo');
        $this->assertScan($response, true, 'box BOX-000001 removed');
        $this->assertSame(0, StorageBox::count());
    }

    public function test_completing_with_unlabeled_bags_requires_confirmation(): void
    {
        $this->barcode('box', 'BOX-000001');
        $this->finalizedBatch('BAG-000001');

        $this->scan('BOX-000001');
        $this->scan('BAG-000001');

        // No divider scanned: first attempt asks, confirmed attempt closes.
        $response = $this->actingAs($this->user)->post('/storage/complete', ['confirmed' => false]);
        $response->assertSessionHas('scan', fn ($scan) => $scan['tone'] === 'confirm');
        $this->assertSame('open', StorageBox::first()->status);

        $this->actingAs($this->user)->post('/storage/complete', ['confirmed' => true]);
        $box = StorageBox::first();
        $this->assertSame('closed', $box->status);
        // The unlabeled section is preserved, not discarded.
        $this->assertSame(1, $box->section_count);
        $this->assertNull($box->sections()->first()->category_barcode_id);
    }

    public function test_empty_box_cannot_be_completed(): void
    {
        $this->barcode('box', 'BOX-000001');
        $this->scan('BOX-000001');

        $response = $this->actingAs($this->user)->post('/storage/complete', ['confirmed' => false]);
        $this->assertScan($response, false, 'empty');

        // The failed completion must not corrupt the box: its pending
        // section survives and the next bag scan still works.
        $this->assertNotNull(StorageBox::first()->pendingSection());
        $this->finalizedBatch('BAG-000001');
        $this->assertScan($this->scan('BAG-000001'), true, '1 bag(s)');
    }

    public function test_finalizing_then_immediately_packing_the_same_bag_works(): void
    {
        // Finalize on the batch page, then scan the same bag on the
        // packing screen seconds later — the double-read guard must not
        // swallow it (it only guards packing-screen scans).
        $this->barcode('box', 'BOX-000001');
        $bagBarcode = $this->barcode('bag', 'BAG-000001');
        $batch = Batch::create(['user_id' => $this->user->id, 'source' => 'bulk']);
        $item = Item::create(['user_id' => $this->user->id, 'batch_id' => $batch->id, 'status' => Item::STATUS_PROCESSED]);
        $item->metadata()->create(['category' => 'sports_card']);

        $this->scan('BOX-000001');
        $this->actingAs($this->user)->post("/batches/{$batch->id}/bag", ['code' => 'BAG-000001']);

        // No time travel: the packing scan happens within the guard window.
        $response = $this->actingAs($this->user)->post('/storage/scan', ['code' => 'BAG-000001']);
        $this->assertScan($response, true, '1 bag(s)');
        $this->assertNotNull($batch->fresh()->storage_section_id);
    }

    public function test_box_detail_shows_sections_and_bags(): void
    {
        $this->barcode('box', 'BOX-000001');
        $this->barcode('category', 'CAT-000001', 'Baseball 80s');
        $this->finalizedBatch('BAG-000001');

        $this->scan('BOX-000001');
        $this->scan('BAG-000001');
        $this->scan('CAT-000001');
        $this->actingAs($this->user)->post('/storage/complete', ['confirmed' => false]);

        $this->actingAs($this->user)
            ->get('/storage/boxes/'.StorageBox::first()->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('StorageBox')
                ->where('box.code', 'BOX-000001')
                ->where('box.sections.0.category', 'Baseball 80s')
                ->where('box.sections.0.bags.0.code', 'BAG-000001')
            );
    }

    public function test_storage_pages_require_authentication(): void
    {
        $this->get('/storage')->assertRedirect('/login');
        $this->post('/storage/scan', ['code' => 'BOX-000001'])->assertRedirect('/login');
    }
}
