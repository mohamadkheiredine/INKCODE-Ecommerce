<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Only the Admin (users.manage) sees and changes other Users. What an Admin may not do to
 * their own access is a business rule of the actions (ProtectAdminAccess), not a
 * permission. Every User manages their own profile through the Profile endpoints.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::UsersManage->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo(Permission::UsersManage->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::UsersManage->value);
    }

    /**
     * Covers details, Role, deactivation and reactivation.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo(Permission::UsersManage->value);
    }
}
