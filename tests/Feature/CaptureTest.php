<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaptureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    public function test_capture_screen_renders(): void
    {
        $this->actingAs($this->user)->get('/capture')->assertOk();
    }

    public function test_first_photo_creates_the_item(): void
    {
        $response = $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('card-front.jpg'),
        ]);

        $this->assertDatabaseCount('items', 1);
        $this->assertDatabaseCount('images', 1);

        $item = Item::first();
        $this->assertSame($this->user->id, $item->user_id);

        $image = Image::first();
        $this->assertSame('card-front.jpg', $image->original_filename);
        $this->assertStringStartsWith("original/{$item->id}/", $image->path);
        Storage::disk('local')->assertExists($image->path);

        $response->assertRedirect("/capture/{$item->id}");
    }

    public function test_additional_photos_attach_to_the_same_item(): void
    {
        $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('front.jpg'),
        ]);
        $item = Item::first();

        $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('back.jpg'),
            'item_id' => $item->id,
        ]);

        $this->assertDatabaseCount('items', 1);
        $this->assertSame(2, $item->images()->count());
    }

    public function test_deleting_one_of_several_photos_keeps_the_item(): void
    {
        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('a.jpg')]);
        $item = Item::first();
        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('b.jpg'), 'item_id' => $item->id]);

        $first = $item->images()->orderBy('id')->first();
        $path = $first->path;

        $response = $this->actingAs($this->user)->delete("/images/{$first->id}");

        $response->assertRedirect("/capture/{$item->id}");
        $this->assertDatabaseCount('items', 1);
        $this->assertDatabaseCount('images', 1);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_the_last_photo_deletes_the_item(): void
    {
        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('only.jpg')]);
        $item = Item::first();
        $image = $item->images()->first();

        $response = $this->actingAs($this->user)->delete("/images/{$image->id}");

        $response->assertRedirect('/capture');
        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('images', 0);
        Storage::disk('local')->assertMissing($image->path);
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $response = $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('items', 0);
    }

    public function test_original_photo_can_be_viewed(): void
    {
        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('view.jpg')]);
        $image = Image::first();

        $this->actingAs($this->user)->get("/images/{$image->id}")->assertOk();
    }

    public function test_an_image_can_be_rotated_in_quarter_turns(): void
    {
        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('r.jpg')]);
        $image = Image::first();

        foreach ([90, 180, 270, 0] as $expected) {
            $this->actingAs($this->user)->post("/images/{$image->id}/rotate");
            $this->assertSame($expected, $image->fresh()->rotation);
        }
    }

    public function test_rotated_image_still_streams(): void
    {
        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('s.jpg')]);
        $image = Image::first();

        $this->actingAs($this->user)->post("/images/{$image->id}/rotate");

        $this->actingAs($this->user)
            ->get("/images/{$image->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_capture_requires_authentication(): void
    {
        $this->get('/capture')->assertRedirect('/login');
        $this->post('/capture/images')->assertRedirect('/login');
    }

    public function test_home_stats_count_captures(): void
    {
        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('one.jpg')]);
        $item = Item::first();
        $this->actingAs($this->user)->post('/capture/images', ['photo' => UploadedFile::fake()->image('two.jpg'), 'item_id' => $item->id]);

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('stats.itemsCaptured', 1)
                ->where('stats.picturesUploaded', 2)
            );
    }
}
