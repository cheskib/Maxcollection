<?php

namespace Tests\Feature;

use App\Models\Item;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_the_four_statistics(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Home')
                ->has('stats.itemsCaptured')
                ->has('stats.itemsProcessed')
                ->has('stats.needsReview')
                ->has('stats.picturesUploaded')
            );
    }

    public function test_home_shows_the_collection_value_total(): void
    {
        $user = User::factory()->create();

        $manual = Item::create(['user_id' => $user->id, 'status' => Item::STATUS_PROCESSED]);
        $manual->metadata()->create(['category' => 'sports_card', 'value_from' => 100, 'value_to' => 200, 'ai_value_from' => 1, 'ai_value_to' => 2]);

        $aiOnly = Item::create(['user_id' => $user->id, 'status' => Item::STATUS_PROCESSED]);
        $aiOnly->metadata()->create(['category' => 'sports_card', 'ai_value_from' => 10, 'ai_value_to' => 20]);

        // A card with no value doesn't count toward the valued total.
        $unvalued = Item::create(['user_id' => $user->id, 'status' => Item::STATUS_PROCESSED]);
        $unvalued->metadata()->create(['category' => 'sports_card']);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.value.from', 110)
                ->where('stats.value.to', 220)
                ->where('stats.value.valuedCount', 2)
            );
    }

    public function test_settings_shows_coming_soon(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('ComingSoon'));
    }

    public function test_settings_requires_authentication(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }
}
