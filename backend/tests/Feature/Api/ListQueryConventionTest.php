<?php

use App\Enums\Role;
use App\Models\Category;

/*
| The one list convention every list endpoint shares (App\Http\Requests\ListRequest),
| checked through the Categories list.
*/

beforeEach(function () {
    $this->actingAs(userWithRole(Role::Viewer));
});

/**
 * @return list<string>
 */
function listedNames(string $query): array
{
    return test()->getJson('/api/v1/categories?'.$query)->assertOk()->json('data.*.name');
}

it('finds rows whose name or slug contains the search term', function () {
    Category::factory()->create(['name' => 'Summer Shirts', 'slug' => 'summer-shirts']);
    Category::factory()->create(['name' => 'Shoes', 'slug' => 'footwear']);
    Category::factory()->create(['name' => 'Bags', 'slug' => 'bags']);

    expect(listedNames('search=shirt'))->toBe(['Summer Shirts'])
        ->and(listedNames('search=FOOT'))->toBe(['Shoes']);
});

it('treats % and _ in the search term as plain characters', function () {
    Category::factory()->create(['name' => '100% Cotton']);
    Category::factory()->create(['name' => '1000 Threads']);

    expect(listedNames('search=100%25'))->toBe(['100% Cotton'])
        ->and(listedNames('search=_'))->toBe([]);
});

it('sorts ascending by a field, and descending with a leading minus', function () {
    Category::factory()->create(['name' => 'Bags']);
    Category::factory()->create(['name' => 'Accessories']);
    Category::factory()->create(['name' => 'Coats']);

    expect(listedNames('sort=name'))->toBe(['Accessories', 'Bags', 'Coats'])
        ->and(listedNames('sort=-name'))->toBe(['Coats', 'Bags', 'Accessories']);
});

it('breaks ties between equal sort values by creation, in the same direction', function () {
    // None has children, so every row ties; children_count has no index to lend an order.
    $created = Category::factory()->count(5)->create();

    $pages = collect([1, 2, 3])->flatMap(
        fn (int $page) => $this->getJson("/api/v1/categories?sort=-children_count&per_page=2&page=$page")->json('data.*.id'),
    );

    expect($pages->all())->toBe($created->pluck('id')->reverse()->values()->all());
});

it('applies the filters a list declares', function () {
    $clothing = Category::factory()->create(['name' => 'Clothing']);
    Category::factory()->childOf($clothing)->create(['name' => 'Shirts']);
    $bags = Category::factory()->create(['name' => 'Bags']);
    Category::factory()->childOf($bags)->create(['name' => 'Backpacks']);

    expect(listedNames('filter[level]=top'))->toBe(['Bags', 'Clothing'])
        ->and(listedNames('filter[level]=child'))->toBe(['Backpacks', 'Shirts'])
        ->and(listedNames("filter[parent_id]={$clothing->id}"))->toBe(['Shirts'])
        ->and(listedNames('filter[level]='))->toBe(['Backpacks', 'Bags', 'Clothing', 'Shirts']);
});

it('keeps the search, filters and sort on every page link', function () {
    Category::factory()->count(3)->create(['parent_id' => null]);

    $links = $this->getJson('/api/v1/categories?filter[level]=top&sort=-name&per_page=1&page=2')
        ->assertOk()
        ->json('links');

    expect(urldecode($links['next']))->toContain('filter[level]=top')->toContain('sort=-name')->toContain('per_page=1')->toContain('page=3')
        ->and(urldecode($links['prev']))->toContain('page=1');
});

it('refuses a sort the list does not offer, naming the ones it does', function () {
    $this->getJson('/api/v1/categories?sort=-password')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['sort' => 'This list can only be sorted by: name, children_count, created_at.']);
});

it('refuses a filter the list does not offer, naming the ones it does', function () {
    $this->getJson('/api/v1/categories?filter[secret]=1')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['filter' => 'This list can only be filtered by: level, parent_id.']);
});

it('validates each filter value', function () {
    $this->getJson('/api/v1/categories?filter[level]=grandchild')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['filter.level']);
});

it('refuses a page size outside 1 to 100', function (string $perPage) {
    $this->getJson('/api/v1/categories?per_page='.$perPage)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page' => 'The per page field must be between 1 and 100.']);
})->with(['0', '101']);

it('answers in Arabic when the interface is in Arabic', function () {
    $this->getJson('/api/v1/categories?sort=colour', ['Accept-Language' => 'ar'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort' => 'لا يمكن ترتيب هذه القائمة إلا حسب: name, children_count, created_at.']);
});
