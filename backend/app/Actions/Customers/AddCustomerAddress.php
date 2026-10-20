<?php

namespace App\Actions\Customers;

use App\Enums\AddressPurpose;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Adds an address to a Customer. Their first address becomes the default for billing and
 * shipping; a later one takes over a default only when asked to.
 */
final class AddCustomerAddress
{
    public function __construct(
        private readonly MakeDefaultAddress $makeDefaultAddress,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * @param  array<string, string|null>  $fields  the address itself, without default flags
     * @param  array<value-of<AddressPurpose>, bool|null>  $defaults  true to make it the default for that purpose
     */
    public function handle(Customer $customer, array $fields, array $defaults): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $fields, $defaults): CustomerAddress {
            // One address change per Customer at a time, so defaults never race.
            Customer::query()->whereKey($customer->id)->lockForUpdate()->first();
            $isFirst = ! $customer->addresses()->exists();

            $address = $customer->addresses()->create($fields);
            foreach (AddressPurpose::cases() as $purpose) {
                if ($isFirst || ($defaults[$purpose->value] ?? false)) {
                    $this->makeDefaultAddress->handle($address, $purpose);
                }
            }

            $this->auditTrail->created($address);

            return $address;
        });
    }
}
