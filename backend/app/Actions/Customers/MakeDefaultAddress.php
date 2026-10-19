<?php

namespace App\Actions\Customers;

use App\Enums\AddressPurpose;
use App\Models\CustomerAddress;
use App\Services\AuditTrail;

/**
 * Makes an address its Customer's default for one purpose, taking the role away from the
 * previous default first (the unique index allows only one at any moment). The previous
 * default's change is recorded here; the caller records the change to `$address` along
 * with its own. Runs inside the caller's transaction, which holds the Customer's lock.
 */
final class MakeDefaultAddress
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(CustomerAddress $address, AddressPurpose $purpose): void
    {
        $column = $purpose->defaultColumn();
        if ($address->getAttribute($column) === true) {
            return;
        }

        $previous = CustomerAddress::query()
            ->where('customer_id', $address->customer_id)
            ->where($column, true)
            ->first();

        if ($previous !== null) {
            $before = $previous->auditValues();
            $previous->update([$column => false]);
            $this->auditTrail->updated($previous, $before);
        }

        $address->update([$column => true]);
    }
}
