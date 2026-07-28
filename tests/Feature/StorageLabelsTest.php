<?php

namespace Tests\Feature;

use App\Models\Barcode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StorageLabelsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_bag_labels_are_registered_with_sequential_codes(): void
    {
        $response = $this->actingAs($this->user)->post('/storage/labels', ['type' => 'bag', 'count' => 3]);

        $this->assertSame(['BAG-000001', 'BAG-000002', 'BAG-000003'], Barcode::orderBy('id')->pluck('code')->all());

        // A second run continues the sequence — codes are never reissued.
        $this->actingAs($this->user)->post('/storage/labels', ['type' => 'bag', 'count' => 2]);
        $this->assertSame('BAG-000005', Barcode::orderBy('id')->get()->last()->code);

        $run = Barcode::first()->print_run;
        $response->assertRedirect(route('labels.print', ['run' => $run]));
    }

    public function test_divider_labels_carry_names_and_neutral_codes(): void
    {
        $this->actingAs($this->user)->post('/storage/labels', [
            'type' => 'divider',
            'names' => "Baseball 80s\n\nFootball Stars\n",
        ]);

        $barcodes = Barcode::orderBy('id')->get();
        $this->assertCount(2, $barcodes);
        $this->assertSame('DIV-000001', $barcodes[0]->code);
        $this->assertSame('Baseball 80s', $barcodes[0]->label);
        $this->assertSame('Football Stars', $barcodes[1]->label);
    }

    public function test_print_sheet_renders_a_barcode_per_label(): void
    {
        $this->actingAs($this->user)->post('/storage/labels', ['type' => 'box', 'count' => 2]);
        $run = Barcode::first()->print_run;

        $this->actingAs($this->user)
            ->get("/storage/labels/{$run}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('LabelPrint')
                ->has('labels', 2)
                ->where('labels.0.code', 'BOX-000001')
                ->where('labels.0.svg', fn ($svg) => str_contains((string) $svg, '<svg'))
            );
    }

    public function test_unknown_print_run_is_not_found(): void
    {
        $this->actingAs($this->user)->get('/storage/labels/not-a-run')->assertNotFound();
    }

    public function test_labels_require_authentication(): void
    {
        $this->get('/storage/labels')->assertRedirect('/login');
        $this->post('/storage/labels', ['type' => 'bag', 'count' => 1])->assertRedirect('/login');
    }
}
