<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkCaptureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    public function test_unprocessed_batches_reappear_in_the_workspace(): void
    {
        $awaiting = \App\Models\Batch::create(['user_id' => $this->user->id, 'source' => 'bulk']);
        \App\Models\Item::create(['user_id' => $this->user->id, 'batch_id' => $awaiting->id]);

        $converting = \App\Models\Batch::create(['user_id' => $this->user->id, 'source' => 'pdf', 'label' => 'mid.pdf']);

        // Fully processed batches stay out of the workspace.
        $done = \App\Models\Batch::create(['user_id' => $this->user->id, 'source' => 'pdf', 'label' => 'done.pdf', 'converted_at' => now()]);
        \App\Models\Item::create(['user_id' => $this->user->id, 'batch_id' => $done->id, 'status' => \App\Models\Item::STATUS_PROCESSED]);

        $this->actingAs($this->user)
            ->get('/capture/bulk')
            ->assertInertia(fn ($page) => $page
                ->has('pendingBatches', 2)
                ->where('pendingBatches.0.id', $awaiting->id)
                ->where('pendingBatches.0.captured', 1)
                ->where('pendingBatches.1.converting', true)
            );
    }

    public function test_bulk_capture_screen_renders(): void
    {
        $this->actingAs($this->user)->get('/capture/bulk')->assertOk();
    }

    public function test_a_group_of_photos_creates_one_item(): void
    {
        $response = $this->actingAs($this->user)->post('/capture/bulk/items', [
            'photos' => [
                UploadedFile::fake()->image('front.jpg'),
                UploadedFile::fake()->image('back.jpg'),
            ],
        ]);

        $response->assertCreated()->assertJson(['photos' => 2]);
        $this->assertDatabaseCount('items', 1);
        $this->assertSame(2, Item::first()->images()->count());
    }

    public function test_single_photo_group_creates_single_photo_item(): void
    {
        $this->actingAs($this->user)->post('/capture/bulk/items', [
            'photos' => [UploadedFile::fake()->image('only.jpg')],
        ])->assertCreated();

        $this->assertDatabaseCount('items', 1);
        $this->assertSame(1, Item::first()->images()->count());
    }

    public function test_separate_calls_create_separate_items(): void
    {
        foreach (['a', 'b', 'c'] as $name) {
            $this->actingAs($this->user)->post('/capture/bulk/items', [
                'photos' => [UploadedFile::fake()->image("{$name}.jpg")],
            ]);
        }

        $this->assertDatabaseCount('items', 3);
    }

    public function test_non_image_files_are_rejected(): void
    {
        $response = $this->actingAs($this->user)->postJson('/capture/bulk/items', [
            'photos' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('items', 0);
    }

    public function test_bulk_capture_requires_authentication(): void
    {
        $this->get('/capture/bulk')->assertRedirect('/login');
        $this->post('/capture/bulk/items')->assertRedirect('/login');
    }
}
