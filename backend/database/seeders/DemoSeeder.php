<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The demo store for local and review environments. Later tickets add the
 * catalog, stock, Customers and Orders here.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoStoreSettingsSeeder::class,
            DemoUsersSeeder::class,
            DemoCatalogSeeder::class,
            DemoCustomersSeeder::class,
            DemoShippingSeeder::class,
        ]);
    }
}
