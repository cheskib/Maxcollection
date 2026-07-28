<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkEditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    private function card(array $metadata = []): Item
    {
        $item = Item::create(['user_id' => $this->user->id, 'status' => Item::STATUS_PROCESSED]);
        $item->metadata()->create(array_merge(['category' => 'sports_card', 'confidence' => 90, 'player_name' => 'Someone'], $metadata));

        return $item;
    }

    public function test_bulk_edit_applies_fields_with_history(): void
    {
        $first = $this->card(['sport' => 'Baseball']);
        $second = $this->card(['sport' => 'Baseball']);
        $untouched = $this->card(['sport' => 'Baseball']);

        $this->actingAs($this->user)->post('/items/bulk-edit', [
            'item_ids' => [$first->id, $second->id],
            'fields' => ['sport' => 'Football', 'card_type' => 'Base'],
        ])->assertRedirect();

        $this->assertSame('Football', $first->fresh()->metadata->sport);
        $this->assertSame('Football', $second->fresh()->metadata->sport);
        $this->assertSame('Baseball', $untouched->fresh()->metadata->sport);
        $this->assertNotNull($first->metadataHistory()->firstWhere('field_name', 'sport'));
    }

    public function test_bulk_edit_can_move_items_to_a_collection(): void
    {
        $collection = Collection::create(['user_id' => $this->user->id, 'name' => "Sruli's"]);
        $first = $this->card();
        $second = $this->card();

        $this->actingAs($this->user)->post('/items/bulk-edit', [
            'item_ids' => [$first->id, $second->id],
            'fields' => [],
            'collection_id' => (string) $collection->id,
        ]);

        $this->assertSame($collection->id, $first->fresh()->collection_id);
        $this->assertSame($collection->id, $second->fresh()->collection_id);
    }

    public function test_bulk_edit_clears_review_status(): void
    {
        $item = $this->card();
        $item->update(['status' => Item::STATUS_NEEDS_REVIEW, 'review_reason' => 'low_confidence']);

        $this->actingAs($this->user)->post('/items/bulk-edit', [
            'item_ids' => [$item->id],
            'fields' => ['team' => 'Yankees'],
        ]);

        $this->assertSame(Item::STATUS_PROCESSED, $item->fresh()->status);
        $this->assertSame('Yankees', $item->fresh()->metadata->team);
    }

    public function test_unprocessed_items_are_skipped_for_metadata_edits(): void
    {
        $captured = Item::create(['user_id' => $this->user->id, 'status' => Item::STATUS_CAPTURED]);

        $this->actingAs($this->user)->post('/items/bulk-edit', [
            'item_ids' => [$captured->id],
            'fields' => ['sport' => 'Football'],
        ]);

        // Still waiting for processing, no metadata invented.
        $this->assertSame(Item::STATUS_CAPTURED, $captured->fresh()->status);
        $this->assertNull($captured->fresh()->metadata);
    }
}
