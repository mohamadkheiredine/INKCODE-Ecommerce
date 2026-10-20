<?php

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
| The audit log (GET /audit-entries): every recorded change, read-only, for the Roles
| with audit.view.
*/

beforeEach(function () {
    $this->admin = userWithRole(Role::Admin, ['name' => 'Ahmed Admin']);
    $this->actingAs($this->admin);
});

/**
 * @return list<string>
 */
function listedLabels(string $query = ''): array
{
    return test()->getJson('/api/v1/audit-entries?'.$query)->assertOk()->json('data.*.record_label');
}

it('lists entries newest first with who did what to which record and the values before and after', function () {
    AuditEntry::factory()->for($this->admin)->create([
        'action' => AuditAction::Updated,
        'record_type' => 'brand',
        'record_id' => 7,
        'record_label' => 'Atlas Outdoor',
        'before' => ['name' => 'Atlas'],
        'after' => ['name' => 'Atlas Outdoor'],
        'created_at' => now()->subMinute(),
    ]);
    AuditEntry::factory()->create(['user_id' => null, 'record_label' => 'Newest', 'created_at' => now()]);

    $this->getJson('/api/v1/audit-entries')
        ->assertOk()
        ->assertJsonPath('data.*.record_label', ['Newest', 'Atlas Outdoor'])
        ->assertJsonPath('data.0.user', null)
        ->assertJsonPath('data.1', fn (array $entry): bool => $entry['action'] === 'updated'
            && $entry['record_type'] === 'brand'
            && $entry['record_id'] === 7
            && $entry['user'] === ['id' => $this->admin->id, 'name' => 'Ahmed Admin']
            && $entry['before'] === ['name' => 'Atlas']
            && $entry['after'] === ['name' => 'Atlas Outdoor']
            && $entry['created_at'] !== '');
});

it('loads every entry\'s User in one query, however many Users wrote them', function () {
    $queriesFor = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/audit-entries')->assertOk();

        return count(DB::getQueryLog());
    };

    AuditEntry::factory()->create();
    $queriesFor(); // warms the session and permission caches, which the first request fills
    $oneEntry = $queriesFor();
    AuditEntry::factory()->count(5)->create();

    expect($queriesFor())->toBe($oneEntry);
});

it('searches the record label', function () {
    AuditEntry::factory()->create(['record_label' => 'Summer Shirts']);
    AuditEntry::factory()->create(['record_label' => 'Atlas Outdoor']);

    expect(listedLabels('search=shirt'))->toBe(['Summer Shirts']);
});

it('filters by User, action, record type and record', function () {
    $sara = User::factory()->create();
    AuditEntry::factory()->for($sara)->create(['record_label' => 'By Sara', 'action' => AuditAction::Created, 'record_type' => 'customer']);
    AuditEntry::factory()->create(['record_label' => 'Deleted brand', 'action' => AuditAction::Deleted, 'record_type' => 'brand', 'record_id' => 4]);
    AuditEntry::factory()->create(['record_label' => 'Other brand', 'record_type' => 'brand', 'record_id' => 5]);
    AuditEntry::factory()->create(['record_label' => 'A category', 'record_type' => 'category', 'record_id' => 4]);

    expect(listedLabels("filter[user]={$sara->id}"))->toBe(['By Sara'])
        ->and(listedLabels('filter[action]=deleted'))->toBe(['Deleted brand'])
        ->and(listedLabels('filter[record_type]=category'))->toBe(['A category'])
        ->and(listedLabels('filter[record_type]=brand&filter[record]=4'))->toBe(['Deleted brand']);
});

it('filters by a date range, both days included', function () {
    foreach (['2026-10-01 23:59:59' => 'Before', '2026-10-02 00:00:00' => 'First day', '2026-10-03 23:59:59' => 'Last day', '2026-10-04 00:00:00' => 'After'] as $time => $label) {
        AuditEntry::factory()->create(['record_label' => $label, 'created_at' => $time]);
    }

    expect(listedLabels('filter[from]=2026-10-02&filter[to]=2026-10-03'))->toBe(['Last day', 'First day'])
        ->and(listedLabels('filter[from]=2026-10-04'))->toBe(['After'])
        ->and(listedLabels('filter[to]=2026-10-01'))->toBe(['Before']);
});

