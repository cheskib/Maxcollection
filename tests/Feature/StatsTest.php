<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ProcessingJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function processedItem(array $metadata = []): Item
    {
        $item = Item::create([
            'user_id' => $this->user->id,
            'status' => Item::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);

        $item->metadata()->create(array_merge([
            'category' => 'sports_card',
            'confidence' => 90,
        ], $metadata));

        return $item;
    }

    public function test_overview_counts_and_clean_rate(): void
    {
        $this->processedItem(['confidence' => 80]);
        $this->processedItem(['confidence' => 90]);
        $this->processedItem(['confidence' => 100]);

        $reviewed = Item::create(['user_id' => $this->user->id, 'status' => Item::STATUS_NEEDS_REVIEW, 'review_reason' => 'low_confidence']);
        $reviewed->metadata()->create(['category' => 'sports_card', 'confidence' => 40]);

        // A captured-but-unprocessed item counts toward the total only.
        Item::create(['user_id' => $this->user->id, 'status' => Item::STATUS_CAPTURED]);

        $failedJob = $reviewed->processingJobs()->create(['status' => ProcessingJob::STATUS_FAILED]);

        $this->actingAs($this->user)
            ->get('/stats')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Stats')
                ->where('overview.totalItems', 5)
                ->where('overview.processed', 3)
                ->where('overview.needsReview', 1)
                // 3 clean of 4 finished = 75%.
                ->where('overview.cleanRate', 75)
                // Average confidence over processed items only: (80+90+100)/3.
                ->where('overview.avgConfidence', 90)
                ->where('overview.failedJobs', 1)
            );
    }

    public function test_breakdowns_cover_processed_items_only(): void
    {
        $this->processedItem(['year' => '1987', 'manufacturer' => 'Topps', 'sport' => 'Baseball']);
        $this->processedItem(['year' => '1987', 'manufacturer' => 'Topps', 'sport' => 'Baseball']);
        $this->processedItem(['year' => '1990', 'manufacturer' => 'Fleer', 'sport' => 'Football']);

        $reviewed = Item::create(['user_id' => $this->user->id, 'status' => Item::STATUS_NEEDS_REVIEW, 'review_reason' => 'ai_failure']);
        $reviewed->metadata()->create(['category' => 'sports_card', 'year' => '1999', 'sport' => 'Hockey']);

        $this->actingAs($this->user)
            ->get('/stats')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('byYear', 2)
                ->where('byYear.0.value', '1987')
                ->where('byYear.0.count', 2)
                ->where('byYear.1.value', '1990')
                ->has('byManufacturer', 2)
                ->where('byManufacturer.0.value', 'Topps')
                ->has('bySport', 2)
                ->where('bySport.0.value', 'Baseball')
                ->where('bySport.0.count', 2)
            );
    }

    public function test_review_reasons_get_readable_labels(): void
    {
        foreach (['low_confidence', 'low_confidence', 'ai_failure'] as $reason) {
            Item::create(['user_id' => $this->user->id, 'status' => Item::STATUS_NEEDS_REVIEW, 'review_reason' => $reason]);
        }

        $this->actingAs($this->user)
            ->get('/stats')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('reviewReasons', 2)
                ->where('reviewReasons.0.reason', 'Low confidence')
                ->where('reviewReasons.0.count', 2)
                ->where('reviewReasons.1.reason', 'AI failure')
            );
    }

    public function test_stats_page_requires_authentication(): void
    {
        $this->get('/stats')->assertRedirect('/login');
    }
}
