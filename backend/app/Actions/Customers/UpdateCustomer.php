<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Saves a Customer's new details and, when given, their new set of Tags.
 */
final class UpdateCustomer
{
    public function __construct(
        private readonly SyncCustomerTags $syncCustomerTags,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * @param  array{name: string, email: string|null, phone: string|null}  $details
     * @param  list<string>|null  $tagNames  null keeps the current Tags
     */
    public function handle(Customer $customer, array $details, ?array $tagNames): Customer
    {
        return DatabaseErrors::refuseDuplicate(fn (): Customer => DB::transaction(function () use ($customer, $details, $tagNames): Customer {
            $before = $customer->auditValues();

            $customer->update($details);
            if ($tagNames !== null) {
                $this->syncCustomerTags->handle($customer, $tagNames);
            }

            $this->auditTrail->updated($customer, $before);

            return $customer;
        }), 'email');
    }
}
