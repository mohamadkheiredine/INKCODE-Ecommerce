<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Roles that see stock see the Warehouses; only Roles that manage Warehouses change them.
 */
class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::InventoryView->value);
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::WarehousesManage->value);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermissionTo(Permission::WarehousesManage->value);
    }
}
