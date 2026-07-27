<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Collection;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OverviewPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    private function item(string $status, array $metadata = [], ?int $batchId = null): Item
    {
        $item = Item::create(['user_id' => $this->user->id, 'status' => $status, 'batch_id' => $batchId]);

        if ($metadata !== []) {
            $item->metadata()->create(array_merge(['category' => 'sports_card', 'confidence' => 90], $metadata));
        }

        return $item;
    }

    public function test_inventory_summarizes_pipeline_and_uploads(): void
    {
        $batch = Batch::create(['user_id' => $this->user->id, 'source' => 'pdf', 'label' => 'scan.pdf']);
        $this->item(Item::STATUS_PROCESSED, [], $batch->id);
        $this->item(Item::STATUS_CAPTURED, [], $batch->id);
        $this->item(Item::STATUS_CAPTURED);

        $this->actingAs($this->user)
            ->get('/inventory')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Inventory')
                ->where('pipeline.waiting', 2)
                ->where('pipeline.processed', 1)
                ->where('uploads.batches.0.label', 'scan.pdf')
                ->where('uploads.batches.0.itemCount', 2)
                ->where('uploads.batches.0.doneCount', 1)
                ->where('uploads.singleCount', 1)
            );
    }

    public function test_processed_summary_breaks_down_the_collection(): void
    {
        $collection = Collection::create(['name' => "Cheski's", 'user_id' => $this->user->id]);
        $item = $this->item(Item::STATUS_PROCESSED, ['sport' => 'Baseball', 'card_type' => 'All-Star', 'manufacturer' => 'Topps', 'year' => '1987', 'player_name' => 'Don Mattingly']);
        $item->update(['collection_id' => $collection->id]);
        $this->item(Item::STATUS_PROCESSED, ['sport' => 'Football', 'player_name' => 'Dan Marino']);
        // Unprocessed items stay out of every breakdown.
        $this->item(Item::STATUS_CAPTURED, ['sport' => 'Baseball']);

        $this->actingAs($this->user)
            ->get('/items/summary')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ProcessedSummary')
                ->where('total', 2)
                ->has('sports', 2)
                ->where('cardTypes.0.value', 'All-Star')
                ->where('collections.named.0.count', 1)
                ->where('collections.unassigned', 1)
            );
    }

    public function test_photo_summary_flags_single_photo_cards(): void
    {
        $complete = $this->item(Item::STATUS_PROCESSED, ['player_name' => 'Two Sides']);
        foreach (['front', 'back'] as $role) {
            $complete->images()->create([
                'path' => "original/{$complete->id}/{$role}.jpg",
                'original_filename' => "{$role}.jpg",
                'mime_type' => 'image/jpeg',
                'size_bytes' => 1048576,
                'role' => $role,
            ]);
        }

        $lonely = $this->item(Item::STATUS_PROCESSED, ['player_name' => 'One Side']);
        $lonely->images()->create([
            'path' => "original/{$lonely->id}/front.jpg",
            'original_filename' => 'front.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1048576,
            'role' => 'front',
        ]);

        $this->actingAs($this->user)
            ->get('/photos')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('PhotoSummary')
                ->where('totals.photos', 3)
                ->where('bySide.front', 2)
                ->where('bySide.back', 1)
                ->has('singlePhotoItems', 1)
                ->where('singlePhotoItems.0.id', $lonely->id)
                ->where('storageMb', 3)
            );
    }

    public function test_overview_pages_require_authentication(): void
    {
        $this->get('/inventory')->assertRedirect('/login');
        $this->get('/items/summary')->assertRedirect('/login');
        $this->get('/photos')->assertRedirect('/login');
    }
}
