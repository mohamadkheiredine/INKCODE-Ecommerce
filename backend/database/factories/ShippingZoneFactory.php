<?php

namespace Database\Factories;

use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShippingZone>
 */
class ShippingZoneFactory extends Factory
{
    /**
     * A zone with a unique name that covers nothing until given places.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => Str::title(fake()->unique()->words(2, true))];
    }

    /**
     * Covers these places: a country ("LB") or a city in a country ("LB/Beirut").
     */
    public function covering(string ...$places): static
    {
        return $this->afterCreating(function (ShippingZone $zone) use ($places): void {
            foreach ($places as $place) {
                [$countryCode, $city] = array_pad(explode('/', $place, 2), 2, null);
                $zone->locations()->create(['country_code' => $countryCode, 'city' => $city]);
            }
        });
    }
}
