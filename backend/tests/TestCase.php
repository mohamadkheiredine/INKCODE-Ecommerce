<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles and permissions are reference data every feature relies on, so they are
     * seeded once when RefreshDatabase migrates the test database.
     */
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    /**
     * Tests that truncate (tests/Concurrency) keep the rows migrations insert: the one
     * store settings row.
     *
     * @var list<string>
     */
    protected array $exceptTables = ['store_settings'];

    protected function setUp(): void
    {
        parent::setUp();

        // Requests come from the SPA's origin, so Sanctum treats them as first-party
        // and starts a session, exactly as it does for the real frontend.
        $this->withHeader('Origin', 'http://localhost:5173');
    }
}
