<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
 * Seed the way a local or review environment does, with demo mode on or off.
 */
function seedStore(bool $demoMode): void
{
    config(['app.demo_mode' => $demoMode]);
    test()->seed(DatabaseSeeder::class);
}
