<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a Customer together with their addresses, notes and Tag links (cascading keys).
 * A Customer who has Orders is refused with 409 `in_use`: Orders reference their Customer
 * through a restricting foreign key (ticket #16), so the database itself refuses.
 */
final class DeleteCustomer
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Customer $customer): void
    {
        DatabaseErrors::refuseDeleteInUse(
            fn () => DB::transaction(function () use ($customer): void {
                // Recorded first, while the Tags are still linked; a refused delete rolls it back.
                $this->auditTrail->deleted($customer);
                $customer->delete();
            }),
            __('customers.has_orders', ['name' => $customer->name]),
        );
    }
}
