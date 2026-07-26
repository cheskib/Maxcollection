<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Metadata;
use App\Models\ProcessingJob;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_required_tables_exist(): void
    {
        foreach ([
            'users', 'items', 'images', 'metadata', 'metadata_history',
            'processing_jobs', 'processing_logs', 'settings',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_item_has_one_metadata_record(): void
    {
        $user = User::factory()->create();
        $item = Item::create(['user_id' => $user->id]);

        $item->metadata()->create([
            'category' => 'sports_card',
            'confidence' => 92.5,
            'player_name' => 'Test Player',
        ]);

        $this->assertInstanceOf(Metadata::class, $item->fresh()->metadata);
        $this->assertSame('Test Player', $item->fresh()->metadata->player_name);
    }

    public function test_metadata_history_is_append_only_with_created_at(): void
    {
        $user = User::factory()->create();
        $item = Item::create(['user_id' => $user->id]);

        $record = $item->metadataHistory()->create([
            'user_id' => $user->id,
            'field_name' => 'player_name',
            'previous_value' => null,
            'new_value' => 'Corrected Player',
        ]);

        $this->assertNotNull($record->created_at);
        $this->assertSame(1, $item->metadataHistory()->count());
    }

    public function test_processing_job_records_logs(): void
    {
        $user = User::factory()->create();
        $item = Item::create(['user_id' => $user->id]);

        $job = $item->processingJobs()->create(['status' => ProcessingJob::STATUS_QUEUED]);
        $job->logs()->create(['level' => 'info', 'message' => 'Queued for processing.']);

        $this->assertSame(1, $job->logs()->count());
        $this->assertSame(ProcessingJob::STATUS_QUEUED, $item->processingJobs()->first()->status);
    }

    public function test_deleting_an_item_cascades_to_related_tables(): void
    {
        $user = User::factory()->create();
        $item = Item::create(['user_id' => $user->id]);
        $item->metadata()->create(['category' => 'coin']);
        $item->metadataHistory()->create(['user_id' => $user->id, 'field_name' => 'country', 'new_value' => 'USA']);
        $job = $item->processingJobs()->create(['status' => ProcessingJob::STATUS_COMPLETED]);
        $job->logs()->create(['message' => 'Done.']);

        $item->delete();

        $this->assertDatabaseCount('metadata', 0);
        $this->assertDatabaseCount('metadata_history', 0);
        $this->assertDatabaseCount('processing_jobs', 0);
        $this->assertDatabaseCount('processing_logs', 0);
    }

    public function test_confidence_threshold_setting_is_seeded(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->assertSame('75', Setting::value('confidence_threshold'));
    }
}
