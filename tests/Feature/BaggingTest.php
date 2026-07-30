<?php

namespace Tests\Feature;

use App\Models\BaggingEvent;
use App\Models\Barcode;
use App\Models\Batch;
use App\Models\IngestFile;
use App\Models\Item;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaggingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function batchForBag(string $code, ?string $flag = null): Batch
    {
        $barcode = Barcode::create(['type' => 'bag', 'code' => $code]);
        $batch = Batch::create([
            'user_id' => $this->user->id, 'source' => 'scan',
            'barcode_id' => $barcode->id, 'capture_flag' => $flag,
        ]);
        Item::create(['user_id' => $this->user->id, 'batch_id' => $batch->id, 'status' => Item::STATUS_PROCESSED]);

        return $batch;
    }

    private function scan(string $code): array
    {
        $response = $this->actingAs($this->user)->from('/bagging')->post('/bagging/scan', ['code' => $code]);
        $response->assertRedirect('/bagging');

        return session('scan');
    }

    public function test_good_bag_scan_in_then_scan_out(): void
    {
        $this->batchForBag('BAG-000001');

        $verdict = $this->scan('BAG-000001');
        $this->assertTrue($verdict['ok']);
        $this->assertStringContainsString('good', $verdict['message']);

        $done = $this->scan('BAG-000001');
        $this->assertTrue($done['ok']);
        $this->assertStringContainsString('done in', $done['message']);

        $this->assertSame(1, BaggingEvent::where('action', BaggingEvent::BAG_DONE)->whereNotNull('seconds')->count());
    }

    public function test_flagged_bag_locks_the_line_until_set_aside(): void
    {
        $this->batchForBag('BAG-000001', 'missing_side');
        $this->batchForBag('BAG-000002');

        $verdict = $this->scan('BAG-000001');
        $this->assertFalse($verdict['ok']);
        $this->assertStringContainsString('FLAGGED (missing side)', $verdict['message']);

        // Next bin refused; finishing the flagged bag itself refused.
        $this->assertStringContainsString('Finish BAG-000001 first', $this->scan('BAG-000002')['message']);
        $this->assertStringContainsString('must be set aside', $this->scan('BAG-000001')['message']);

        // SET-ASIDE closes it out; the next bin is welcome.
        $aside = $this->scan('SET-ASIDE');
        $this->assertTrue($aside['ok']);
        $this->assertTrue($this->scan('BAG-000002')['ok']);
    }

    public function test_three_flagged_in_a_row_raises_the_alarm(): void
    {
        foreach (['BAG-000001', 'BAG-000002', 'BAG-000003'] as $code) {
            $this->batchForBag($code, 'ticket_mismatch');
        }

        $this->assertFalse($this->scan('BAG-000001')['alarm']);
        $this->scan('SET-ASIDE');
        $this->assertFalse($this->scan('BAG-000002')['alarm']);
        $this->scan('SET-ASIDE');
        $this->assertTrue($this->scan('BAG-000003')['alarm']);

        $this->assertSame(1, BaggingEvent::where('action', BaggingEvent::ALARM)->count());
    }

    public function test_good_bag_resets_the_flag_streak(): void
    {
        $this->batchForBag('BAG-000001', 'missing_side');
        $this->batchForBag('BAG-000002');
        $this->batchForBag('BAG-000003', 'missing_side');
        $this->batchForBag('BAG-000004', 'missing_side');

        $this->scan('BAG-000001');
        $this->scan('SET-ASIDE');
        $this->scan('BAG-000002');
        $this->scan('BAG-000002');
        $this->scan('BAG-000003');
        $this->scan('SET-ASIDE');
        $this->assertFalse($this->scan('BAG-000004')['alarm']);
    }

    public function test_still_checking_and_unknown_bags(): void
    {
        $station = Station::issue('Desk', Station::TYPE_CARDS);
        IngestFile::create([
            'station_id' => $station->id, 'folder' => 'BAG-000009', 'filename' => 'x.jpg',
            'checksum' => str_repeat('a', 64), 'size_bytes' => 1, 'path' => 'ingest/x.jpg',
        ]);

        $this->assertStringContainsString('Still checking', $this->scan('BAG-000009')['message']);
        $this->assertStringContainsString('Unknown bag', $this->scan('BAG-000404')['message']);
    }

    public function test_set_aside_without_a_flagged_bag_is_refused(): void
    {
        $this->batchForBag('BAG-000001');

        $this->assertStringContainsString('Nothing to set aside', $this->scan('SET-ASIDE')['message']);

        $this->scan('BAG-000001');
        $this->assertStringContainsString('not flagged', $this->scan('SET-ASIDE')['message']);
    }

    public function test_bagging_page_shows_open_state_and_counts(): void
    {
        $this->batchForBag('BAG-000001');
        $this->scan('BAG-000001');

        $this->actingAs($this->user)
            ->get('/bagging')
            ->assertInertia(fn ($page) => $page
                ->component('Bagging')
                ->where('open.bagCode', 'BAG-000001')
                ->where('open.verdict', 'good')
            );
    }
}
