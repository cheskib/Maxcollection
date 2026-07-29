<?php

namespace Tests\Feature;

use App\Models\Barcode;
use App\Models\Batch;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_pipeline_shows_where_every_card_stands(): void
    {
        // One at each stage: capturing, bagged, boxed, sold.
        Item::create(['user_id' => $this->admin->id, 'status' => Item::STATUS_CAPTURED]);

        $baggedBatch = Batch::create([
            'user_id' => $this->admin->id, 'source' => 'bulk',
            'barcode_id' => Barcode::create(['type' => 'bag', 'code' => 'BAG-000001'])->id,
            'status' => Batch::STATUS_CLOSED, 'finalized_at' => now(),
        ]);
        Item::create(['user_id' => $this->admin->id, 'batch_id' => $baggedBatch->id, 'status' => Item::STATUS_PROCESSED, 'processed_at' => now()]);

        $sold = Item::create(['user_id' => $this->admin->id, 'status' => Item::STATUS_PROCESSED, 'processed_at' => now()]);
        $this->actingAs($this->admin)->post("/items/{$sold->id}/withdraw", ['reason' => 'sold', 'sale_price' => '125']);

        $this->actingAs($this->admin)
            ->get('/reports')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports')
                ->where('pipeline.capturing', 1)
                ->where('pipeline.bagged', 1)
                ->where('pipeline.boxed', 0)
                ->where('pipeline.gone', 1)
                ->where('pipeline.soldTotal', 125)
            );
    }

    public function test_daily_activity_reports_inbound_and_outbound(): void
    {
        $item = Item::create(['user_id' => $this->admin->id, 'status' => Item::STATUS_PROCESSED, 'processed_at' => now()]);
        $item->metadata()->create(['category' => 'sports_card']);
        $this->actingAs($this->admin)->post("/items/{$item->id}/withdraw", ['reason' => 'sold', 'sale_price' => '75']);

        $this->actingAs($this->admin)
            ->get('/reports')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('days', 14)
                // Today: 1 captured, 1 processed, 1 sold for $75.
                ->where('days.0.captured', 1)
                ->where('days.0.processed', 1)
                ->where('days.0.removals.0.reason', 'Sold')
                ->where('days.0.removals.0.count', 1)
                ->where('days.0.removals.0.soldTotal', 75)
            );
    }

    public function test_admin_can_create_accounts_and_change_roles(): void
    {
        $this->actingAs($this->admin)->post('/settings/users', [
            'name' => 'Scanner One',
            'email' => 'scanner@example.com',
            'password' => 'password123',
            'role' => 'scanner',
        ]);

        $created = User::where('email', 'scanner@example.com')->first();
        $this->assertSame('scanner', $created->role);

        $this->actingAs($this->admin)->post("/settings/users/{$created->id}/role", ['role' => 'admin']);
        $this->assertSame('admin', $created->fresh()->role);

        // No self-demotion into a lockout.
        $this->actingAs($this->admin)->post("/settings/users/{$this->admin->id}/role", ['role' => 'scanner']);
        $this->assertSame('admin', $this->admin->fresh()->role);
    }

    public function test_scanners_cannot_manage_accounts(): void
    {
        $scanner = User::factory()->scanner()->create();

        $this->actingAs($scanner)->post('/settings/users', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'password123', 'role' => 'admin',
        ])->assertForbidden();
    }

    public function test_reports_require_authentication(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }
}
