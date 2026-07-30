<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Item;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminControlsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create();
    }

    public function test_admin_sets_the_scan_line_collection(): void
    {
        $collection = Collection::create(['user_id' => $this->admin->id, 'name' => "Sruli's"]);

        $this->actingAs($this->admin)
            ->post('/settings/default-collection', ['collection_id' => $collection->id])
            ->assertRedirect();

        $this->assertSame((string) $collection->id, Setting::value('default_collection_id'));

        $this->actingAs($this->admin)
            ->get('/settings')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('defaultCollectionId', $collection->id)
            );
    }

    public function test_unknown_collection_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/default-collection', ['collection_id' => 999])
            ->assertSessionHasErrors('collection_id');
    }

    public function test_ai_hold_keeps_items_queued_and_release_resumes(): void
    {
        config(['services.openai.key' => null]);

        // Hold on: processing a captured item leaves it queued.
        Setting::updateOrCreate(['key' => 'ai_hold'], ['value' => '1']);
        $item = Item::create(['user_id' => $this->admin->id, 'status' => Item::STATUS_CAPTURED]);

        $this->actingAs($this->admin)->post('/process');
        $this->assertSame(Item::STATUS_QUEUED, $item->fresh()->status);

        // Release: the queued job re-dispatches and runs (null key -> the
        // item leaves the queue through the normal failure path).
        $this->actingAs($this->admin)->post('/settings/ai-hold', ['hold' => false]);
        $this->assertNotSame(Item::STATUS_QUEUED, $item->fresh()->status);
    }

    public function test_stall_rescue_leaves_held_items_alone(): void
    {
        Setting::updateOrCreate(['key' => 'ai_hold'], ['value' => '1']);
        $item = Item::create(['user_id' => $this->admin->id, 'status' => Item::STATUS_QUEUED]);
        Item::where('id', $item->id)->update(['updated_at' => now()->subMinutes(30)]);

        // Home triggers the stall rescue; a held item is waiting on
        // purpose and must not be marked as a failure.
        $this->actingAs($this->admin)->get('/');
        $this->assertSame(Item::STATUS_QUEUED, $item->fresh()->status);
    }

    public function test_scanners_cannot_touch_admin_controls(): void
    {
        $scanner = User::factory()->scanner()->create();
        $collection = Collection::create(['user_id' => $this->admin->id, 'name' => 'Main']);

        $this->actingAs($scanner)->post('/settings/default-collection', ['collection_id' => $collection->id])->assertForbidden();
        $this->actingAs($scanner)->post('/settings/ai-hold', ['hold' => true])->assertForbidden();
    }
}
