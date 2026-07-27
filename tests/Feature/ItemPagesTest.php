<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ProcessingJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ItemPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    private function processedItem(array $metadata = []): Item
    {
        $item = Item::create([
            'user_id' => $this->user->id,
            'status' => Item::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);

        $item->metadata()->create(array_merge([
            'category' => 'sports_card',
            'confidence' => 90,
            'player_name' => 'Sample Player',
        ], $metadata));

        return $item;
    }

    public function test_processed_items_page_lists_processed_items_only(): void
    {
        $processed = $this->processedItem();
        Item::create(['user_id' => $this->user->id, 'status' => Item::STATUS_NEEDS_REVIEW]);

        $this->actingAs($this->user)
            ->get('/items')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ProcessedItems')
                ->has('items', 1)
                ->where('items.0.id', $processed->id)
                ->where('items.0.category', 'Sports Card')
            );
    }

    public function test_item_detail_shows_metadata_fields_and_processing_info(): void
    {
        $item = $this->processedItem(['year' => '1989', 'manufacturer' => 'Upper Deck']);
        $job = $item->processingJobs()->create(['status' => ProcessingJob::STATUS_COMPLETED, 'finished_at' => now()]);
        $job->logs()->create(['message' => 'Processing completed.']);

        $this->actingAs($this->user)
            ->get("/items/{$item->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ItemDetail')
                ->where('item.title', '1989 Upper Deck Sample Player')
                ->where('item.confidence', 90)
                // Only fields with values are shown (player, year, manufacturer).
                ->has('item.fields', 3)
                ->where('item.processing.status', 'completed')
            );
    }

    public function test_editing_metadata_records_history_and_clears_review(): void
    {
        $item = $this->processedItem();
        $item->update(['status' => Item::STATUS_NEEDS_REVIEW, 'review_reason' => 'low_confidence']);

        $response = $this->actingAs($this->user)->put("/items/{$item->id}/metadata", [
            'category' => 'sports_card',
            'player_name' => 'Corrected Player',
            'year' => '1990',
        ]);

        // Save keeps the user on the edit screen with its confirmation.
        $response->assertRedirect("/items/{$item->id}/edit");

        $item->refresh();
        $this->assertSame(Item::STATUS_PROCESSED, $item->status);
        $this->assertNull($item->review_reason);
        $this->assertSame('Corrected Player', $item->metadata->player_name);
        $this->assertSame('1990', $item->metadata->year);

        $history = $item->metadataHistory()->get();
        $this->assertCount(2, $history);
        $playerChange = $history->firstWhere('field_name', 'player_name');
        $this->assertSame('Sample Player', $playerChange->previous_value);
        $this->assertSame('Corrected Player', $playerChange->new_value);
        $this->assertSame($this->user->id, $playerChange->user_id);
    }

    public function test_unchanged_fields_produce_no_history(): void
    {
        $item = $this->processedItem();

        $this->actingAs($this->user)->put("/items/{$item->id}/metadata", [
            'category' => 'sports_card',
            'player_name' => 'Sample Player',
        ]);

        $this->assertSame(0, $item->metadataHistory()->count());
    }

    public function test_needs_review_page_lists_reasons(): void
    {
        $item = $this->processedItem();
        $item->update(['status' => Item::STATUS_NEEDS_REVIEW, 'review_reason' => 'unsupported_category']);

        $this->actingAs($this->user)
            ->get('/review')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('NeedsReview')
                ->has('items', 1)
                ->where('items.0.reason', 'Unsupported category')
            );
    }

    public function test_search_matches_metadata_fields(): void
    {
        $griffey = $this->processedItem(['player_name' => 'Ken Griffey Jr.']);
        $this->processedItem(['player_name' => 'Someone Else']);

        $this->actingAs($this->user)
            ->get('/items?q=Griffey')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)
                ->where('items.0.id', $griffey->id)
            );
    }

    public function test_search_matches_year_and_country(): void
    {
        $coin = $this->processedItem(['category' => 'coin', 'player_name' => null, 'country' => 'Canada', 'denomination' => 'Dollar']);
        $this->processedItem();

        $this->actingAs($this->user)
            ->get('/items?q=Canada')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)
                ->where('items.0.id', $coin->id)
            );
    }

    public function test_reprocess_queues_a_new_job(): void
    {
        config(['services.openai.key' => null]);
        $item = $this->processedItem();

        $this->actingAs($this->user)->post("/items/{$item->id}/reprocess")
            ->assertRedirect("/items/{$item->id}");

        $this->assertGreaterThanOrEqual(1, $item->processingJobs()->count());
    }

    public function test_processed_items_can_stack_metadata_filters(): void
    {
        $match = $this->processedItem(['sport' => 'Baseball', 'year' => '1987', 'team' => 'Rangers']);
        $this->processedItem(['sport' => 'Baseball', 'year' => '1989']);
        $this->processedItem(['sport' => 'Football', 'year' => '1987']);

        $this->actingAs($this->user)
            ->get('/items?sport=Baseball&year=1987')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)
                ->where('items.0.id', $match->id)
                ->where('filters.sport', 'Baseball')
            );
    }

    public function test_filter_options_come_from_existing_processed_items(): void
    {
        $this->processedItem(['sport' => 'Baseball', 'year' => '1987', 'manufacturer' => 'Topps']);
        $this->processedItem(['sport' => 'Hockey', 'year' => '1990']);

        $this->actingAs($this->user)
            ->get('/items')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filterOptions.sport', ['Baseball', 'Hockey'])
                ->where('filterOptions.year', ['1990', '1987'])
                ->where('filterOptions.manufacturer', ['Topps'])
            );
    }

    public function test_deleting_an_item_removes_everything(): void
    {
        $item = $this->processedItem();
        $item->images()->create([
            'path' => 'original/'.$item->id.'/photo.jpg',
            'original_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10,
        ]);
        Storage::disk('local')->put('original/'.$item->id.'/photo.jpg', 'x');
        $item->metadataHistory()->create(['user_id' => $this->user->id, 'field_name' => 'year', 'new_value' => '1989']);

        $response = $this->actingAs($this->user)->delete("/items/{$item->id}");

        $response->assertRedirect('/items');
        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('images', 0);
        $this->assertDatabaseCount('metadata', 0);
        $this->assertDatabaseCount('metadata_history', 0);
        Storage::disk('local')->assertMissing('original/'.$item->id.'/photo.jpg');
    }

    public function test_item_pages_require_authentication(): void
    {
        $item = $this->processedItem();

        $this->get('/items')->assertRedirect('/login');
        $this->get("/items/{$item->id}")->assertRedirect('/login');
        $this->get('/review')->assertRedirect('/login');
    }
}
