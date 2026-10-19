<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Brand;
use App\Models\User;

/**
 * Every Role may see the catalog; only Roles that manage it may change Brands.
 */
class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CatalogView->value);
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->hasPermissionTo(Permission::CatalogView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }

    /**
     * Also covers the Brand's logo.
     */
    public function update(User $user, Brand $brand): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }
}
