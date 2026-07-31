<?php

namespace Tests\Feature;

use App\Models\EquipmentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EquipmentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_seeded_items_grouped_by_station(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/equipment')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Equipment')
                ->where('items.comic_photo.0.name', 'Canon EOS R50 + 18-45mm kit')
                ->where('items.comic_photo.0.status', 'need')
            );

        // Every seeded item lives in a known station bucket.
        $this->assertSame(0, EquipmentItem::whereNotIn('station', EquipmentItem::STATIONS)->count());
    }

    public function test_admin_adds_flips_status_and_removes(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post('/equipment', [
            'station' => 'printing',
            'name' => 'Extra label roll',
            'status' => 'need',
            'price' => '$20',
            'url' => 'https://www.amazon.com/s?k=labels',
        ])->assertRedirect();

        $item = EquipmentItem::where('name', 'Extra label roll')->first();
        $this->assertSame('printing', $item->station);
        $this->assertSame('Link', $item->links[0]['label']);

        $this->actingAs($admin)->patch("/equipment/{$item->id}", ['status' => 'have'])->assertRedirect();
        $this->assertSame('have', $item->fresh()->status);

        $this->actingAs($admin)->delete("/equipment/{$item->id}")->assertRedirect();
        $this->assertNull(EquipmentItem::find($item->id));
    }

    public function test_invalid_station_and_status_are_rejected(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->from('/equipment')
            ->post('/equipment', ['station' => 'garage', 'name' => 'X', 'status' => 'need'])
            ->assertSessionHasErrors('station');

        $item = EquipmentItem::first();
        $this->actingAs($admin)
            ->from('/equipment')
            ->patch("/equipment/{$item->id}", ['status' => 'maybe'])
            ->assertSessionHasErrors('status');
    }

    public function test_scanners_cannot_view_or_edit(): void
    {
        $scanner = User::factory()->scanner()->create();

        $this->actingAs($scanner)->get('/equipment')->assertStatus(403);
        $this->actingAs($scanner)->post('/equipment', ['station' => 'printing', 'name' => 'X', 'status' => 'need'])->assertStatus(403);
    }
}
