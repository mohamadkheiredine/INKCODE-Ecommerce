<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Adds a note to a Customer, signed by the User who wrote it.
 */
final class AddCustomerNote
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Customer $customer, User $author, string $body): CustomerNote
    {
        return DB::transaction(function () use ($customer, $author, $body): CustomerNote {
            $note = new CustomerNote(['body' => $body]);
            $note->customer()->associate($customer);
            $note->author()->associate($author);
            $note->save();

            $this->auditTrail->created($note);

            return $note;
        });
    }
}
