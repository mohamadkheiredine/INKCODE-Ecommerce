<?php

namespace App\Actions\Customers;

use App\Enums\AddressPurpose;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Removes an address. If it was a default, the Customer's oldest remaining address takes
 * that role over, so a Customer with addresses always has both defaults.
 */
final class RemoveCustomerAddress
{
    public function __construct(
        private readonly MakeDefaultAddress $makeDefaultAddress,
        private readonly AuditTrail $auditTrail,
    ) {}

    public function handle(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address): void {
            Customer::query()->whereKey($address->customer_id)->lockForUpdate()->first();
            $address->refresh();

            $this->auditTrail->deleted($address);
            $address->delete();

            $successor = CustomerAddress::query()->where('customer_id', $address->customer_id)->orderBy('id')->first();
            if ($successor === null) {
                return;
            }

            $before = $successor->auditValues();
            foreach (AddressPurpose::cases() as $purpose) {
                if ($address->getAttribute($purpose->defaultColumn()) === true) {
                    $this->makeDefaultAddress->handle($successor, $purpose);
                }
            }
            $this->auditTrail->updated($successor, $before);
        });
    }
}
