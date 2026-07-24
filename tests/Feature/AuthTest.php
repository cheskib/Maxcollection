<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_shown_to_guests(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_seeded_administrator_can_login(): void
    {
        $this->seed(AdminUserSeeder::class);

        $response = $this->post('/login', [
            'email' => 'cheskib@gmail.com',
            'password' => 'collection321$$',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_second_seeded_administrator_can_login(): void
    {
        $this->seed(AdminUserSeeder::class);

        $response = $this->post('/login', [
            'email' => 'srulymax007@gmail.com',
            'password' => 'collection321$$',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->seed(AdminUserSeeder::class);

        $response = $this->from('/login')->post('/login', [
            'email' => 'cheskib@gmail.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_view_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect('/');
    }
}