it('sorts oldest first on request and pages through the entries', function () {
    foreach (['First', 'Second', 'Third'] as $minutesAgo => $label) {
        AuditEntry::factory()->create(['record_label' => $label, 'created_at' => now()->subMinutes(3 - $minutesAgo)]);
    }

    expect(listedLabels('sort=created_at'))->toBe(['First', 'Second', 'Third']);
    $this->getJson('/api/v1/audit-entries?per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('data.*.record_label', ['First'])
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2);
});

it('refuses unknown filters and sorts, and filters that make no sense', function (string $query, string $field) {
    $this->getJson('/api/v1/audit-entries?'.$query)->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    'unknown filter' => ['filter[colour]=red', 'filter'],
    'unknown sort' => ['sort=record_label', 'sort'],
    'unknown action' => ['filter[action]=renamed', 'filter.action'],
    'malformed date' => ['filter[from]=02/10/2026', 'filter.from'],
    'range ending before it starts' => ['filter[from]=2026-10-05&filter[to]=2026-10-04', 'filter.to'],
    'record without its type' => ['filter[record]=4', 'filter.record_type'],
]);

it('offers the Users who appear in the log, by name, as the User filter', function () {
    AuditEntry::factory()->for(User::factory()->create(['name' => 'Zeina']))->create();
    AuditEntry::factory()->for($this->admin)->count(2)->create();
    User::factory()->create(['name' => 'Never changed anything']);

    $this->getJson('/api/v1/audit-entries/users')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => $this->admin->id, 'name' => 'Ahmed Admin'],
            ['id' => User::query()->where('name', 'Zeina')->value('id'), 'name' => 'Zeina'],
        ]]);
});

it('shows changes made through earlier modules, as they were made', function () {
    $brand = Brand::factory()->create(['name' => 'Atlas', 'slug' => 'atlas']);
    $this->putJson("/api/v1/brands/{$brand->id}", ['name' => 'Atlas Outdoor', 'slug' => 'atlas'])->assertOk();

    $this->getJson("/api/v1/audit-entries?filter[record_type]=brand&filter[record]={$brand->id}")
        ->assertOk()
        ->assertJsonPath('data.0.action', 'updated')
        ->assertJsonPath('data.0.user.name', 'Ahmed Admin')
        ->assertJsonPath('data.0.before', ['name' => 'Atlas'])
        ->assertJsonPath('data.0.after', ['name' => 'Atlas Outdoor']);
});

it('keeps the log read-only: it can only be read', function () {
    $methods = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => str_contains($route->uri(), 'audit-entries'))
        ->flatMap(fn (RouteDefinition $route): array => $route->methods())
        ->unique()->sort()->values()->all();

    expect($methods)->toBe(['GET', 'HEAD']);
    $this->postJson('/api/v1/audit-entries', [])->assertMethodNotAllowed();
});

it('shows the log only to Roles with audit.view', function (Role $role) {
    $this->actingAs(userWithRole($role));

    $this->getJson('/api/v1/audit-entries')->assertForbidden();
    $this->getJson('/api/v1/audit-entries/users')->assertForbidden();
})->with(fn (): array => array_values(array_filter(
    Role::cases(),
    fn (Role $role): bool => ! in_array(Permission::AuditView, $role->permissions(), true),
)));

it('asks guests to sign in', function () {
    auth()->guard('web')->logout();

    $this->getJson('/api/v1/audit-entries')->assertUnauthorized();
});

it('gives the demo store a history that matches its records, written once', function () {
    seedStore(demoMode: true);
    seedStore(demoMode: true);

    $entries = AuditEntry::query()->with('user')->get();
    $brand = Brand::query()->orderBy('id')->firstOrFail();

    expect($entries)->not->toBeEmpty()
        ->and($entries->every(fn (AuditEntry $entry): bool => str_ends_with((string) $entry->user?->email, '@inkcode.test')))->toBeTrue()
        ->and($entries->pluck('record_type')->unique()->values()->all())->toContain('category', 'brand', 'customer', 'store_settings')
        ->and(auditTrailOf('brand', $brand->id)->last()?->after)->toBe(['name' => $brand->name]);
});
