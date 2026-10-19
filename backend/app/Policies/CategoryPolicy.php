<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\User;

/**
 * Every Role may see the catalog; only Roles that manage it may change Categories.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CatalogView->value);
    }

    public function view(User $user, Category $category): bool
    {
        return $user->hasPermissionTo(Permission::CatalogView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }
}
