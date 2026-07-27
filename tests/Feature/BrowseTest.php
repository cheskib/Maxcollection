<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BrowseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function card(array $metadata): Item
    {
        $item = Item::create([
            'user_id' => $this->user->id,
            'status' => Item::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);

        $item->metadata()->create(array_merge(['category' => 'sports_card', 'confidence' => 95], $metadata));

        return $item;
    }

    public function test_top_level_groups_by_sport_with_counts(): void
    {
        $this->card(['sport' => 'Baseball', 'player_name' => 'A']);
        $this->card(['sport' => 'Baseball', 'player_name' => 'B']);
        $this->card(['sport' => 'Football', 'player_name' => 'C']);
        $this->card(['sport' => null, 'player_name' => 'D']);

        $this->actingAs($this->user)
            ->get('/browse')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Browse')
                ->where('level', 'sport')
                ->has('groups', 3)
                ->where('groups.0.label', 'Baseball')
                ->where('groups.0.count', 2)
                ->where('groups.2.label', 'Unknown')
            );
    }

    public function test_drilling_into_a_sport_groups_by_year_newest_first(): void
    {
        $this->card(['sport' => 'Baseball', 'year' => '1987']);
        $this->card(['sport' => 'Baseball', 'year' => '1989']);
        $this->card(['sport' => 'Football', 'year' => '1989']);

        $this->actingAs($this->user)
            ->get('/browse?sport=Baseball')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('level', 'year')
                ->has('groups', 2)
                ->where('groups.0.label', '1989')
                ->where('groups.1.label', '1987')
            );
    }

    public function test_drilling_into_a_year_groups_by_team(): void
    {
        $this->card(['sport' => 'Baseball', 'year' => '1987', 'team' => 'Rangers']);
        $this->card(['sport' => 'Baseball', 'year' => '1987', 'team' => 'Rangers']);
        $this->card(['sport' => 'Baseball', 'year' => '1987', 'team' => 'Yankees']);

        $this->actingAs($this->user)
            ->get('/browse?sport=Baseball&year=1987')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('level', 'team')
                ->has('groups', 2)
                ->where('groups.0.label', 'Rangers')
                ->where('groups.0.count', 2)
            );
    }

    public function test_final_level_lists_players_alphabetically(): void
    {
        $this->card(['sport' => 'Baseball', 'year' => '1987', 'team' => 'Rangers', 'player_name' => 'Ruben Sierra']);
        $this->card(['sport' => 'Baseball', 'year' => '1987', 'team' => 'Rangers', 'player_name' => 'Mike Loynd']);
        $this->card(['sport' => 'Baseball', 'year' => '1987', 'team' => 'Yankees', 'player_name' => 'Other Guy']);

        $this->actingAs($this->user)
            ->get('/browse?sport=Baseball&year=1987&team=Rangers')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('level', 'players')
                ->has('items', 2)
                ->where('items.0.player', 'Mike Loynd')
                ->where('items.1.player', 'Ruben Sierra')
            );
    }

    public function test_unknown_drills_into_cards_missing_the_value(): void
    {
        $this->card(['sport' => null, 'year' => '1990', 'team' => 'Bulls', 'player_name' => 'No Sport']);
        $this->card(['sport' => 'Baseball', 'year' => '1990']);

        $this->actingAs($this->user)
            ->get('/browse?sport=Unknown')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('level', 'year')
                ->has('groups', 1)
                ->where('groups.0.label', '1990')
                ->where('groups.0.count', 1)
            );
    }

    public function test_browse_requires_authentication(): void
    {
        $this->get('/browse')->assertRedirect('/login');
    }
}
