<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Order;
use App\Models\User;

/**
 * Every Role with orders.view sees Orders. Roles that create Orders (orders.create) enter
 * them; confirming one commits the Order its creator entered, so the Roles that update
 * Orders (orders.update) may do it, edit an unlocked Order and add notes; orders.transition
 * moves an Order forward (Preparing, Ready for shipping); orders.cancel cancels all or part
 * of one; orders.assign gives it an Assignee; shipments.manage ships it in Shipments and marks
 * them delivered.
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

    public function update(User $user, Order $order): bool
    {
        return $user->hasPermissionTo(Permission::OrdersUpdate->value);
    }

    public function transition(User $user, Order $order): bool
    {
        return $user->hasPermissionTo(Permission::OrdersTransition->value);
    }

    public function assign(User $user, Order $order): bool
    {
        return $user->hasPermissionTo(Permission::OrdersAssign->value);
    }

    public function ship(User $user, Order $order): bool
    {
        return $user->hasPermissionTo(Permission::ShipmentsManage->value);
    }
}
