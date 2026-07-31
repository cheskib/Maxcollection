<?php

namespace Tests\Feature;

use App\Models\BaggingEvent;
use App\Models\Barcode;
use App\Models\Batch;
use App\Models\Station;
use App\Models\StorageBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class KpiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_dashboard_aggregates_baggers_stations_flags_and_ledger(): void
    {
        // A bagger with one finished bag (40s) and one set-aside today.
        $barcode = Barcode::create(['type' => 'bag', 'code' => 'BAG-000001']);
        $batch = Batch::create(['user_id' => $this->admin->id, 'source' => 'scan', 'barcode_id' => $barcode->id]);
        BaggingEvent::create(['user_id' => $this->admin->id, 'batch_id' => $batch->id, 'action' => BaggingEvent::BAG_DONE, 'seconds' => 40]);
        BaggingEvent::create(['user_id' => $this->admin->id, 'batch_id' => $batch->id, 'action' => BaggingEvent::SET_ASIDE, 'seconds' => 10]);
        BaggingEvent::create(['user_id' => $this->admin->id, 'batch_id' => $batch->id, 'action' => BaggingEvent::ALARM]);

        // A station and a flagged batch attributed to it.
        $station = Station::issue('Card scan desk 1', Station::TYPE_CARDS, $this->admin->id);
        Batch::create(['user_id' => $this->admin->id, 'source' => 'scan', 'station_id' => $station->id, 'capture_flag' => 'missing_side']);

        // Ledger: in-service bag above, one unused, one voided; a box in use.
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000002']);
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000003', 'voided_at' => now(), 'void_reason' => 'test']);
        $boxBarcode = Barcode::create(['type' => 'box', 'code' => 'BOX-000001']);
        StorageBox::create(['user_id' => $this->admin->id, 'barcode_id' => $boxBarcode->id, 'status' => 'open']);

        $this->actingAs($this->admin)
            ->get('/kpi')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Kpi')
                ->where('diagnoseWaiting', 1)
                ->where('baggers.0.doneToday', 1)
                ->where('baggers.0.averageSecondsToday', 40)
                ->where('baggers.0.setAsideWeek', 1)
                ->where('baggers.0.alarmsWeek', 1)
                ->where('stations.0.name', 'Card scan desk 1')
                ->where('stations.0.flagsWeek', 1)
                ->where('flags.0.flag', 'missing_side')
                ->where('flags.0.waiting', 1)
                ->where('ledger.0.type', 'bag')
                ->where('ledger.0.total', 3)
                ->where('ledger.0.inService', 1)
                ->where('ledger.0.unused', 1)
                ->where('ledger.0.voided', 1)
                ->where('ledger.1.type', 'box')
                ->where('ledger.1.inService', 1)
                ->where('alarms.0.who', $this->admin->name)
            );
    }

    public function test_sticker_lookup_reports_each_state(): void
    {
        $inService = Barcode::create(['type' => 'bag', 'code' => 'BAG-000001']);
        Batch::create(['user_id' => $this->admin->id, 'source' => 'scan', 'barcode_id' => $inService->id]);
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000002']);
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000003', 'voided_at' => now(), 'void_reason' => 'damaged']);

        $lookup = fn (string $code) => $this->actingAs($this->admin)->get('/kpi?sticker='.$code);

        $lookup('BAG-000001')->assertInertia(fn (AssertableInertia $page) => $page->where('stickerLookup.state', 'in service'));
        $lookup('bag-000002')->assertInertia(fn (AssertableInertia $page) => $page->where('stickerLookup.state', 'unused'));
        $lookup('BAG-000003')->assertInertia(fn (AssertableInertia $page) => $page->where('stickerLookup.state', 'voided'));
        $lookup('BAG-000404')->assertInertia(fn (AssertableInertia $page) => $page->where('stickerLookup.state', 'unknown'));
    }

    public function test_scanners_cannot_see_the_dashboard(): void
    {
        $this->actingAs(User::factory()->scanner()->create())->get('/kpi')->assertStatus(403);
    }
}
