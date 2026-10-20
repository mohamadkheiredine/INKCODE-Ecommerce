<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Receipt;
use App\Models\User;

/**
 * Roles that see stock see Receipts; only Roles that receive stock record them.
 */
class ReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::InventoryView->value);
    }

    public function view(User $user, Receipt $receipt): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::InventoryReceive->value);
    }
}
