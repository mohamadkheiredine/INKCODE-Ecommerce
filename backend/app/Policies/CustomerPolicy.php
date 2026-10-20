<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;

/**
 * Roles that serve Customers look at them; Sales and Support (and Admin) also change them.
 * Changing a Customer covers their addresses, Tags and notes.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CustomersView->value);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo(Permission::CustomersView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CustomersManage->value);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo(Permission::CustomersManage->value);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo(Permission::CustomersManage->value);
    }
}
