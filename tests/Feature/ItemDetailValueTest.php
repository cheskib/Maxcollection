<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ItemDetailValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_ballpark_gets_market_check_links(): void
    {
        $user = User::factory()->create();
        $item = Item::create(['user_id' => $user->id, 'status' => Item::STATUS_PROCESSED]);
        $item->metadata()->create([
            'category' => 'sports_card', 'player_name' => 'Don Mattingly',
            'year' => '1987', 'manufacturer' => 'Topps', 'card_number' => '500',
            'ai_value_from' => 5, 'ai_value_to' => 12,
        ]);

        $this->actingAs($user)
            ->get("/items/{$item->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('item.value.check.0.label', 'eBay solds')
                ->where('item.value.check.1.label', 'SportsCardsPro')
                ->where('item.value.check.0.url', fn ($url) => str_contains($url, 'LH_Sold=1')
                    && str_contains($url, urlencode('1987 Topps Don Mattingly #500')))
            );
    }

    public function test_comics_get_ebay_only_and_blank_items_get_none(): void
    {
        $user = User::factory()->create();

        $comic = Item::create(['user_id' => $user->id, 'status' => Item::STATUS_PROCESSED]);
        $comic->metadata()->create(['category' => 'comic_book', 'title' => 'Test Title', 'issue_number' => '129']);

        $this->actingAs($user)
            ->get("/items/{$comic->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('item.value.check.0.label', 'eBay solds')
                ->missing('item.value.check.1')
            );

        $blank = Item::create(['user_id' => $user->id, 'status' => Item::STATUS_PROCESSED]);
        $blank->metadata()->create(['category' => 'sports_card']);

        $this->actingAs($user)
            ->get("/items/{$blank->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('item.value.check', []));
    }
}
