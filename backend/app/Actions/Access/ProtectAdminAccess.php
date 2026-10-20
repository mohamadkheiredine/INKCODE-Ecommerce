<?php

namespace App\Actions\Access;

use App\Enums\Role;
use App\Exceptions\BusinessRuleViolation;
use App\Models\User;

/**
 * The rules guarding a change that takes access away from a User (a new Role, or
 * deactivation): an Admin never takes away their own access, and the store never loses
 * its last active Admin. Call it inside the transaction that makes the change.
 */
final class ProtectAdminAccess
{
    /**
     * @throws BusinessRuleViolation 403 `own_access` when the actor is the User; 409 `last_admin`
     *                               when the User is the last active Admin
     */
    public function handle(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw new BusinessRuleViolation('own_access', __('users.own_access'), 403);
        }

        if ($user->role() !== Role::Admin || ! $user->is_active) {
            return;
        }

        // Locks every active Admin, so two Admins taking each other's access at the same
        // moment are served one after the other and the second is refused.
        $otherActiveAdmins = User::query()
            ->role(Role::Admin->value)
            ->where('is_active', true)
            ->lockForUpdate()
            ->pluck('id')
            ->reject(fn (int $id): bool => $id === $user->id);

        if ($otherActiveAdmins->isEmpty()) {
            throw new BusinessRuleViolation('last_admin', __('users.last_admin'));
        }
    }
}
