<?php

namespace Tests\Feature;

use App\Jobs\ArchiveBatchJob;
use App\Jobs\ProcessIngestFolderJob;
use App\Jobs\ProcessItemJob;
use App\Models\Barcode;
use App\Models\Batch;
use App\Models\IngestFile;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Station;
use App\Models\User;
use App\Services\BarcodeReader;
use App\Services\ProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A reader that answers from a path → codes map instead of zbarimg. */
class FakeBarcodeReader extends BarcodeReader
{
    /** @param array<string, array<int, string>> $byBasename */
    public function __construct(private array $byBasename = [])
    {
    }

    public function read(string $absolutePath): array
    {
        return $this->byBasename[basename($absolutePath)] ?? [];
    }
}

class IngestPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create();
        $this->station = Station::issue('Card scan desk 1', Station::TYPE_CARDS, $this->admin->id);
    }

    /** Store a fake scanned JPEG and its ingest record, aged past quiet. */
    private function receivedFile(string $folder, string $filename, int $ageSeconds = 60): IngestFile
    {
        $path = "ingest/{$this->station->id}/{$folder}/{$filename}";
        Storage::disk('local')->put($path, "bytes-of-{$filename}");

        $file = IngestFile::create([
            'station_id' => $this->station->id,
            'folder' => $folder,
            'filename' => $filename,
            'checksum' => hash('sha256', "bytes-of-{$filename}"),
            'size_bytes' => 10,
            'path' => $path,
        ]);
        $file->timestamps = false;
        $file->forceFill(['created_at' => now()->subSeconds($ageSeconds)])->save();

        return $file;
    }

    private function runFolder(string $folder, array $barcodeMap): void
    {
        (new ProcessIngestFolderJob($this->station->id, $folder))
            ->handle(new FakeBarcodeReader($barcodeMap), app(ProcessingService::class));
    }

    public function test_quiet_folder_becomes_a_bound_batch_with_paired_items(): void
    {
        Bus::fake([ArchiveBatchJob::class, ProcessItemJob::class]);
        $barcode = Barcode::create(['type' => 'bag', 'code' => 'BAG-000123']);
        $collection = \App\Models\Collection::create(['name' => "Sruli's", 'user_id' => $this->admin->id]);
        Setting::updateOrCreate(['key' => 'default_collection_id'], ['value' => (string) $collection->id]);

        $this->receivedFile('BAG-000123', '000-ticket.jpg');
        foreach (['001-front.jpg', '001-back.jpg', '002-front.jpg', '002-back.jpg'] as $name) {
            $this->receivedFile('BAG-000123', $name);
        }

        $this->runFolder('BAG-000123', ['000-ticket.jpg' => ['BAG-000123']]);

        $batch = Batch::first();
        $this->assertSame('scan', $batch->source);
        $this->assertNull($batch->capture_flag);
        $this->assertSame($barcode->id, $batch->barcode_id);
        $this->assertNotNull($batch->finalized_at);
        $this->assertSame($this->station->id, $batch->station_id);
        $this->assertSame($this->admin->id, $batch->user_id);

        $items = Item::where('batch_id', $batch->id)->get();
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame($collection->id, $item->collection_id);
            $this->assertSame(['front', 'back'], $item->images()->orderBy('id')->pluck('role')->all());
            // AI queued in the background for every item.
            $this->assertSame(Item::STATUS_QUEUED, $item->fresh()->status);
        }

        $this->assertSame(0, IngestFile::whereNull('processed_at')->count());
        Bus::assertDispatched(ArchiveBatchJob::class);
        Bus::assertDispatchedTimes(ProcessItemJob::class, 2);
    }

    public function test_folder_still_receiving_is_left_alone(): void
    {
        $this->receivedFile('BAG-000123', '000-ticket.jpg', ageSeconds: 5);

        $this->runFolder('BAG-000123', ['000-ticket.jpg' => ['BAG-000123']]);

        $this->assertSame(0, Batch::count());
        $this->assertSame(1, IngestFile::whereNull('processed_at')->count());
    }

    public function test_ticket_beats_folder_name_and_mismatch_is_flagged(): void
    {
        Bus::fake([ArchiveBatchJob::class, ProcessItemJob::class]);
        $right = Barcode::create(['type' => 'bag', 'code' => 'BAG-000456']);

        $this->receivedFile('BAG-000123', '000-ticket.jpg');
        $this->receivedFile('BAG-000123', '001-front.jpg');
        $this->receivedFile('BAG-000123', '001-back.jpg');

        $this->runFolder('BAG-000123', ['000-ticket.jpg' => ['BAG-000456']]);

        $batch = Batch::first();
        $this->assertSame('ticket_mismatch', $batch->capture_flag);
        // The physical sticker is the truth: bound to the ticket's bag.
        $this->assertSame($right->id, $batch->barcode_id);
    }

    public function test_unregistered_bag_is_flagged_and_unbound(): void
    {
        Bus::fake([ArchiveBatchJob::class, ProcessItemJob::class]);

        $this->receivedFile('BAG-000123', '000-ticket.jpg');
        $this->receivedFile('BAG-000123', '001-front.jpg');
        $this->receivedFile('BAG-000123', '001-back.jpg');

        $this->runFolder('BAG-000123', ['000-ticket.jpg' => ['BAG-000123']]);

        $batch = Batch::first();
        $this->assertSame('bag_unregistered', $batch->capture_flag);
        $this->assertNull($batch->barcode_id);
        $this->assertNull($batch->finalized_at);
        Bus::assertNotDispatched(ArchiveBatchJob::class);
    }

    public function test_odd_side_count_is_flagged_and_orphan_kept(): void
    {
        Bus::fake([ArchiveBatchJob::class, ProcessItemJob::class]);
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000123']);

        $this->receivedFile('BAG-000123', '000-ticket.jpg');
        foreach (['001-front.jpg', '001-back.jpg', '002-front.jpg'] as $name) {
            $this->receivedFile('BAG-000123', $name);
        }

        $this->runFolder('BAG-000123', ['000-ticket.jpg' => ['BAG-000123']]);

        $batch = Batch::first();
        $this->assertSame('missing_side', $batch->capture_flag);
        $this->assertSame(2, Item::where('batch_id', $batch->id)->count());
        $this->assertSame(1, Item::where('batch_id', $batch->id)->orderByDesc('id')->first()->images()->count());
    }

    public function test_bag_already_used_is_a_conflict(): void
    {
        Bus::fake([ArchiveBatchJob::class, ProcessItemJob::class]);
        $barcode = Barcode::create(['type' => 'bag', 'code' => 'BAG-000123']);
        Batch::create(['user_id' => $this->admin->id, 'source' => 'bulk', 'barcode_id' => $barcode->id]);

        $this->receivedFile('BAG-000123', '000-ticket.jpg');
        $this->receivedFile('BAG-000123', '001-front.jpg');
        $this->receivedFile('BAG-000123', '001-back.jpg');

        $this->runFolder('BAG-000123', ['000-ticket.jpg' => ['BAG-000123']]);

        $batch = Batch::where('source', 'scan')->first();
        $this->assertSame('bag_conflict', $batch->capture_flag);
        $this->assertNull($batch->barcode_id);
    }

    public function test_unreadable_ticket_falls_back_to_folder_name_flagged(): void
    {
        Bus::fake([ArchiveBatchJob::class, ProcessItemJob::class]);
        Barcode::create(['type' => 'bag', 'code' => 'BAG-000123']);

        // Reader sees nothing; the first file is kept as a card so no
        // image is dropped on a wrong guess.
        $this->receivedFile('BAG-000123', '000-ticket.jpg');
        $this->receivedFile('BAG-000123', '001-front.jpg');

        $this->runFolder('BAG-000123', []);

        $batch = Batch::first();
        $this->assertSame('ticket_unverified', $batch->capture_flag);
        $this->assertNotNull($batch->barcode_id);
        $this->assertSame(1, Item::where('batch_id', $batch->id)->count());
        $this->assertSame(2, Item::where('batch_id', $batch->id)->first()->images()->count());
    }

    public function test_ingest_upload_schedules_folder_processing(): void
    {
        Bus::fake([ProcessIngestFolderJob::class]);

        $content = 'jpeg-bytes';
        $this->postJson('/api/ingest', [
            'folder' => 'BAG-000123',
            'filename' => '001-front.jpg',
            'checksum' => hash('sha256', $content),
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('001-front.jpg', $content),
        ], ['X-Station-Token' => $this->station->token])->assertStatus(201);

        Bus::assertDispatched(ProcessIngestFolderJob::class, fn (ProcessIngestFolderJob $job) => $job->folder === 'BAG-000123');
    }
}
