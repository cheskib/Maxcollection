<?php

namespace Tests\Feature;

use App\Models\CardSet;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CardSetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.openai.key' => 'test-key']);
        $this->user = User::factory()->create();
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function openAiResponse(array $result): array
    {
        return [
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => json_encode($result)],
                    ],
                ],
            ],
        ];
    }

    private function processCard(array $fields): Item
    {
        Http::fakeSequence('api.openai.com/*')
            ->push($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 95,
                'fields' => $fields,
            ]))
            ->push($this->openAiResponse([
                'description' => 'Wood-grain borders frame each photo; backs are yellow with full stats.',
            ]));

        $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('card.jpg'),
        ]);
        $item = Item::latest('id')->first();

        $this->actingAs($this->user)->post('/process');

        return $item;
    }

    public function test_processing_a_card_creates_its_set_profile(): void
    {
        $this->processCard([
            'player_name' => 'Don Mattingly',
            'sport' => 'Baseball',
            'manufacturer' => 'Topps',
            'year' => '1987',
        ]);

        $set = CardSet::first();
        $this->assertNotNull($set);
        $this->assertSame('1987 Topps Baseball', $set->displayName());
        // The catalog is hidden for now: no AI description is written.
        $this->assertNull($set->description);
        $this->assertSame(1, $set->cardsQuery()->count());
    }

    public function test_cards_from_the_same_set_share_one_profile(): void
    {
        // One sequence for the whole run: identify #1, identify #2 (no
        // set description while the catalog is hidden).
        Http::fakeSequence('api.openai.com/*')
            ->push($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 95,
                'fields' => ['player_name' => 'Don Mattingly', 'sport' => 'Baseball', 'manufacturer' => 'Topps', 'year' => '1987'],
            ]))
            ->push($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 95,
                'fields' => ['player_name' => 'Bobby Witt', 'sport' => 'Baseball', 'manufacturer' => 'Topps', 'year' => '1987'],
            ]));

        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('a.jpg')]);
        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('b.jpg')]);

        $this->actingAs($this->user)->post('/process');

        $this->assertSame(1, CardSet::count());
        $this->assertSame(2, CardSet::first()->cardsQuery()->count());
    }

    public function test_sets_pages_list_profiles_and_cards(): void
    {
        $this->processCard(['player_name' => 'Don Mattingly', 'sport' => 'Baseball', 'manufacturer' => 'Topps', 'year' => '1987']);
        $set = CardSet::first();

        $this->actingAs($this->user)
            ->get('/sets')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Sets')
                ->has('sets', 1)
                ->where('sets.0.name', '1987 Topps Baseball')
                ->where('sets.0.cardCount', 1)
            );

        $this->actingAs($this->user)
            ->get("/sets/{$set->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SetDetail')
                ->has('cards', 1)
                ->where('cards.0.title', '1987 Topps Don Mattingly')
            );
    }

    public function test_the_owner_can_rewrite_a_set_description(): void
    {
        $this->processCard(['player_name' => 'Don Mattingly', 'sport' => 'Baseball', 'manufacturer' => 'Topps', 'year' => '1987']);
        $set = CardSet::first();

        $this->actingAs($this->user)
            ->put("/sets/{$set->id}", ['description' => 'My own notes about this set.'])
            ->assertRedirect();

        $this->assertSame('My own notes about this set.', $set->fresh()->description);
    }

    public function test_description_failure_does_not_block_processing(): void
    {
        Http::fakeSequence('api.openai.com/*')
            ->push($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 95,
                'fields' => ['player_name' => 'Solo Player', 'sport' => 'Baseball', 'manufacturer' => 'Fleer', 'year' => '1990'],
            ]))
            ->push('Server error', 500);

        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('c.jpg')]);
        $item = Item::latest('id')->first();
        $this->actingAs($this->user)->post('/process');

        // The card processed fine; the profile exists with its write-up pending.
        $this->assertSame(Item::STATUS_PROCESSED, $item->fresh()->status);
        $this->assertNull(CardSet::first()->description);
    }

    public function test_sets_require_authentication(): void
    {
        $this->get('/sets')->assertRedirect('/login');
    }
}
