<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Item;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    private function processedItem(array $metadata = []): Item
    {
        $item = Item::create(['user_id' => $this->admin->id, 'status' => Item::STATUS_PROCESSED, 'processed_at' => now()]);
        $item->metadata()->create(array_merge(['category' => 'sports_card', 'player_name' => 'Sample Player'], $metadata));

        return $item;
    }

    public function test_selling_a_card_documents_it_and_removes_it_from_totals(): void
    {
        $kept = $this->processedItem(['value_from' => 100, 'value_to' => 100]);
        $sold = $this->processedItem(['value_from' => 400, 'value_to' => 400]);

        $this->actingAs($this->admin)->post("/items/{$sold->id}/withdraw", [
            'reason' => 'sold',
            'sale_price' => '450',
            'sale_date' => '2026-07-29',
            'buyer' => 'John',
            'platform' => 'eBay',
        ]);

        $sold->refresh();
        $this->assertSame(Item::DISPOSITION_GONE, $sold->disposition);
        $this->assertSame(450.0, $sold->activeWithdrawal()->sale_price);

        // Home totals now reflect only the card still owned.
        $this->actingAs($this->admin)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.itemsProcessed', 1)
                ->where('stats.value.from', 100)
            );

        // And the processed list no longer shows it.
        $this->actingAs($this->admin)
            ->get('/items')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)
                ->where('items.0.id', $kept->id)
            );
    }

    public function test_relocated_cards_stay_in_the_collection_totals(): void
    {
        $moved = $this->processedItem(['value_from' => 200, 'value_to' => 200]);

        $this->actingAs($this->admin)->post("/items/{$moved->id}/withdraw", [
            'reason' => 'moved',
            'destination' => 'Home safe',
        ]);

        $this->assertSame(Item::DISPOSITION_RELOCATED, $moved->fresh()->disposition);

        // Still owned: value stays.
        $this->actingAs($this->admin)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.value.from', 200));
    }

    public function test_other_reason_requires_notes_and_double_removal_is_rejected(): void
    {
        $item = $this->processedItem();

        $this->actingAs($this->admin)
            ->post("/items/{$item->id}/withdraw", ['reason' => 'other'])
            ->assertSessionHasErrors('notes');

        $this->actingAs($this->admin)->post("/items/{$item->id}/withdraw", ['reason' => 'moved', 'destination' => 'Safe']);
        $this->actingAs($this->admin)->post("/items/{$item->id}/withdraw", ['reason' => 'sold']);

        // Second removal did not overwrite the first.
        $this->assertSame(1, $item->withdrawals()->count());
        $this->assertSame(Item::DISPOSITION_RELOCATED, $item->fresh()->disposition);
    }

    public function test_reinstate_reuses_the_original_record(): void
    {
        $item = $this->processedItem();
        $this->actingAs($this->admin)->post("/items/{$item->id}/withdraw", ['reason' => 'grading', 'destination' => 'PSA']);

        $this->actingAs($this->admin)->post("/items/{$item->id}/reinstate", ['notes' => 'Returned as PSA 9, added to box 2']);

        $item->refresh();
        $this->assertNull($item->disposition);
        $this->assertNull($item->withdrawn_at);
        // Same item id, full trail kept.
        $withdrawal = $item->withdrawals()->first();
        $this->assertNotNull($withdrawal->reinstated_at);
        $this->assertSame('Returned as PSA 9, added to box 2', $withdrawal->reinstate_notes);
    }

    public function test_update_location_keeps_every_hop_in_the_trail(): void
    {
        $item = $this->processedItem();
        $this->actingAs($this->admin)->post("/items/{$item->id}/withdraw", ['reason' => 'moved', 'destination' => 'Home safe']);

        $this->actingAs($this->admin)->post("/items/{$item->id}/location", ['destination' => 'Bank box']);

        $item->refresh();
        $this->assertSame(Item::DISPOSITION_RELOCATED, $item->disposition);
        $this->assertSame(2, $item->withdrawals()->count());
        $this->assertSame('Bank box', $item->activeWithdrawal()->destination);
    }

    public function test_item_page_shows_the_disposition(): void
    {
        $item = $this->processedItem();
        $this->actingAs($this->admin)->post("/items/{$item->id}/withdraw", [
            'reason' => 'sold', 'sale_price' => '99', 'buyer' => 'Jane',
        ]);

        $this->actingAs($this->admin)
            ->get("/items/{$item->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('item.disposition', 'gone')
                ->where('item.withdrawal.reasonLabel', 'Sold')
                ->where('item.withdrawal.salePrice', 99)
                ->where('item.withdrawal.buyer', 'Jane')
                ->has('item.withdrawalHistory', 1)
            );
    }

    public function test_scanners_cannot_remove_reinstate_or_delete(): void
    {
        $scanner = User::factory()->scanner()->create();
        $item = $this->processedItem();

        $this->actingAs($scanner)->post("/items/{$item->id}/withdraw", ['reason' => 'sold'])->assertForbidden();
        $this->actingAs($scanner)->post("/items/{$item->id}/reinstate")->assertForbidden();
        $this->actingAs($scanner)->delete("/items/{$item->id}")->assertForbidden();
        $this->actingAs($scanner)->get('/settings')->assertForbidden();
        $this->actingAs($scanner)->get('/reports')->assertForbidden();
        $this->actingAs($scanner)->get('/export')->assertForbidden();

        // But scanners still capture, browse, and pack.
        $this->actingAs($scanner)->get('/')->assertOk();
        $this->actingAs($scanner)->get('/storage')->assertOk();
        $this->actingAs($scanner)->get("/items/{$item->id}")->assertOk();
    }

    public function test_collection_totals_exclude_sold_cards(): void
    {
        $collection = Collection::create(['user_id' => $this->admin->id, 'name' => 'Main']);
        $kept = $this->processedItem(['value_from' => 100, 'value_to' => 100]);
        $sold = $this->processedItem(['value_from' => 900, 'value_to' => 900]);
        Item::whereIn('id', [$kept->id, $sold->id])->update(['collection_id' => $collection->id]);

        $this->actingAs($this->admin)->post("/items/{$sold->id}/withdraw", ['reason' => 'sold', 'sale_price' => '950']);

        $this->actingAs($this->admin)
            ->get('/collections')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('collections.0.itemCount', 1)
                ->where('collections.0.value.from', 100)
            );
    }
}
