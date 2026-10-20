<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Attribute;
use App\Models\User;

/**
 * Every Role may see the Attribute library; only Roles that manage the catalog may
 * change it.
 */
class AttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CatalogView->value);
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $user->hasPermissionTo(Permission::CatalogView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }

    /**
     * Also covers the Attribute's values.
     */
    public function update(User $user, Attribute $attribute): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }

    public function delete(User $user, Attribute $attribute): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }
}
