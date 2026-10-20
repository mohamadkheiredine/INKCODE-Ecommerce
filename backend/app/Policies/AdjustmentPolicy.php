<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Roles that see stock see Adjustments; only Roles that adjust stock record them.
 */
class AdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::InventoryView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::InventoryAdjust->value);
    }
}
