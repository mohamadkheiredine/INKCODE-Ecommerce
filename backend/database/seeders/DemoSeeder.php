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
            DemoCouponsSeeder::class,
            // Orders need the stocked Products, the Customers, the Shipping methods and Coupons.
            DemoOrdersSeeder::class,
            // Payment ledgers for those Orders.
            DemoPaymentsSeeder::class,
            // Returns on the Delivered Orders, after their payments (for the Refunds).
            DemoReturnsSeeder::class,
            // Earlier Delivered Orders, so every Segment has Customers.
            DemoCustomerHistorySeeder::class,
        ]);
    }
}
