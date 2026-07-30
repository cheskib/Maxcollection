<?php

namespace Tests\Feature;

use App\Jobs\ProcessIngestFolderJob;
use App\Models\Barcode;
use App\Models\Batch;
use App\Models\IngestFile;
use App\Models\Item;
use App\Models\Station;
use App\Models\User;
use App\Services\BarcodeReader;
use App\Services\ProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiagnoseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create();
    }

    private function flaggedBatch(string $code = 'BAG-000123', string $flag = 'missing_side', bool $bound = true): Batch
    {
        $barcode = Barcode::firstOrCreate(['code' => $code], ['type' => 'bag']);
        $batch = Batch::create([
            'user_id' => $this->admin->id, 'source' => 'scan', 'label' => $code,
            'barcode_id' => $bound ? $barcode->id : null, 'capture_flag' => $flag,
            'finalized_at' => $bound ? now() : null,
        ]);

        $item = Item::create(['user_id' => $this->admin->id, 'batch_id' => $batch->id]);
        Storage::disk('local')->put("original/{$item->id}/a.jpg", 'bytes');
        $item->images()->create([
            'path' => "original/{$item->id}/a.jpg", 'original_filename' => 'a.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 5, 'role' => 'front',
        ]);

        return $batch;
    }

    public function test_scanning_a_flagged_bag_opens_its_diagnosis(): void
    {
        $batch = $this->flaggedBatch();

        $this->actingAs($this->admin)
            ->post('/diagnose/scan', ['code' => 'bag-000123'])
            ->assertRedirect(route('diagnose.show', $batch));

        $this->actingAs($this->admin)
            ->post('/diagnose/scan', ['code' => 'BAG-000999'])
            ->assertSessionHas('status');
    }

    public function test_confirm_clears_the_flag_and_logs_who(): void
    {
        $batch = $this->flaggedBatch();

        $this->actingAs($this->admin)
            ->post("/diagnose/{$batch->id}/resolve", ['resolution' => 'confirmed', 'note' => 'False alarm'])
            ->assertRedirect(route('diagnose'));

        $batch->refresh();
        $this->assertNull($batch->capture_flag);
        $this->assertSame('confirmed', $batch->resolution);
        $this->assertSame('False alarm', $batch->resolution_note);
        $this->assertSame($this->admin->id, $batch->resolved_by);
        $this->assertNotNull($batch->resolved_at);
        // Items untouched — the capture was good.
        $this->assertSame(1, $batch->items()->count());
    }

    public function test_rescan_deletes_items_keeps_tombstone_and_releases_the_bag(): void
    {
        $batch = $this->flaggedBatch();
        $imagePath = $batch->items()->first()->images()->first()->path;

        $this->actingAs($this->admin)
            ->post("/diagnose/{$batch->id}/resolve", ['resolution' => 'rescan', 'note' => 'Pulled 2 cards']);

        $batch->refresh();
        $this->assertSame('rescan', $batch->resolution);
        $this->assertNull($batch->barcode_id);
        $this->assertSame(0, $batch->items()->count());
        Storage::disk('local')->assertMissing($imagePath);
        // The sticker itself survives — the bag returns to the scan line.
        $this->assertNull(Barcode::where('code', 'BAG-000123')->first()->voided_at);
    }

    public function test_replaced_also_voids_the_sticker(): void
    {
        $batch = $this->flaggedBatch();

        $this->actingAs($this->admin)
            ->post("/diagnose/{$batch->id}/resolve", ['resolution' => 'replaced']);

        $barcode = Barcode::where('code', 'BAG-000123')->first();
        $this->assertNotNull($barcode->voided_at);
        $this->assertNotNull($barcode->void_reason);
        $this->assertSame('replaced', $batch->fresh()->resolution);
    }

    public function test_rescan_lets_the_pipeline_rebind_and_link_the_attempts(): void
    {
        Bus::fake([\App\Jobs\ArchiveBatchJob::class, \App\Jobs\ProcessItemJob::class]);
        $station = Station::issue('Desk', Station::TYPE_CARDS, $this->admin->id);
        $old = $this->flaggedBatch();

        $this->actingAs($this->admin)->post("/diagnose/{$old->id}/resolve", ['resolution' => 'rescan']);

        // The bag rescans: new folder, same number.
        foreach (['000-ticket.jpg', '001-front.jpg', '001-back.jpg'] as $name) {
            $path = "ingest/{$station->id}/BAG-000123/{$name}";
            Storage::disk('local')->put($path, "bytes-{$name}");
            $file = IngestFile::create([
                'station_id' => $station->id, 'folder' => 'BAG-000123', 'filename' => $name,
                'checksum' => hash('sha256', "bytes-{$name}"), 'size_bytes' => 5, 'path' => $path,
            ]);
            $file->timestamps = false;
            $file->forceFill(['created_at' => now()->subSeconds(60)])->save();
        }

        $reader = new FakeBarcodeReader(['000-ticket.jpg' => ['BAG-000123']]);
        (new ProcessIngestFolderJob($station->id, 'BAG-000123'))->handle($reader, app(ProcessingService::class));

        $fresh = Batch::where('source', 'scan')->whereNull('resolution')->where('label', 'BAG-000123')->first();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->capture_flag);
        $this->assertNotNull($fresh->barcode_id);
        // Both attempts on one page: the tombstone points forward.
        $this->assertSame($fresh->id, $old->fresh()->superseded_by_batch_id);
    }

    public function test_voided_bag_never_returns(): void
    {
        Bus::fake([\App\Jobs\ArchiveBatchJob::class, \App\Jobs\ProcessItemJob::class]);
        $station = Station::issue('Desk', Station::TYPE_CARDS, $this->admin->id);
        $old = $this->flaggedBatch();
        $this->actingAs($this->admin)->post("/diagnose/{$old->id}/resolve", ['resolution' => 'replaced']);

        $path = "ingest/{$station->id}/BAG-000123/000-ticket.jpg";
        Storage::disk('local')->put($path, 'bytes');
        $file = IngestFile::create([
            'station_id' => $station->id, 'folder' => 'BAG-000123', 'filename' => '000-ticket.jpg',
            'checksum' => hash('sha256', 'bytes'), 'size_bytes' => 5, 'path' => $path,
        ]);
        $file->timestamps = false;
        $file->forceFill(['created_at' => now()->subSeconds(60)])->save();

        (new ProcessIngestFolderJob($station->id, 'BAG-000123'))
            ->handle(new FakeBarcodeReader(['000-ticket.jpg' => ['BAG-000123']]), app(ProcessingService::class));

        $fresh = Batch::whereNull('resolution')->where('label', 'BAG-000123')->first();
        $this->assertSame('bag_unregistered', $fresh->capture_flag);
        $this->assertNull($fresh->barcode_id);
    }

    public function test_unbound_batch_cannot_be_confirmed(): void
    {
        $batch = $this->flaggedBatch('BAG-000123', 'bag_unregistered', bound: false);

        $this->actingAs($this->admin)
            ->post("/diagnose/{$batch->id}/resolve", ['resolution' => 'confirmed'])
            ->assertSessionHas('status');

        $this->assertNull($batch->fresh()->resolution);
    }

    public function test_scanners_cannot_diagnose(): void
    {
        $scanner = User::factory()->scanner()->create();
        $batch = $this->flaggedBatch();

        $this->actingAs($scanner)->get('/diagnose')->assertStatus(403);
        $this->actingAs($scanner)->post("/diagnose/{$batch->id}/resolve", ['resolution' => 'confirmed'])->assertStatus(403);
    }
}
