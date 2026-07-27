<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
     * A minimal but valid-enough PDF that poppler can render.
     */
    private function pdfWithPages(int $count): UploadedFile
    {
        $kids = [];
        $body = '';

        for ($i = 0; $i < $count; $i++) {
            $object = 3 + $i;
            $kids[] = "{$object} 0 R";
            $body .= "{$object} 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 300] >> endobj\n";
        }

        $pdf = "%PDF-1.4\n".
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n".
            '2 0 obj << /Type /Pages /Kids ['.implode(' ', $kids)."] /Count {$count} >> endobj\n".
            $body.
            "trailer << /Root 1 0 R >>\n".
            "%%EOF\n";

        $path = sys_get_temp_dir().'/scan-'.uniqid().'.pdf';
        file_put_contents($path, $pdf);

        return new UploadedFile($path, 'scan.pdf', 'application/pdf', null, true);
    }

    private function twoPagePdf(): UploadedFile
    {
        return $this->pdfWithPages(2);
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

    public function test_missing_backs_are_paired_by_ai_page_classification(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => json_encode(['pages' => ['front', 'front', 'back']])]],
                ]],
            ]),
        ]);

        $this->actingAs($this->user)->post('/capture/bulk/pdf', [
            'pdf' => $this->pdfWithPages(3),
            'photos_per_item' => 2,
        ])->assertStatus(202);

        // Page 1 is a front whose back was never scanned: it becomes its own
        // item instead of stealing page 2 (the next card's front).
        $this->assertDatabaseCount('items', 2);
        $items = Item::orderBy('id')->get();
        $this->assertSame(1, $items[0]->images()->count());
        $this->assertSame('front', $items[0]->images()->first()->role);
        $this->assertSame(['front', 'back'], $items[1]->images()->orderBy('id')->pluck('role')->all());
    }

    public function test_classification_failure_falls_back_to_mechanical_pairs(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake(['api.openai.com/*' => Http::response('Server error', 500)]);

        $this->actingAs($this->user)->post('/capture/bulk/pdf', [
            'pdf' => $this->pdfWithPages(2),
            'photos_per_item' => 2,
        ])->assertStatus(202);

        $this->assertDatabaseCount('items', 1);
        $this->assertSame(2, Item::first()->images()->count());
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
