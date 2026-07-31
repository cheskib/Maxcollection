<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EquipmentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_see_the_equipment_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/equipment')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Equipment'));
    }

    public function test_scanners_do_not(): void
    {
        $this->actingAs(User::factory()->scanner()->create())
            ->get('/equipment')
            ->assertStatus(403);
    }
}
