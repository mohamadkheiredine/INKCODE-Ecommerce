<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Order;
use App\Models\User;

/**
 * Every Role with orders.view sees Orders. Roles that create Orders (orders.create) enter
 * them; confirming one commits the Order its creator entered, so the Roles that update
 * Orders (orders.update) may do it; orders.cancel cancels.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::OrdersView->value);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::OrdersCreate->value);
    }

    public function confirm(User $user, Order $order): bool
    {
        return $user->hasPermissionTo(Permission::OrdersUpdate->value);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->hasPermissionTo(Permission::OrdersCancel->value);
    }
}
