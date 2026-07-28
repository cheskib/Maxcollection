<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_contains_every_card_and_its_fields(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $collection = Collection::create(['user_id' => $user->id, 'name' => "Cheski's"]);

        $item = Item::create(['user_id' => $user->id, 'status' => Item::STATUS_PROCESSED, 'collection_id' => $collection->id]);
        $item->metadata()->create([
            'category' => 'sports_card',
            'confidence' => 95,
            'player_name' => 'Mickey Mantle',
            'year' => '1952',
            'manufacturer' => 'Topps',
            'value_from' => 1000,
            'value_to' => 2000,
            'key_card' => true,
        ]);
        Item::create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Player Name', $csv);
        $this->assertStringContainsString('Mickey Mantle', $csv);
        $this->assertStringContainsString("Cheski's", $csv);
        $this->assertStringContainsString('Yes', $csv);
        // Both items exported: header + 2 rows.
        $this->assertSame(3, substr_count(trim($csv), "\n") + 1);
    }

    public function test_export_requires_authentication(): void
    {
        $this->get('/export')->assertRedirect('/login');
    }
}
