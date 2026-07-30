<?php

namespace Tests\Feature;

use App\Models\IngestFile;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StationIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function station(string $type = Station::TYPE_CARDS): Station
    {
        return Station::issue('Card scan desk 1', $type);
    }

    private function payload(string $content = 'jpeg-bytes'): array
    {
        return [
            'folder' => 'BAG-000123',
            'filename' => '001-front.jpg',
            'checksum' => hash('sha256', $content),
            'file' => UploadedFile::fake()->createWithContent('001-front.jpg', $content),
        ];
    }

    public function test_upload_requires_a_valid_token(): void
    {
        $this->postJson('/api/ingest', $this->payload())->assertStatus(401);

        $this->postJson('/api/ingest', $this->payload(), ['X-Station-Token' => 'wrong'])
            ->assertStatus(401);
    }

    public function test_revoked_station_is_refused(): void
    {
        $station = $this->station();
        $token = $station->token;
        $station->update(['revoked_at' => now()]);

        $this->postJson('/api/ingest', $this->payload(), ['X-Station-Token' => $token])
            ->assertStatus(401);
    }

    public function test_upload_stores_file_and_records_it(): void
    {
        $station = $this->station();

        $this->postJson('/api/ingest', $this->payload(), ['X-Station-Token' => $station->token])
            ->assertStatus(201)
            ->assertJson(['status' => 'stored']);

        $record = IngestFile::first();
        $this->assertSame('BAG-000123', $record->folder);
        $this->assertSame('001-front.jpg', $record->filename);
        $this->assertSame($station->id, $record->station_id);
        Storage::disk('local')->assertExists($record->path);

        // The station's heartbeat updates on every authenticated call.
        $this->assertNotNull($station->fresh()->last_seen_at);
    }

    public function test_retrying_the_same_file_is_idempotent(): void
    {
        $station = $this->station();

        $this->postJson('/api/ingest', $this->payload(), ['X-Station-Token' => $station->token])->assertStatus(201);
        $this->postJson('/api/ingest', $this->payload(), ['X-Station-Token' => $station->token])
            ->assertStatus(200)
            ->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, IngestFile::count());
    }

    public function test_corrupted_transfer_is_rejected(): void
    {
        $station = $this->station();

        $payload = $this->payload();
        $payload['checksum'] = hash('sha256', 'different-bytes');

        $this->postJson('/api/ingest', $payload, ['X-Station-Token' => $station->token])
            ->assertStatus(422);

        $this->assertSame(0, IngestFile::count());
    }

    public function test_admin_registers_downloads_config_and_revokes(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post('/settings/stations', ['name' => 'Card scan desk 1', 'type' => 'cards'])
            ->assertRedirect();

        $station = Station::first();
        $this->assertSame('cards', $station->type);
        $this->assertStringStartsWith('mxc_', $station->token);

        $config = $this->actingAs($admin)->get("/settings/stations/{$station->id}/config");
        $config->assertOk();
        $decoded = json_decode($config->getContent(), true);
        $this->assertSame($station->token, $decoded['token']);
        $this->assertArrayHasKey('server', $decoded);

        $this->actingAs($admin)->post("/settings/stations/{$station->id}/revoke")->assertRedirect();
        $this->assertNotNull($station->fresh()->revoked_at);

        // A revoked station's config is gone.
        $this->actingAs($admin)->get("/settings/stations/{$station->id}/config")->assertStatus(410);
    }

    public function test_scanners_cannot_manage_stations(): void
    {
        $scanner = User::factory()->scanner()->create();

        $this->actingAs($scanner)
            ->post('/settings/stations', ['name' => 'Sneaky', 'type' => 'cards'])
            ->assertStatus(403);
    }
}
