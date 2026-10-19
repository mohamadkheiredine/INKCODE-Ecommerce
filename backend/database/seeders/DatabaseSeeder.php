<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data always; the demo store only when demo mode is on.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (config('app.demo_mode')) {
            $this->call(DemoSeeder::class);
        }
    }
}
