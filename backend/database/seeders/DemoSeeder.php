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
            // After the seeders above: it describes the records they created.
            DemoAuditSeeder::class,
            // After the audit history: it records its own entries through the real actions.
            DemoInventorySeeder::class,
            // Orders need the stocked Products, the Customers and the Shipping methods.
            DemoOrdersSeeder::class,
            // Payment ledgers for those Orders.
            DemoPaymentsSeeder::class,
        ]);
    }
}
