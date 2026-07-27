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

    public function test_invalid_tier_is_rejected(): void
    {
        Http::fake();
        $item = $this->captureItem();

        $this->actingAs($this->user)
            ->post("/items/{$item->id}/reprocess", ['tier' => 'super-deluxe'])
            ->assertSessionHasErrors('tier');
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
