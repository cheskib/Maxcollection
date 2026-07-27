<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Image;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaptureWizardTest extends TestCase
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

    public function test_wizard_photos_record_front_and_back_roles(): void
    {
        $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('a.jpg'),
            'role' => 'front',
        ]);
        $item = Item::first();

        $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('b.jpg'),
            'item_id' => $item->id,
            'role' => 'back',
        ]);

        $this->assertSame(['front', 'back'], $item->images()->orderBy('id')->pluck('role')->all());
    }

    public function test_autograph_answer_is_saved_and_survives_processing(): void
    {
        $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('a.jpg'),
            'role' => 'front',
        ]);
        $item = Item::first();

        $this->actingAs($this->user)
            ->post("/items/{$item->id}/autograph", ['authentic' => true])
            ->assertRedirect("/items/{$item->id}");

        $this->assertSame('Yes', $item->fresh()->metadata->autograph);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'category' => 'sports_card',
                            'confidence' => 95,
                            'fields' => ['player_name' => 'Signed Player', 'autograph' => 'No'],
                        ]),
                    ]],
                ]],
            ]),
        ]);

        $this->actingAs($this->user)->post('/process');

        // The AI said No, but the owner said Yes at capture; the owner wins.
        $this->assertSame('Yes', $item->fresh()->metadata->autograph);
    }

    public function test_ai_fills_roles_only_for_unlabeled_photos(): void
    {
        $this->actingAs($this->user)->post('/capture/images', [
            'photo' => UploadedFile::fake()->image('front.jpg'),
            'role' => 'front',
        ]);
        $item = Item::first();
        $item->images()->create([
            'path' => 'original/'.$item->id.'/scan.jpg',
            'original_filename' => 'scan.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10,
        ]);
        Storage::disk('local')->put('original/'.$item->id.'/scan.jpg', UploadedFile::fake()->image('scan.jpg')->getContent());

        Http::fake([
            'api.openai.com/*' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'category' => 'sports_card',
                            'confidence' => 95,
                            'fields' => ['player_name' => 'Role Player'],
                            'roles' => ['back', 'back'],
                        ]),
                    ]],
                ]],
            ]),
        ]);

        $this->actingAs($this->user)->post('/process');

        // First photo keeps the user's 'front' label; the unlabeled scan gets the AI's.
        $this->assertSame(['front', 'back'], $item->images()->orderBy('id')->pluck('role')->all());
    }

    public function test_batch_workspace_status_and_scoped_processing(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode(['category' => 'coin', 'confidence' => 90, 'fields' => ['country' => 'USA', 'denomination' => 'Dime']]),
                    ]],
                ]],
            ]),
        ]);

        $inBatch = Batch::create(['user_id' => $this->user->id, 'source' => 'bulk']);
        $itemIn = Item::create(['user_id' => $this->user->id, 'batch_id' => $inBatch->id]);
        $this->attachPhoto($itemIn);
        $itemOut = Item::create(['user_id' => $this->user->id]);
        $this->attachPhoto($itemOut);

        $status = $this->actingAs($this->user)
            ->get("/capture/bulk/status?ids={$inBatch->id}")
            ->assertOk()
            ->json();
        $this->assertSame(1, $status['batches'][0]['captured']);

        $this->actingAs($this->user)
            ->postJson('/capture/bulk/process', ['batch_ids' => [$inBatch->id]])
            ->assertOk()
            ->assertJson(['queued' => 1]);

        $this->assertSame(Item::STATUS_PROCESSED, $itemIn->fresh()->status);
        $this->assertSame(Item::STATUS_CAPTURED, $itemOut->fresh()->status);
    }

    private function attachPhoto(Item $item): void
    {
        $path = 'original/'.$item->id.'/p.jpg';
        Storage::disk('local')->put($path, UploadedFile::fake()->image('p.jpg')->getContent());
        $item->images()->create([
            'path' => $path,
            'original_filename' => 'p.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10,
        ]);
    }
}
