<?php

namespace App\Actions\Customers;

use App\Enums\AddressPurpose;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves an address and, when asked, makes it a default. A default is never simply
 * switched off: the Customer would be left without one, so the User makes another
 * address the default instead (422 on that flag).
 */
final class UpdateCustomerAddress
{
    public function __construct(
        private readonly MakeDefaultAddress $makeDefaultAddress,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * @param  array<string, string|null>  $fields  the address itself, without default flags
     * @param  array<value-of<AddressPurpose>, bool|null>  $defaults  true to make it the default, null to leave it
     */
    public function handle(CustomerAddress $address, array $fields, array $defaults): CustomerAddress
    {
        return DB::transaction(function () use ($address, $fields, $defaults): CustomerAddress {
            Customer::query()->whereKey($address->customer_id)->lockForUpdate()->first();
            $address->refresh();

            foreach (AddressPurpose::cases() as $purpose) {
                if (($defaults[$purpose->value] ?? null) === false && $address->getAttribute($purpose->defaultColumn()) === true) {
                    throw ValidationException::withMessages([
                        $purpose->defaultColumn() => __('customers.addresses.keeps_default', ['purpose' => __("customers.addresses.{$purpose->value}")]),
                    ]);
                }
            }

            $before = $address->auditValues();
            $address->update($fields);
            foreach (AddressPurpose::cases() as $purpose) {
                if ($defaults[$purpose->value] ?? false) {
                    $this->makeDefaultAddress->handle($address, $purpose);
                }
            }

            $this->auditTrail->updated($address, $before);

            return $address;
        });
    }
}
