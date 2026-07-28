<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Collection;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    private function item(array $attributes = []): Item
    {
        return Item::create(array_merge(['user_id' => $this->user->id], $attributes));
    }

    public function test_item_collection_can_be_changed_from_the_item_view(): void
    {
        $collection = Collection::create(['user_id' => $this->user->id, 'name' => "Cheski's"]);
        $item = $this->item();

        $this->actingAs($this->user)
            ->put("/items/{$item->id}/collection", ['collection_id' => $collection->id])
            ->assertRedirect();

        $this->assertSame($collection->id, $item->fresh()->collection_id);
    }

    public function test_a_new_collection_can_be_created_from_the_item_view(): void
    {
        $item = $this->item();

        $this->actingAs($this->user)
            ->put("/items/{$item->id}/collection", ['new_collection_name' => "Sruli's"]);

        $this->assertSame("Sruli's", $item->fresh()->collection->name);
    }

    public function test_single_capture_assigns_the_chosen_collection(): void
    {
        $collection = Collection::create(['user_id' => $this->user->id, 'name' => 'Box A']);

        $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('front.jpg'),
            'collection_id' => $collection->id,
        ]);

        $this->assertSame($collection->id, Item::first()->collection_id);
    }

    public function test_bulk_capture_creates_and_reuses_a_named_collection(): void
    {
        $this->actingAs($this->user)->post('/capture/bulk/items', [
            'photos' => [UploadedFile::fake()->image('a.jpg')],
            'new_collection_name' => 'Scan Pile',
        ])->assertCreated()->assertJsonPath('collectionId', fn ($id) => is_int($id));

        $this->actingAs($this->user)->post('/capture/bulk/items', [
            'photos' => [UploadedFile::fake()->image('b.jpg')],
            'new_collection_name' => 'Scan Pile',
        ]);

        $this->assertDatabaseCount('collections', 1);
        $this->assertSame(2, Collection::first()->items()->count());
    }

    public function test_a_whole_batch_can_be_moved_to_a_collection(): void
    {
        $batch = Batch::create(['user_id' => $this->user->id, 'source' => 'bulk']);
        $this->item(['batch_id' => $batch->id]);
        $this->item(['batch_id' => $batch->id]);
        $outside = $this->item();

        $this->actingAs($this->user)
            ->post("/batches/{$batch->id}/collection", ['new_collection_name' => 'Moved'])
            ->assertRedirect();

        $collection = Collection::first();
        $this->assertSame(2, $collection->items()->count());
        $this->assertNull($outside->fresh()->collection_id);
    }

    public function test_collections_page_lists_counts_and_unassigned(): void
    {
        $collection = Collection::create(['user_id' => $this->user->id, 'name' => "Cheski's"]);
        $this->item(['collection_id' => $collection->id]);
        $this->item();

        $this->actingAs($this->user)
            ->get('/collections')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Collections')
                ->has('collections', 1)
                ->where('collections.0.itemCount', 1)
                ->where('unassignedCount', 1)
            );
    }

    public function test_collection_detail_and_unassigned_views_filter_items(): void
    {
        $collection = Collection::create(['user_id' => $this->user->id, 'name' => "Cheski's"]);
        $inside = $this->item(['collection_id' => $collection->id]);
        $loose = $this->item();

        $this->actingAs($this->user)
            ->get("/collections/{$collection->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)->where('items.0.id', $inside->id));

        $this->actingAs($this->user)
            ->get('/collections/unassigned')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)->where('items.0.id', $loose->id));
    }

    public function test_processed_items_can_filter_by_collection(): void
    {
        $collection = Collection::create(['user_id' => $this->user->id, 'name' => "Cheski's"]);
        $inside = $this->item(['collection_id' => $collection->id, 'status' => Item::STATUS_PROCESSED, 'processed_at' => now()]);
        $inside->metadata()->create(['category' => 'sports_card', 'player_name' => 'In']);
        $loose = $this->item(['status' => Item::STATUS_PROCESSED, 'processed_at' => now()]);
        $loose->metadata()->create(['category' => 'sports_card', 'player_name' => 'Out']);

        $this->actingAs($this->user)
            ->get("/items?collection={$collection->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)->where('items.0.id', $inside->id));

        $this->actingAs($this->user)
            ->get('/items?collection=unassigned')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)->where('items.0.id', $loose->id));

        $this->actingAs($this->user)
            ->get('/items')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('items', 2));
    }

    public function test_collections_show_value_totals(): void
    {
        $collection = Collection::create(['user_id' => $this->user->id, 'name' => "Cheski's"]);

        // Manual value overrides the AI ballpark; AI fills in elsewhere.
        $manual = $this->item(['collection_id' => $collection->id, 'status' => Item::STATUS_PROCESSED]);
        $manual->metadata()->create(['category' => 'sports_card', 'value_from' => 100, 'value_to' => 200, 'ai_value_from' => 1, 'ai_value_to' => 2]);

        $aiOnly = $this->item(['collection_id' => $collection->id, 'status' => Item::STATUS_PROCESSED]);
        $aiOnly->metadata()->create(['category' => 'sports_card', 'ai_value_from' => 10, 'ai_value_to' => 20]);

        $this->actingAs($this->user)
            ->get('/collections')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('collections.0.value.from', 110)
                ->where('collections.0.value.to', 220)
            );
    }

    public function test_collection_items_can_be_sorted_by_value(): void
    {
        $collection = Collection::create(['user_id' => $this->user->id, 'name' => 'Box']);

        $cheap = $this->item(['collection_id' => $collection->id]);
        $cheap->metadata()->create(['category' => 'sports_card', 'ai_value_from' => 1, 'ai_value_to' => 2]);
        $pricey = $this->item(['collection_id' => $collection->id]);
        $pricey->metadata()->create(['category' => 'sports_card', 'value_from' => 500, 'value_to' => 900]);
        $unvalued = $this->item(['collection_id' => $collection->id]);

        $this->actingAs($this->user)
            ->get("/collections/{$collection->id}?sort=value")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.0.id', $pricey->id)
                ->where('items.1.id', $cheap->id)
                ->where('items.2.id', $unvalued->id)
                ->where('collection.value.from', 501)
                ->where('collection.value.to', 902)
            );
    }

    public function test_collections_require_authentication(): void
    {
        $this->get('/collections')->assertRedirect('/login');
    }
}
