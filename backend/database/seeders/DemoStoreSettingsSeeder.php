<?php

namespace Database\Seeders;

use App\Models\StoreSettings;
use App\Services\Settings;
use Illuminate\Database\Seeder;

/**
 * The demo store's profile. The business settings keep the defaults the migration gives
 * every store (USD, 5% VAT, deduct when Shipped, 14-day Return window, threshold 5),
 * so the demo Orders and reports are computed with exactly those.
 */
class DemoStoreSettingsSeeder extends Seeder
{
    public function run(Settings $settings): void
    {
        StoreSettings::query()->whereKey(StoreSettings::ID)->update([
            'name' => 'Inkcode Demo Store',
            'contact_email' => 'hello@inkcode-demo.test',
            'contact_phone' => '+961 1 555 0100',
            'address' => "Hamra Street 12\nBeirut, Lebanon",
        ]);

        $settings->forget();
    }
}
