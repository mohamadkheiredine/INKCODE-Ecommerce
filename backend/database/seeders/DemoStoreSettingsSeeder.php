<?php

namespace Database\Seeders;

use App\Models\StoreSettings;
use App\Services\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The demo store's profile. The row is rebuilt from the table's defaults, so the
 * business settings are exactly the ones every new store starts with (USD, 5% VAT,
 * deduct when Shipped, 14-day Return window, threshold 5) and the demo Orders and
 * reports are computed with those. Running it again puts the demo settings back.
 */
class DemoStoreSettingsSeeder extends Seeder
{
    public function run(Settings $settings): void
    {
        DB::transaction(function (): void {
            StoreSettings::query()->whereKey(StoreSettings::ID)->delete();
            DB::table('store_settings')->insert([
                'id' => StoreSettings::ID,
                'name' => 'Inkcode Demo Store',
                'contact_email' => 'hello@inkcode-demo.test',
                'contact_phone' => '+961 1 555 0100',
                'address' => "Hamra Street 12\nBeirut, Lebanon",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $settings->forget();
    }
}
