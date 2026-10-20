<?php

namespace App\Actions\Shipping;

use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\ShippingZoneLocation;

/**
 * What it costs to ship to an address: the Shipping zone that covers it and that zone's
 * active Shipping methods, cheapest first. A zone that names the address's city wins
 * over a zone that covers its whole country; an address no zone covers gets no methods.
 *
 * Order creation (#16) quotes the delivery address the same way and takes the chosen
 * method through {@see ShippingQuote::method()}.
 */
final class QuoteShipping
{
    public function handle(string $countryCode, string $city): ShippingQuote
    {
        $countryCode = strtoupper(trim($countryCode));
        $city = trim($city);

        $zone = ShippingZoneLocation::query()
            ->where('country_code', $countryCode)
            ->whereIn('city_key', [mb_strtolower($city), ''])
            // A city's key sorts after the whole country's '', so the city comes first.
            ->orderByDesc('city_key')
            ->first()
            ?->zone;

        return new ShippingQuote(
            countryCode: $countryCode,
            city: $city,
            zone: $zone,
            methods: $zone === null ? [] : $this->activeMethods($zone),
        );
    }

    /**
     * @return list<ShippingMethod>
     */
    private function activeMethods(ShippingZone $zone): array
    {
        return $zone->methods()->active()->orderBy('price')->orderBy('name')->get()->all();
    }
}
