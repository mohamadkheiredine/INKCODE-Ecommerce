<?php

use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the MySQL `inkcode_testing` database (see
| phpunit.xml) so row locks and transactions behave like production.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create an active User holding exactly the given Role.
 *
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(Role $role, array $attributes = []): User
{
    return User::factory()->withRole($role)->create($attributes);
}

/**
 * Every Role that only looks at the catalog: everyone but Admin and Operations.
 *
 * @return list<Role>
 */
function rolesThatMayNotManageTheCatalog(): array
{
    return [Role::Inventory, Role::Sales, Role::Support, Role::Finance, Role::Viewer];
}

/**
 * The audit entries recorded for one record, oldest first. (The audit log's own API
 * arrives with the audit viewer, #26.)
 *
 * @return Collection<int, AuditEntry>
 */
function auditTrailOf(string $recordType, int $recordId): Collection
{
    return AuditEntry::query()
        ->where('record_type', $recordType)
        ->where('record_id', $recordId)
        ->orderBy('id')
        ->get();
}

/**
 * Seed the way a local or review environment does, with demo mode on or off.
 */
function seedStore(bool $demoMode): void
{
    config(['app.demo_mode' => $demoMode]);
    test()->seed(DatabaseSeeder::class);
}

/**
 * Sends the next request from one browser, holding only this session cookie (none for a
 * new browser). Tests share one app, so the guards and the in-memory session are reset
 * the way a real next request finds them.
 */
function asBrowser(string $session = ''): TestCase
{
    app('auth')->forgetGuards();
    app('session')->driver()->flush();

    return test()->withCredentials()->withCookie((string) config('session.cookie'), $session);
}

function signInFromNewBrowser(string $email, string $password): TestResponse
{
    return asBrowser()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
}

/**
 * The session a response started, to send on that browser's later requests.
 */
function sessionOf(TestResponse $response): string
{
    return $response->getCookie((string) config('session.cookie'))?->getValue()
        ?? throw new RuntimeException('The response started no session.');
}
