<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Customer with their Tags. The audit entry holds the details and the Tag names.
 */
final class CreateCustomer
{
    public function __construct(
        private readonly SyncCustomerTags $syncCustomerTags,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * @param  array{name: string, email: string|null, phone: string|null}  $details
     * @param  list<string>  $tagNames
     */
    public function handle(array $details, array $tagNames): Customer
    {
        return DatabaseErrors::refuseDuplicate(fn (): Customer => DB::transaction(function () use ($details, $tagNames): Customer {
            $customer = Customer::query()->create($details);
            $this->syncCustomerTags->handle($customer, $tagNames);

            $this->auditTrail->created($customer);

            return $customer;
        }), 'email');
    }
}
