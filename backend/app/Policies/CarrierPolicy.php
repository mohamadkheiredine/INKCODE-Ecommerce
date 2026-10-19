<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Carrier;
use App\Models\User;

/**
 * Carriers are picked when shipping an Order and shown on it, so Roles that see Orders
 * or manage Shipments may list them; only Roles that manage shipping change them.
 */
class CarrierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            Permission::ShippingManage->value,
            Permission::ShipmentsManage->value,
            Permission::OrdersView->value,
        ]);
    }

    public function view(User $user, Carrier $carrier): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ShippingManage->value);
    }

    public function update(User $user, Carrier $carrier): bool
    {
        return $user->hasPermissionTo(Permission::ShippingManage->value);
    }

    public function delete(User $user, Carrier $carrier): bool
    {
        return $user->hasPermissionTo(Permission::ShippingManage->value);
    }
}
