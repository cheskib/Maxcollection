<?php

namespace Tests\Feature;

use App\Jobs\SplitGridJob;
use App\Models\Batch;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GridCaptureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    /** A JPEG of $width × $height with a distinct grey level per sixth. */
    private function gridJpeg(int $width = 600, int $height = 400): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 200, 200));

        ob_start();
        imagejpeg($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent('grid.jpg', $binary);
    }

    public function test_grid_upload_creates_a_converting_batch_and_queues_the_split(): void
    {
        Bus::fake();

        $response = $this->actingAs($this->user)->post('/capture/bulk/grid', [
            'photo' => $this->gridJpeg(),
            'cells' => 6,
        ]);

        $response->assertStatus(202);

        $batch = Batch::first();
        $this->assertSame('grid', $batch->source);
        $this->assertNull($batch->converted_at);

        Bus::assertDispatched(SplitGridJob::class, fn (SplitGridJob $job) => $job->cells === 6 && $job->batchId === $batch->id);
    }

    public function test_duplicate_grid_photo_is_refused(): void
    {
        Bus::fake();
        $photo = $this->gridJpeg();

        $this->actingAs($this->user)->post('/capture/bulk/grid', ['photo' => $photo, 'cells' => 4])->assertStatus(202);
        $this->actingAs($this->user)
            ->post('/capture/bulk/grid', ['photo' => $this->gridJpeg(), 'cells' => 4])
            ->assertStatus(409);
    }

    public function test_split_job_creates_one_item_per_cell_in_reading_order(): void
    {
        $batch = Batch::create(['user_id' => $this->user->id, 'source' => 'grid']);
        Storage::disk('local')->put('imports/front.jpg', $this->gridJpeg(600, 400)->getContent());

        (new SplitGridJob('imports/front.jpg', null, 6, $this->user->id, $batch->id))->handle();

        $items = Item::where('batch_id', $batch->id)->orderBy('id')->get();
        $this->assertCount(6, $items);

        foreach ($items as $index => $item) {
            $this->assertCount(1, $item->images);
            $image = $item->images->first();
            $this->assertSame('front', $image->role);
            $this->assertSame(sprintf('grid-cell-%d-front.jpg', $index + 1), $image->original_filename);

            // A 600×400 landscape photo of six boxes slices 2×3: 200×200 cells.
            $cell = imagecreatefromstring((string) Storage::disk('local')->get($image->path));
            $this->assertSame(200, imagesx($cell));
            $this->assertSame(200, imagesy($cell));
        }

        $this->assertNotNull($batch->fresh()->converted_at);
        // The upload is deleted once split; only the cell crops remain.
        Storage::disk('local')->assertMissing('imports/front.jpg');
    }

    public function test_split_job_pairs_backs_by_position(): void
    {
        $batch = Batch::create(['user_id' => $this->user->id, 'source' => 'grid']);
        Storage::disk('local')->put('imports/front.jpg', $this->gridJpeg()->getContent());
        Storage::disk('local')->put('imports/back.jpg', $this->gridJpeg()->getContent());

        (new SplitGridJob('imports/front.jpg', 'imports/back.jpg', 2, $this->user->id, $batch->id))->handle();

        $items = Item::where('batch_id', $batch->id)->orderBy('id')->get();
        $this->assertCount(2, $items);

        foreach ($items as $item) {
            $this->assertSame(['front', 'back'], $item->images()->orderBy('id')->pluck('role')->all());
        }
    }

    public function test_single_cell_grid_makes_one_item(): void
    {
        $batch = Batch::create(['user_id' => $this->user->id, 'source' => 'grid']);
        Storage::disk('local')->put('imports/front.jpg', $this->gridJpeg(300, 420)->getContent());

        (new SplitGridJob('imports/front.jpg', null, 1, $this->user->id, $batch->id))->handle();

        $this->assertSame(1, Item::where('batch_id', $batch->id)->count());
    }

    public function test_portrait_photo_of_six_slices_three_rows(): void
    {
        $batch = Batch::create(['user_id' => $this->user->id, 'source' => 'grid']);
        // 420×900 portrait: six portrait boxes lie 3 rows × 2 columns
        // (each cell 210×300 — the comic/card shape the splitter targets).
        Storage::disk('local')->put('imports/front.jpg', $this->gridJpeg(420, 900)->getContent());

        (new SplitGridJob('imports/front.jpg', null, 6, $this->user->id, $batch->id))->handle();

        $first = Item::where('batch_id', $batch->id)->orderBy('id')->first()->images->first();
        $cell = imagecreatefromstring((string) Storage::disk('local')->get($first->path));
        $this->assertSame(210, imagesx($cell));
        $this->assertSame(300, imagesy($cell));
        $this->assertSame(6, Item::where('batch_id', $batch->id)->count());
    }
}
