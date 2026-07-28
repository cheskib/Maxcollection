<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DuplicatesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    private function card(array $metadata): Item
    {
        $item = Item::create(['user_id' => $this->user->id, 'status' => Item::STATUS_PROCESSED]);
        $item->metadata()->create(array_merge([
            'category' => 'sports_card',
            'confidence' => 90,
            'manufacturer' => 'Topps',
            'year' => '1987',
        ], $metadata));

        return $item;
    }

    public function test_item_page_shows_copies_of_the_same_card(): void
    {
        $first = $this->card(['player_name' => 'Don Mattingly', 'card_number' => '500']);
        $second = $this->card(['player_name' => 'Don Mattingly', 'card_number' => '500']);
        $this->card(['player_name' => 'Bobby Witt', 'card_number' => '415']);

        $this->actingAs($this->user)
            ->get("/items/{$first->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('item.copies.count', 2)
                ->where('item.copies.others.0', $second->id)
            );
    }

    public function test_duplicates_page_groups_multiples(): void
    {
        $this->card(['player_name' => 'Don Mattingly', 'card_number' => '500']);
        $this->card(['player_name' => 'Don Mattingly', 'card_number' => '500']);
        $this->card(['player_name' => 'Don Mattingly', 'card_number' => '500']);
        $this->card(['player_name' => 'Bobby Witt', 'card_number' => '415']);

        $this->actingAs($this->user)
            ->get('/duplicates')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Duplicates')
                ->has('groups', 1)
                ->where('groups.0.copies', 3)
                ->where('groups.0.title', '1987 Topps Don Mattingly #500')
            );
    }

    public function test_summary_links_to_duplicates(): void
    {
        $this->card(['player_name' => 'Don Mattingly', 'card_number' => '500']);
        $this->card(['player_name' => 'Don Mattingly', 'card_number' => '500']);

        $this->actingAs($this->user)
            ->get('/items/summary')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('duplicates', 1));
    }

    public function test_settings_can_change_the_review_threshold(): void
    {
        $this->actingAs($this->user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings')
                ->where('confidenceThreshold', 75)
            );

        $this->actingAs($this->user)
            ->post('/settings', ['confidence_threshold' => 90])
            ->assertRedirect();

        $this->assertSame('90', \App\Models\Setting::value('confidence_threshold'));
    }

    public function test_market_price_lookup_stores_the_match(): void
    {
        config(['services.pricecharting.token' => 'test-token']);
        \Illuminate\Support\Facades\Http::fake([
            'www.pricecharting.com/*' => \Illuminate\Support\Facades\Http::response([
                'product-name' => '1987 Topps Don Mattingly #500',
                'loose-price' => 1250,
            ]),
        ]);

        $item = $this->card(['player_name' => 'Don Mattingly', 'card_number' => '500']);

        $this->actingAs($this->user)
            ->post("/items/{$item->id}/market-value")
            ->assertRedirect();

        $metadata = $item->fresh()->metadata;
        $this->assertSame(12.5, $metadata->market_value);
        $this->assertSame('1987 Topps Don Mattingly #500', $metadata->market_match);
        $this->assertNotNull($metadata->market_checked_at);
    }

    public function test_market_price_requires_the_token(): void
    {
        config(['services.pricecharting.token' => null]);
        $item = $this->card(['player_name' => 'Don Mattingly']);

        $this->actingAs($this->user)
            ->post("/items/{$item->id}/market-value")
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'PRICECHARTING_TOKEN'));

        $this->assertNull($item->fresh()->metadata->market_value);
    }

    public function test_duplicates_require_authentication(): void
    {
        $this->get('/duplicates')->assertRedirect('/login');
    }
}
