<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    /**
     * A minimal but valid-enough two-page PDF that poppler can render.
     */
    private function twoPagePdf(): UploadedFile
    {
        $pdf = "%PDF-1.4\n".
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n".
            "2 0 obj << /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >> endobj\n".
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 300] >> endobj\n".
            "4 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 300] >> endobj\n".
            "trailer << /Root 1 0 R >>\n".
            "%%EOF\n";

        $path = sys_get_temp_dir().'/scan-'.uniqid().'.pdf';
        file_put_contents($path, $pdf);

        return new UploadedFile($path, 'scan.pdf', 'application/pdf', null, true);
    }

    public function test_pdf_pages_become_front_back_items(): void
    {
        $response = $this->actingAs($this->user)->post('/capture/bulk/pdf', [
            'pdf' => $this->twoPagePdf(),
            'photos_per_item' => 2,
        ]);

        $response->assertStatus(202);

        // Queue runs synchronously in tests, so the import is already done.
        $this->assertDatabaseCount('items', 1);
        $item = Item::first();
        $this->assertSame(2, $item->images()->count());
        foreach ($item->images as $image) {
            Storage::disk('local')->assertExists($image->path);
        }
    }

    public function test_single_sided_scan_creates_one_item_per_page(): void
    {
        $this->actingAs($this->user)->post('/capture/bulk/pdf', [
            'pdf' => $this->twoPagePdf(),
            'photos_per_item' => 1,
        ])->assertStatus(202);

        $this->assertDatabaseCount('items', 2);
        $this->assertSame(1, Item::first()->images()->count());
    }

    public function test_the_uploaded_pdf_is_cleaned_up_after_import(): void
    {
        $this->actingAs($this->user)->post('/capture/bulk/pdf', [
            'pdf' => $this->twoPagePdf(),
            'photos_per_item' => 2,
        ]);

        $this->assertSame([], Storage::disk('local')->files('imports'));
    }

    public function test_non_pdf_files_are_rejected(): void
    {
        $response = $this->actingAs($this->user)->postJson('/capture/bulk/pdf', [
            'pdf' => UploadedFile::fake()->image('photo.jpg'),
            'photos_per_item' => 2,
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('items', 0);
    }

    public function test_pdf_import_requires_authentication(): void
    {
        $this->post('/capture/bulk/pdf')->assertRedirect('/login');
    }
}
