<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ProcessingJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.openai.key' => 'test-key']);
        $this->user = User::factory()->create();
    }

    private function captureItem(): Item
    {
        $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        return Item::latest('id')->first();
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function openAiResponse(array $result): array
    {
        return [
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => json_encode($result)],
                    ],
                ],
            ],
        ];
    }

    public function test_processing_a_confident_sports_card_saves_metadata(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 93,
                'fields' => ['player_name' => 'Ken Griffey Jr.', 'year' => '1989', 'manufacturer' => 'Upper Deck'],
            ])),
        ]);

        $item = $this->captureItem();

        $this->actingAs($this->user)->post('/process')->assertRedirect('/');

        $item->refresh();
        $this->assertSame(Item::STATUS_PROCESSED, $item->status);
        $this->assertNull($item->review_reason);
        $this->assertNotNull($item->processed_at);
        $this->assertSame('sports_card', $item->metadata->category);
        $this->assertSame('Ken Griffey Jr.', $item->metadata->player_name);
        $this->assertSame(93.0, $item->metadata->confidence);

        $job = $item->processingJobs()->first();
        $this->assertSame(ProcessingJob::STATUS_COMPLETED, $job->status);
        $this->assertNotNull($job->raw_response);
        $this->assertGreaterThan(0, $job->logs()->count());
    }

    public function test_low_confidence_items_go_to_needs_review(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'coin',
                'confidence' => 40,
                'fields' => ['country' => 'USA', 'denomination' => 'Quarter'],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        $item->refresh();
        $this->assertSame(Item::STATUS_NEEDS_REVIEW, $item->status);
        $this->assertSame('low_confidence', $item->review_reason);
        $this->assertSame('coin', $item->metadata->category);
    }

    public function test_unsupported_items_go_to_needs_review(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'unsupported',
                'confidence' => 95,
                'fields' => [],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        $item->refresh();
        $this->assertSame(Item::STATUS_NEEDS_REVIEW, $item->status);
        $this->assertSame('unsupported_category', $item->review_reason);
    }

    public function test_missing_primary_metadata_goes_to_needs_review(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'comic_book',
                'confidence' => 90,
                'fields' => ['publisher' => 'Marvel'],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        $item->refresh();
        $this->assertSame(Item::STATUS_NEEDS_REVIEW, $item->status);
        $this->assertSame('missing_metadata', $item->review_reason);
    }

    public function test_api_failure_marks_job_failed_and_item_needs_review(): void
    {
        Http::fake(['api.openai.com/*' => Http::response('Server error', 500)]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        $item->refresh();
        $this->assertSame(Item::STATUS_NEEDS_REVIEW, $item->status);
        $this->assertSame('ai_failure', $item->review_reason);

        $job = $item->processingJobs()->first();
        $this->assertSame(ProcessingJob::STATUS_FAILED, $job->status);
        $this->assertNotNull($job->error_message);
    }

    public function test_one_failing_item_does_not_stop_others(): void
    {
        $good = $this->openAiResponse([
            'category' => 'stamp',
            'confidence' => 88,
            'fields' => ['country' => 'USA', 'issue_name' => 'Liberty'],
        ]);

        Http::fakeSequence('api.openai.com/*')
            ->push('Server error', 500)
            ->push($good);

        $first = $this->captureItem();
        $second = $this->captureItem();

        $this->actingAs($this->user)->post('/process');

        $this->assertSame(Item::STATUS_NEEDS_REVIEW, $first->fresh()->status);
        $this->assertSame(Item::STATUS_PROCESSED, $second->fresh()->status);
        $this->assertSame('stamp', $second->fresh()->metadata->category);
    }

    public function test_only_captured_items_are_queued(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'coin',
                'confidence' => 90,
                'fields' => ['country' => 'USA', 'denomination' => 'Dime'],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');
        $this->assertSame(1, $item->processingJobs()->count());

        // Second run has nothing to queue: the item is already processed.
        $this->actingAs($this->user)->post('/process');
        $this->assertSame(1, $item->fresh()->processingJobs()->count());
    }

    public function test_missing_api_key_is_a_handled_failure(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        $item->refresh();
        $this->assertSame(Item::STATUS_NEEDS_REVIEW, $item->status);
        $this->assertSame('ai_failure', $item->review_reason);
        Http::assertNothingSent();
    }

    public function test_standard_processing_uses_the_standard_model(): void
    {
        config(['services.openai.model' => 'gpt-4.1-mini']);
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 90,
                'fields' => ['player_name' => 'Standard Player'],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        Http::assertSent(fn ($request) => $request['model'] === 'gpt-4.1-mini');
        $this->assertSame('gpt-4.1-mini', $item->processingJobs()->latest('id')->first()->model);
    }

    public function test_premium_reprocess_uses_the_premium_model(): void
    {
        config([
            'services.openai.model' => 'gpt-4.1-mini',
            'services.openai.premium_model' => 'gpt-4.1',
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 97,
                'fields' => ['player_name' => 'Premium Player'],
            ])),
        ]);

        $item = $this->captureItem();

        $this->actingAs($this->user)
            ->post("/items/{$item->id}/reprocess", ['tier' => 'premium'])
            ->assertRedirect("/items/{$item->id}");

        Http::assertSent(fn ($request) => $request['model'] === 'gpt-4.1');
        $this->assertSame('gpt-4.1', $item->processingJobs()->latest('id')->first()->model);
        $this->assertSame('Premium Player', $item->fresh()->metadata->player_name);
    }

    public function test_ai_never_trims_or_tilts_photos(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 95,
                'fields' => ['player_name' => 'Framed Player'],
                'tilts' => [7.5],
                'trims' => [['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        // Framing is the photographer's responsibility (owner decision):
        // trim/tilt data in an AI response is ignored entirely.
        $image = $item->images()->first();
        $this->assertSame(0.0, $image->tilt);
        $this->assertSame([0, 0, 0, 0], [
            $image->crop_top, $image->crop_right, $image->crop_bottom, $image->crop_left,
        ]);
    }

    public function test_batch_reprocess_from_originals_restores_scanner_framing(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 95,
                'fields' => ['player_name' => 'Restored Player'],
                'trims' => [['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15]],
            ])),
        ]);

        $item = $this->captureItem();
        $batch = \App\Models\Batch::create(['user_id' => $this->user->id, 'source' => 'bulk']);
        $item->update(['batch_id' => $batch->id]);
        $item->images()->first()->update(['crop_top' => 20, 'crop_left' => 10, 'tilt' => 3.0]);

        $this->actingAs($this->user)->post("/batches/{$batch->id}/reprocess", ['source' => 'original']);

        // Batch items are never AI-trimmed; a fresh start clears any old
        // trim and tilt so the scanner's own framing comes back.
        $image = $item->images()->first();
        $this->assertSame([0, 0, 0, 0], [
            $image->crop_top, $image->crop_right, $image->crop_bottom, $image->crop_left,
        ]);
        $this->assertSame(0.0, $image->tilt);
    }

    public function test_everything_can_be_reprocessed_at_once(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 92,
                'fields' => ['player_name' => 'Everyone'],
            ])),
        ]);

        $processed = $this->captureItem();
        $processed->update(['status' => Item::STATUS_PROCESSED]);
        $review = $this->captureItem();
        $review->update(['status' => Item::STATUS_NEEDS_REVIEW, 'review_reason' => 'low_confidence']);

        $this->actingAs($this->user)
            ->post('/reprocess-all', ['source' => 'cleaned'])
            ->assertRedirect();

        $this->assertSame(Item::STATUS_PROCESSED, $processed->fresh()->status);
        $this->assertSame(Item::STATUS_PROCESSED, $review->fresh()->status);
        $this->assertSame(1, $processed->processingJobs()->count());
        $this->assertSame(1, $review->processingJobs()->count());
    }

    public function test_a_whole_batch_can_be_reprocessed(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 92,
                'fields' => ['player_name' => 'Batch Player'],
            ])),
        ]);

        $batch = \App\Models\Batch::create(['user_id' => $this->user->id, 'source' => 'bulk']);
        $processed = $this->captureItem();
        $processed->update(['batch_id' => $batch->id, 'status' => Item::STATUS_PROCESSED]);
        $captured = $this->captureItem();
        $captured->update(['batch_id' => $batch->id]);
        $outside = $this->captureItem();

        $this->actingAs($this->user)
            ->post("/batches/{$batch->id}/reprocess", ['source' => 'cleaned'])
            ->assertRedirect();

        // Both batch members were re-run regardless of status; the item
        // outside the batch was left alone.
        $this->assertSame(Item::STATUS_PROCESSED, $processed->fresh()->status);
        $this->assertSame(Item::STATUS_PROCESSED, $captured->fresh()->status);
        $this->assertSame(1, $processed->processingJobs()->count());
        $this->assertSame(1, $captured->processingJobs()->count());
        $this->assertSame(0, $outside->processingJobs()->count());
    }

    public function test_ai_cleanup_can_be_undone(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 95,
                'fields' => ['player_name' => 'Undo Player'],
                'rotations' => [180],
                'trims' => [['top' => 12, 'right' => 3, 'bottom' => 4, 'left' => 5]],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        $image = $item->images()->first();
        $this->assertSame(180, $image->rotation);
        $this->assertNotNull($image->previous_adjustments);

        // Undo restores the pre-AI cleanup (untouched, here)...
        $this->actingAs($this->user)->post("/images/{$image->id}/undo");
        $image->refresh();
        $this->assertSame(0, $image->rotation);

        // ...and undoing again swaps the AI cleanup back.
        $this->actingAs($this->user)->post("/images/{$image->id}/undo");
        $image->refresh();
        $this->assertSame(180, $image->rotation);
    }

    public function test_undo_without_a_snapshot_is_a_404(): void
    {
        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('u.jpg')]);
        $image = Item::first()->images()->first();

        $this->actingAs($this->user)->post("/images/{$image->id}/undo")->assertNotFound();
    }

    public function test_reprint_and_subset_cards_save_card_type_and_original_year(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 94,
                'fields' => [
                    'player_name' => 'Don Mattingly',
                    'manufacturer' => 'Topps',
                    'year' => '1987',
                    'original_year' => '1972',
                    'card_type' => 'All-Star',
                ],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        $metadata = $item->fresh()->metadata;
        $this->assertSame('1987', $metadata->year);
        $this->assertSame('1972', $metadata->original_year);
        $this->assertSame('All-Star', $metadata->card_type);
        // Subset cards read like collectors say them.
        $this->assertSame('1987 Topps All-Star Don Mattingly', $metadata->primaryTitle());
    }

    public function test_checklist_cards_do_not_need_a_player_name(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 92,
                'fields' => ['card_type' => 'Checklist', 'manufacturer' => 'Topps', 'year' => '1987', 'sport' => 'Baseball'],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        // No player name, but a checklist legitimately has none.
        $item->refresh();
        $this->assertSame(Item::STATUS_PROCESSED, $item->status);
        $this->assertNull($item->review_reason);
    }

    public function test_sport_is_capitalized_when_saved(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 92,
                'fields' => ['player_name' => 'Dan Marino', 'sport' => 'football'],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        $this->assertSame('Football', $item->fresh()->metadata->sport);
    }

    public function test_reprocessing_from_originals_restores_framing_and_sets_rotation(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 96,
                'fields' => ['player_name' => 'Fresh Player'],
                'rotations' => [90],
            ])),
        ]);

        $item = $this->captureItem();
        $item->images()->first()->update(['rotation' => 270, 'crop_top' => 40, 'crop_left' => 20, 'tilt' => 3.0]);

        $this->actingAs($this->user)
            ->post("/items/{$item->id}/reprocess", ['tier' => 'standard', 'source' => 'original'])
            ->assertRedirect("/items/{$item->id}");

        $image = $item->images()->first();
        // The AI saw the untouched photo: its rotation is absolute, and the
        // photo returns to its original framing (no trim, no tilt).
        $this->assertSame(90, $image->rotation);
        $this->assertSame(0.0, $image->tilt);
        $this->assertSame([0, 0, 0, 0], [
            $image->crop_top, $image->crop_right, $image->crop_bottom, $image->crop_left,
        ]);
    }

    public function test_invalid_source_is_rejected(): void
    {
        Http::fake();
        $item = $this->captureItem();

        $this->actingAs($this->user)
            ->post("/items/{$item->id}/reprocess", ['source' => 'raw'])
            ->assertSessionHasErrors('source');
    }

    public function test_invalid_tier_is_rejected(): void
    {
        Http::fake();
        $item = $this->captureItem();

        $this->actingAs($this->user)
            ->post("/items/{$item->id}/reprocess", ['tier' => 'super-deluxe'])
            ->assertSessionHasErrors('tier');
    }

    public function test_ai_reported_rotations_auto_orient_the_photos(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 95,
                'fields' => ['player_name' => 'Turned Player'],
                'rotations' => [180, 90],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('back.jpg'),
            'item_id' => $item->id,
        ]);

        $this->actingAs($this->user)->post('/process');

        $rotations = $item->images()->orderBy('id')->pluck('rotation')->all();
        $this->assertSame([180, 90], $rotations);
    }

    public function test_ai_rotation_is_additive_to_manual_rotation(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 95,
                'fields' => ['player_name' => 'Additive Player'],
                'rotations' => [90],
            ])),
        ]);

        $item = $this->captureItem();
        $item->images()->first()->update(['rotation' => 270]);

        $this->actingAs($this->user)->post('/process');

        $this->assertSame(0, $item->images()->first()->rotation);
    }

    public function test_manual_rotation_locks_out_ai_rotation(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 95,
                'fields' => ['player_name' => 'Locked Player'],
                'rotations' => [180],
            ])),
        ]);

        $item = $this->captureItem();
        $image = $item->images()->first();

        // The user rotates by hand: orientation is now locked.
        $this->actingAs($this->user)->post("/images/{$image->id}/rotate");
        $this->assertTrue($image->fresh()->rotation_locked);

        // Neither a normal run nor a reprocess-from-originals may change it.
        $this->actingAs($this->user)->post('/process');
        $this->assertSame(90, $image->fresh()->rotation);

        $this->actingAs($this->user)->post("/items/{$item->id}/reprocess", ['source' => 'original']);
        $this->assertSame(90, $image->fresh()->rotation);
    }

    public function test_missing_rotations_leave_photos_untouched(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 95,
                'fields' => ['player_name' => 'Plain Player'],
            ])),
        ]);

        $item = $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        $this->assertSame(0, $item->images()->first()->rotation);
    }

    public function test_home_stats_reflect_processing_results(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'category' => 'sports_card',
                'confidence' => 91,
                'fields' => ['player_name' => 'Test Player'],
            ])),
        ]);

        $this->captureItem();
        $this->actingAs($this->user)->post('/process');

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('stats.itemsProcessed', 1)
                ->where('stats.needsReview', 0)
            );
    }
}
