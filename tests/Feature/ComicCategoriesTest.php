<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Metadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ComicCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function comic(string $publisher, string $year, array $extra = []): Item
    {
        $item = Item::create(['user_id' => $this->user->id, 'status' => Item::STATUS_PROCESSED]);
        $item->metadata()->create([
            'category' => 'comic_book', 'title' => 'Test Title',
            'publisher' => $publisher, 'year' => $year, ...$extra,
        ]);

        return $item;
    }

    public function test_age_derives_from_year_at_the_approved_boundaries(): void
    {
        $this->assertSame('Golden Age', Metadata::comicAge('1938'));
        $this->assertSame('Golden Age', Metadata::comicAge('1955'));
        $this->assertSame('Silver Age', Metadata::comicAge('1956'));
        $this->assertSame('Silver Age', Metadata::comicAge('1969'));
        $this->assertSame('Bronze Age', Metadata::comicAge('1970'));
        $this->assertSame('Bronze Age', Metadata::comicAge('1984'));
        $this->assertSame('Copper Age', Metadata::comicAge('1985'));
        $this->assertSame('Copper Age', Metadata::comicAge('1991'));
        $this->assertSame('Modern Age', Metadata::comicAge('1992'));
        $this->assertSame('Modern Age', Metadata::comicAge('2026'));

        $this->assertNull(Metadata::comicAge(null));
        $this->assertNull(Metadata::comicAge('1937'));
        $this->assertNull(Metadata::comicAge('unknown'));
    }

    public function test_summary_drills_publisher_then_age(): void
    {
        $this->comic('Marvel', '1963');
        $this->comic('Marvel', '1968');
        $this->comic('Marvel', '1975');
        $this->comic('DC', '1940');

        // Level 2: comics group by publisher.
        $this->actingAs($this->user)
            ->get('/items/summary?category=comic_book')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groupField', 'publisher')
                ->where('groups.0.value', 'Marvel')
                ->where('groups.0.count', 3)
            );

        // Level 3: a chosen publisher breaks down by age, oldest era first.
        $this->actingAs($this->user)
            ->get('/items/summary?category=comic_book&publisher=Marvel')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('publisher', 'Marvel')
                ->where('total', 3)
                ->where('ages.0.value', 'Silver Age')
                ->where('ages.0.count', 2)
                ->where('ages.1.value', 'Bronze Age')
                ->where('ages.1.count', 1)
            );
    }

    public function test_items_list_filters_by_comic_age(): void
    {
        $silver = $this->comic('Marvel', '1963');
        $this->comic('Marvel', '1975');

        $this->actingAs($this->user)
            ->get('/items?category=comic_book&publisher=Marvel&age=Silver+Age')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('page.total', 1)
                ->where('items.0.id', $silver->id)
                ->where('filters.age', 'Silver Age')
            );

        // An unknown age label filters nothing rather than erroring.
        $this->actingAs($this->user)
            ->get('/items?category=comic_book&age=Stone+Age')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('page.total', 2));
    }

    public function test_format_and_genre_save_from_the_edit_form(): void
    {
        $item = $this->comic('Marvel', '1963');

        $this->actingAs($this->user)->put("/items/{$item->id}/metadata", [
            'category' => 'comic_book',
            'title' => 'Test Title',
            'publisher' => 'Marvel',
            'year' => '1963',
            'format' => 'Annual',
            'genre' => 'Superhero',
        ])->assertRedirect();

        $metadata = $item->fresh()->metadata;
        $this->assertSame('Annual', $metadata->format);
        $this->assertSame('Superhero', $metadata->genre);
    }
}
