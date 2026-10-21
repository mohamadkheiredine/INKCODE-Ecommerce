<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Coupon;
use App\Models\User;

/**
 * Roles with coupons.view see Coupons and their usage; only coupons.manage (Admin, Sales)
 * creates, edits and deletes them. Applying a Coupon to an Order is part of creating or
 * editing the Order ({@see OrderPolicy}).
 */
class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CouponsView->value);
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CouponsManage->value);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $user->hasPermissionTo(Permission::CouponsManage->value);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $user->hasPermissionTo(Permission::CouponsManage->value);
    }
}
