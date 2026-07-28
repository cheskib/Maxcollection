<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\KeyName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class KeyCardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.openai.key' => 'test-key']);
        $this->user = User::factory()->create();
        KeyName::forgetCache();
    }

    /**
     * @param string $player
     * @return array<string, mixed>
     */
    private function aiResponse(string $player): array
    {
        return [
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'category' => 'sports_card',
                        'confidence' => 95,
                        'fields' => ['player_name' => $player],
                    ]),
                ]],
            ]],
        ];
    }

    /**
     * Capture one item per player, then process them all against a
     * response sequence (stacked Http::fake calls would all resolve to
     * the first registration).
     *
     * @param array<int, string> $players
     * @return array<int, Item>
     */
    private function processCards(array $players): array
    {
        $items = [];
        foreach ($players as $player) {
            $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('c.jpg')]);
            $items[] = Item::latest('id')->first();
        }

        $sequence = Http::fakeSequence('api.openai.com/*');
        foreach ($players as $player) {
            $sequence->push($this->aiResponse($player));
        }

        $this->actingAs($this->user)->post('/process');

        return $items;
    }

    private function processCard(string $player): Item
    {
        return $this->processCards([$player])[0];
    }

    public function test_watchlist_is_seeded_across_sports(): void
    {
        $this->assertGreaterThan(300, KeyName::count());
        foreach (['Baseball', 'Football', 'Basketball', 'Hockey', 'Soccer', 'Golf', 'Tennis', 'Boxing', 'Wrestling', 'Racing'] as $sport) {
            $this->assertTrue(KeyName::where('sport', $sport)->exists(), "no names for {$sport}");
        }
    }

    public function test_processing_flags_key_names_regardless_of_value(): void
    {
        [$mantle, $nobody] = $this->processCards(['Mickey Mantle', 'John Nobody']);

        $this->assertTrue($mantle->fresh()->metadata->key_card);
        $this->assertFalse($nobody->fresh()->metadata->key_card);
    }

    public function test_editing_the_player_name_reevaluates_the_flag(): void
    {
        $item = $this->processCard('John Nobody');

        $this->actingAs($this->user)->put("/items/{$item->id}/metadata", [
            'category' => 'sports_card',
            'player_name' => 'Wayne Gretzky',
        ]);

        $this->assertTrue($item->fresh()->metadata->key_card);

        $this->actingAs($this->user)->put("/items/{$item->id}/metadata", [
            'category' => 'sports_card',
            'player_name' => 'John Nobody Again',
        ]);

        $this->assertFalse($item->fresh()->metadata->key_card);
    }

    public function test_key_cards_can_be_listed_and_flagged_in_lists(): void
    {
        [$mantle] = $this->processCards(['Mickey Mantle', 'John Nobody']);

        $this->actingAs($this->user)
            ->get('/items?key=1')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)
                ->where('items.0.id', $mantle->id)
                ->where('items.0.keyCard', true)
            );

        $this->actingAs($this->user)
            ->get('/items/summary')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('keyCards', 1));
    }

    public function test_watchlist_can_be_edited_and_reflags_existing_cards(): void
    {
        $item = $this->processCard('Zeke Unknown');
        $this->assertFalse($item->fresh()->metadata->key_card);

        // Adding the name flags the existing card...
        $this->actingAs($this->user)
            ->post('/settings/key-names', ['sport' => 'Baseball', 'name' => 'Zeke Unknown'])
            ->assertRedirect();
        $this->assertTrue($item->fresh()->metadata->key_card);

        // ...and removing it clears the flag again.
        $entry = KeyName::where('name', 'Zeke Unknown')->first();
        $this->actingAs($this->user)
            ->delete("/settings/key-names/{$entry->id}")
            ->assertRedirect();
        $this->assertFalse($item->fresh()->metadata->key_card);
    }
}
