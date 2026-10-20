<?php

namespace App\Enums;

/**
 * What an address is used for. A Customer has one default address for each purpose.
 */
enum AddressPurpose: string
{
    case Billing = 'billing';
    case Shipping = 'shipping';

    /** The customer_addresses column that marks the default address for this purpose. */
    public function defaultColumn(): string
    {
        return "is_default_{$this->value}";
    }
}
