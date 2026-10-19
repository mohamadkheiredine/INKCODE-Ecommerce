<?php

namespace App\Actions\Shipping;

use App\Exceptions\BusinessRuleViolation;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Support\Countries;

/**
 * The answer to {@see QuoteShipping}: the zone covering an address (null when none does)
 * and the Shipping methods offered there.
 */
final readonly class ShippingQuote
{
    /**
     * @param  list<ShippingMethod>  $methods  active only, cheapest first
     */
    public function __construct(
        public string $countryCode,
        public string $city,
        public ?ShippingZone $zone,
        public array $methods,
    ) {}

    /**
     * Why nothing is offered, or null when methods are.
     */
    public function unavailableReason(): ?string
    {
        if ($this->zone === null) {
            return __('shipping.quote.no_zone', ['city' => $this->city, 'country' => Countries::name($this->countryCode)]);
        }

        return $this->methods === [] ? __('shipping.quote.no_methods', ['zone' => $this->zone->name]) : null;
    }

    /**
     * The offered method with this id. Anything else (another zone's method, an inactive
     * one) is refused with 422 `shipping_method_unavailable`.
     */
    public function method(int $id): ShippingMethod
    {
        foreach ($this->methods as $method) {
            if ($method->id === $id) {
                return $method;
            }
        }

        throw new BusinessRuleViolation('shipping_method_unavailable', $this->unavailableReason() ?? __('shipping.quote.method_unavailable'), 422);
    }
}
