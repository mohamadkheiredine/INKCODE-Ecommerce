<?php

namespace App\Http\Requests\Shipping;

use App\Models\ShippingZone;
use App\Models\ShippingZoneLocation;
use App\Support\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A new Shipping zone: a unique name and the places it covers, each a whole country
 * (no city) or one city in a country. A place another zone already covers is refused,
 * so every address matches at most one zone.
 */
class StoreShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ShippingZone::class) ?? false;
    }

    /**
     * Country codes in capitals; a blank city means the whole country.
     */
    protected function prepareForValidation(): void
    {
        $locations = $this->input('locations');
        if (! is_array($locations)) {
            return;
        }

        $this->merge(['locations' => array_map(fn (mixed $location): mixed => is_array($location) ? [
            ...$location,
            'country_code' => Countries::normalize($location['country_code'] ?? null),
            'city' => is_string($location['city'] ?? null) && trim($location['city']) !== '' ? trim($location['city']) : null,
        ] : $location, $locations)]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('shipping_zones', 'name')->ignore($this->zone())],
            'locations' => ['required', 'array', 'min:1', 'max:200'],
            'locations.*' => ['array:country_code,city'],
            'locations.*.country_code' => ['required', 'string', Countries::rule()],
            'locations.*.city' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'locations' => __('shipping.attributes.locations'),
            'locations.*.country_code' => __('shipping.attributes.country'),
            'locations.*.city' => __('shipping.attributes.city'),
        ];
    }

    /**
     * Each place once, and only in one zone.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $seen = [];
            foreach ($this->values()['locations'] as $index => $location) {
                $key = $location['country_code'].'/'.mb_strtolower($location['city'] ?? '');
                $owner = $this->zoneAlreadyCovering($location);

                if (isset($seen[$key])) {
                    $validator->errors()->add("locations.$index", __('shipping.zones.location_repeated', ['place' => $this->placeName($location)]));
                } elseif ($owner !== null) {
                    $validator->errors()->add("locations.$index", __('shipping.zones.location_taken', ['place' => $this->placeName($location), 'zone' => $owner->name]));
                }
                $seen[$key] = true;
            }
        }];
    }

    /**
     * The Shipping zone being edited; none while creating one.
     */
    public function zone(): ?ShippingZone
    {
        $zone = $this->route('shipping_zone');

        return $zone instanceof ShippingZone ? $zone : null;
    }

    /**
     * @return array{name: string, locations: list<array{country_code: string, city: string|null}>}
     */
    public function values(): array
    {
        /** @var list<array{country_code: string, city?: string|null}> $locations */
        $locations = array_values((array) $this->input('locations'));

        return [
            'name' => $this->string('name')->trim()->toString(),
            'locations' => array_map(
                fn (array $location): array => ['country_code' => $location['country_code'], 'city' => $location['city'] ?? null],
                $locations,
            ),
        ];
    }

    /**
     * @param  array{country_code: string, city: string|null}  $location
     */
    private function zoneAlreadyCovering(array $location): ?ShippingZone
    {
        $zone = $this->zone();

        return ShippingZoneLocation::query()
            ->where('country_code', $location['country_code'])
            ->where('city_key', mb_strtolower($location['city'] ?? ''))
            ->when($zone !== null, fn ($query) => $query->where('shipping_zone_id', '!=', $zone?->id))
            ->first()
            ?->zone;
    }

    /**
     * @param  array{country_code: string, city: string|null}  $location
     */
    private function placeName(array $location): string
    {
        $country = Countries::name($location['country_code']);

        return $location['city'] === null ? $country : "{$location['city']}, $country";
    }
}
