<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Product;
use App\Models\User;

/**
 * Every Role may see the catalog; only Roles that manage it may change Products,
 * including their images and Variants.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CatalogView->value);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermissionTo(Permission::CatalogView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }

    /**
     * Also covers the Product's images and Variants, and status changes in bulk.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }

    /**
     * Bulk actions on the list: changing the status of, or deleting, several Products.
     */
    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermissionTo(Permission::CatalogManage->value);
    }
}
