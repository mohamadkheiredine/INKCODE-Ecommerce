<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ShippingZone;
use App\Models\User;

/**
 * Anyone who sees Orders may see where the store ships and at what price; only Roles
 * that manage shipping change Shipping zones and their Shipping methods.
 */
class ShippingZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([Permission::ShippingManage->value, Permission::OrdersView->value]);
    }

    public function view(User $user, ShippingZone $zone): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ShippingManage->value);
    }

    /**
     * Also covers the zone's Shipping methods.
     */
    public function update(User $user, ShippingZone $zone): bool
    {
        return $user->hasPermissionTo(Permission::ShippingManage->value);
    }

    public function delete(User $user, ShippingZone $zone): bool
    {
        return $user->hasPermissionTo(Permission::ShippingManage->value);
    }
}
