<?php

namespace Tests\Feature;

use App\Jobs\ArchiveBatchJob;
use App\Models\Barcode;
use App\Models\Batch;
use App\Models\Item;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DropboxArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        config(['services.dropbox.key' => 'test-key', 'services.dropbox.secret' => 'test-secret']);
    }

    private function connectDropbox(): void
    {
        Setting::updateOrCreate(['key' => 'dropbox_refresh_token'], ['value' => 'refresh-token']);
    }

    /** A finalized batch with $imageCount stored original images. */
    private function finalizedBatch(int $imageCount = 2, string $bagCode = 'BAG-000001'): Batch
    {
        $barcode = Barcode::create(['type' => 'bag', 'code' => $bagCode]);
        $batch = Batch::create([
            'user_id' => $this->user->id, 'source' => 'bulk',
            'barcode_id' => $barcode->id, 'status' => Batch::STATUS_CLOSED, 'finalized_at' => now(),
        ]);

        for ($i = 0; $i < $imageCount; $i++) {
            $item = Item::create(['user_id' => $this->user->id, 'batch_id' => $batch->id, 'status' => Item::STATUS_PROCESSED]);
            $path = "original/{$item->id}/photo.jpg";
            Storage::disk('local')->put($path, "image-bytes-{$i}");
            $item->images()->create([
                'path' => $path, 'original_filename' => 'photo.jpg',
                'mime_type' => 'image/jpeg', 'size_bytes' => 10, 'role' => 'front',
            ]);
        }

        return $batch;
    }

    public function test_connect_redirects_to_dropbox_authorize(): void
    {
        $response = $this->actingAs($this->user)->get('/settings/dropbox/connect');

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://www.dropbox.com/oauth2/authorize', (string) $location);
        $this->assertStringContainsString('client_id=test-key', (string) $location);
        $this->assertStringContainsString('token_access_type=offline', (string) $location);
    }

    public function test_callback_stores_refresh_token_and_backfills(): void
    {
        Bus::fake();
        Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['refresh_token' => 'the-token'])]);
        $this->finalizedBatch();

        $this->actingAs($this->user)
            ->get('/settings/dropbox/callback?code=auth-code')
            ->assertRedirect(route('settings'));

        $this->assertSame('the-token', Setting::value('dropbox_refresh_token'));
        Bus::assertDispatched(ArchiveBatchJob::class);
    }

    public function test_cancelled_callback_stores_nothing(): void
    {
        $this->actingAs($this->user)
            ->get('/settings/dropbox/callback?error=access_denied')
            ->assertRedirect(route('settings'));

        $this->assertNull(Setting::value('dropbox_refresh_token'));
    }

    public function test_archive_job_uploads_originals_and_stamps_archived_at(): void
    {
        $this->connectDropbox();
        Http::fake([
            'api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'short-lived', 'expires_in' => 14400]),
            'content.dropboxapi.com/2/files/upload' => Http::response(['name' => 'ok']),
        ]);

        $batch = $this->finalizedBatch(2);

        (new ArchiveBatchJob($batch->id))->handle(app(\App\Services\DropboxService::class));

        $this->assertNotNull($batch->fresh()->archived_at);

        // Every upload lands in the bag's folder.
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'files/upload')) {
                return true;
            }
            $arg = json_decode($request->header('Dropbox-API-Arg')[0] ?? '{}', true);

            return str_starts_with($arg['path'] ?? '', '/BAG-000001/item-');
        });
    }

    public function test_archive_job_chunks_large_batches(): void
    {
        Bus::fake();
        $this->connectDropbox();
        Http::fake([
            'api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'short-lived', 'expires_in' => 14400]),
            'content.dropboxapi.com/2/files/upload' => Http::response(['name' => 'ok']),
        ]);

        $batch = $this->finalizedBatch(ArchiveBatchJob::CHUNK + 1);

        (new ArchiveBatchJob($batch->id))->handle(app(\App\Services\DropboxService::class));

        // Not finished: the remainder is re-queued and nothing is stamped.
        $this->assertNull($batch->fresh()->archived_at);
        Bus::assertDispatched(ArchiveBatchJob::class, fn (ArchiveBatchJob $job) => $job->offset === ArchiveBatchJob::CHUNK);

        // The follow-up run finishes the job.
        (new ArchiveBatchJob($batch->id, ArchiveBatchJob::CHUNK))->handle(app(\App\Services\DropboxService::class));
        $this->assertNotNull($batch->fresh()->archived_at);
    }

    public function test_finalizing_a_batch_queues_its_archive(): void
    {
        Bus::fake();
        $this->connectDropbox();

        Barcode::create(['type' => 'bag', 'code' => 'BAG-000009']);
        $batch = Batch::create(['user_id' => $this->user->id, 'source' => 'bulk']);
        $item = Item::create(['user_id' => $this->user->id, 'batch_id' => $batch->id, 'status' => Item::STATUS_PROCESSED]);
        $item->metadata()->create(['category' => 'sports_card']);

        $this->actingAs($this->user)->post("/batches/{$batch->id}/bag", ['code' => 'BAG-000009']);

        Bus::assertDispatched(ArchiveBatchJob::class, fn (ArchiveBatchJob $job) => $job->batchId === $batch->id);
    }

    public function test_job_is_a_quiet_noop_when_disconnected(): void
    {
        $batch = $this->finalizedBatch();

        (new ArchiveBatchJob($batch->id))->handle(app(\App\Services\DropboxService::class));

        $this->assertNull($batch->fresh()->archived_at);
        Http::assertNothingSent();
    }

    public function test_settings_page_reports_archive_state(): void
    {
        $this->connectDropbox();
        $archived = $this->finalizedBatch(1, 'BAG-000001');
        $archived->update(['archived_at' => now()]);
        $this->finalizedBatch(1, 'BAG-000002'); // pending

        $this->actingAs($this->user)
            ->get('/settings')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dropbox.configured', true)
                ->where('dropbox.connected', true)
                ->where('dropbox.archivedCount', 1)
                ->where('dropbox.pendingCount', 1)
            );
    }

    public function test_archive_pending_button_queues_unarchived_batches(): void
    {
        Bus::fake();
        $this->connectDropbox();
        $this->finalizedBatch(1, 'BAG-000001');
        $this->finalizedBatch(1, 'BAG-000002');

        $this->actingAs($this->user)->post('/settings/dropbox/archive-pending');

        Bus::assertDispatchedTimes(ArchiveBatchJob::class, 2);
    }

    public function test_disconnect_clears_the_connection(): void
    {
        $this->connectDropbox();

        $this->actingAs($this->user)->post('/settings/dropbox/disconnect');

        $this->assertNull(Setting::value('dropbox_refresh_token'));
    }
}
