<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * The demo store's Shipping zones, Shipping methods and Carriers: Beirut gets its own
 * cheaper, faster methods, the rest of Lebanon and the Gulf have theirs, and anywhere
 * else is not shipped to. Idempotent: running it again restores these records by name.
 */
class DemoShippingSeeder extends Seeder
{
    /**
     * @var array<string, array{locations: list<array{country_code: string, city: string|null}>, methods: array<string, array{string, int, int}>}>
     */
    private const ZONES = [
        'Beirut' => [
            'locations' => [['country_code' => 'LB', 'city' => 'Beirut']],
            'methods' => ['Same-day' => ['6.00', 0, 0], 'Standard' => ['3.00', 1, 2]],
        ],
        'Lebanon' => [
            'locations' => [['country_code' => 'LB', 'city' => null]],
            'methods' => ['Standard' => ['5.00', 2, 4], 'Express' => ['10.00', 1, 2]],
        ],
        'Gulf' => [
            'locations' => [
                ['country_code' => 'AE', 'city' => null], ['country_code' => 'SA', 'city' => null],
                ['country_code' => 'KW', 'city' => null], ['country_code' => 'QA', 'city' => null],
                ['country_code' => 'BH', 'city' => null], ['country_code' => 'OM', 'city' => null],
            ],
            'methods' => ['Standard' => ['15.00', 4, 7], 'Express' => ['30.00', 2, 3]],
        ],
    ];

    /** @var array<string, string|null> */
    private const CARRIERS = [
        'Aramex' => 'https://www.aramex.com/track/results?ShipmentNumber={tracking_number}',
        'DHL Express' => 'https://www.dhl.com/global-en/home/tracking/tracking-express.html?tracking-id={tracking_number}',
        'Local courier' => null,
    ];

    public function run(): void
    {
        foreach (self::ZONES as $name => $zone) {
            $record = ShippingZone::query()->updateOrCreate(['name' => $name]);
            $record->locations()->delete();
            $record->locations()->createMany($zone['locations']);

            foreach ($zone['methods'] as $methodName => [$price, $minDays, $maxDays]) {
                $record->methods()->updateOrCreate(
                    ['name' => $methodName],
                    ['price' => $price, 'min_delivery_days' => $minDays, 'max_delivery_days' => $maxDays, 'is_active' => true],
                );
            }
        }

        foreach (self::CARRIERS as $name => $template) {
            Carrier::query()->updateOrCreate(['name' => $name], ['tracking_url_template' => $template]);
        }
    }
}
