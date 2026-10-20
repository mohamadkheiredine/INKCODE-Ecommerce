<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Transfer;
use App\Models\User;

/**
 * Roles that see stock see Transfers; only Roles that transfer stock create, send,
 * receive or cancel them.
 */
class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::InventoryView->value);
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::InventoryTransfer->value);
    }

    public function update(User $user, Transfer $transfer): bool
    {
        return $this->create($user);
    }
}
