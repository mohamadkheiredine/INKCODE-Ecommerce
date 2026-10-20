<?php

namespace App\Actions\Access;

use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Gives a deactivated User their access back. They sign in again with their password;
 * sessions ended by the deactivation stay ended.
 */
final class ReactivateUser
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $before = $user->auditValues();
            $user->forceFill(['is_active' => true])->save();

            $this->auditTrail->updated($user, $before);

            return $user;
        });
    }
}
