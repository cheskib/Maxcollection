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
