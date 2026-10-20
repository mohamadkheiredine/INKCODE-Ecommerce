<?php

namespace App\Actions\Access;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Saves a User's name, email and Role. A new Role takes effect on the User's next
 * request; taking a Role away follows {@see ProtectAdminAccess}.
 */
final class UpdateUser
{
    public function __construct(
        private readonly ProtectAdminAccess $protectAdminAccess,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * @param  array{name: string, email: string}  $details
     */
    public function handle(User $actor, User $user, array $details, Role $role): User
    {
        return DatabaseErrors::refuseDuplicate(fn (): User => DB::transaction(function () use ($actor, $user, $details, $role): User {
            $before = $user->auditValues();

            if ($user->role() !== $role) {
                $this->protectAdminAccess->handle($actor, $user);
                $user->assignOnlyRole($role);
            }
            $user->update($details);

            $this->auditTrail->updated($user, $before);

            return $user;
        }), 'email');
    }
}
