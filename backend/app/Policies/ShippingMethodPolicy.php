<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ShippingMethod;
use App\Models\User;

/**
 * A Shipping method is changed by the Roles that manage shipping. Adding one to a zone
 * is authorized as an update of the zone ({@see ShippingZonePolicy::update()}).
 */
class ShippingMethodPolicy
{
    public function update(User $user, ShippingMethod $method): bool
    {
        return $user->hasPermissionTo(Permission::ShippingManage->value);
    }

    public function delete(User $user, ShippingMethod $method): bool
    {
        return $user->hasPermissionTo(Permission::ShippingManage->value);
    }
}
