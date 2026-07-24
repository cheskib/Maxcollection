<?php

namespace Tests\Feature;

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
