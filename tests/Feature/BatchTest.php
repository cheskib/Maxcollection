<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    public function test_first_bulk_item_creates_a_batch_and_later_items_join_it(): void
    {
        $first = $this->actingAs($this->user)->post('/capture/bulk/items', [
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertCreated()->json();

        $this->assertDatabaseCount('batches', 1);
        $this->assertSame($first['batchId'], Item::first()->batch_id);

        $this->actingAs($this->user)->post('/capture/bulk/items', [
            'photos' => [UploadedFile::fake()->image('b.jpg')],
            'batch_id' => $first['batchId'],
        ])->assertCreated();

        $this->assertDatabaseCount('batches', 1);
        $this->assertSame(2, Batch::first()->items()->count());
    }

    public function test_pdf_import_creates_a_labeled_batch(): void
    {
        $pdf = "%PDF-1.4\n".
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n".
            "2 0 obj << /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >> endobj\n".
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 300] >> endobj\n".
            "4 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 300] >> endobj\n".
            "trailer << /Root 1 0 R >>\n%%EOF\n";
        $path = sys_get_temp_dir().'/batch-'.uniqid().'.pdf';
        file_put_contents($path, $pdf);

        $this->actingAs($this->user)->post('/capture/bulk/pdf', [
            'pdf' => new UploadedFile($path, 'july-scans.pdf', 'application/pdf', null, true),
            'photos_per_item' => 2,
        ])->assertStatus(202);

        $batch = Batch::first();
        $this->assertSame('july-scans.pdf', $batch->label);
        $this->assertSame('pdf', $batch->source);
        $this->assertSame(1, $batch->items()->count());
    }

    public function test_single_captures_stay_out_of_batches(): void
    {
        $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('solo.jpg'),
        ]);

        $this->assertDatabaseCount('batches', 0);
        $this->assertNull(Item::first()->batch_id);
    }

    public function test_batches_page_lists_batches_with_counts(): void
    {
        $batch = Batch::create(['user_id' => $this->user->id, 'source' => 'bulk']);
        Item::create(['user_id' => $this->user->id, 'batch_id' => $batch->id, 'status' => Item::STATUS_PROCESSED]);
        Item::create(['user_id' => $this->user->id, 'batch_id' => $batch->id, 'status' => Item::STATUS_NEEDS_REVIEW]);
        Item::create(['user_id' => $this->user->id]); // unbatched

        $this->actingAs($this->user)
            ->get('/batches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Batches')
                ->has('batches', 1)
                ->where('batches.0.itemCount', 2)
                ->where('batches.0.processedCount', 1)
                ->where('batches.0.needsReviewCount', 1)
                ->where('unbatchedCount', 1)
            );
    }

    public function test_batch_detail_lists_only_that_batchs_items(): void
    {
        $batch = Batch::create(['user_id' => $this->user->id, 'source' => 'bulk']);
        $inside = Item::create(['user_id' => $this->user->id, 'batch_id' => $batch->id, 'status' => Item::STATUS_PROCESSED]);
        Item::create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->get("/batches/{$batch->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('BatchDetail')
                ->has('items', 1)
                ->where('items.0.id', $inside->id)
                ->where('items.0.status', 'Processed')
            );
    }

    public function test_batches_require_authentication(): void
    {
        $this->get('/batches')->assertRedirect('/login');
    }
}
