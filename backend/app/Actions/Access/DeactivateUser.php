<?php

namespace App\Actions\Access;

use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Takes a User's access away without deleting them, so their audit history stays. Every
 * way they were signed in ends at once: stored sessions are deleted, the "keep me signed
 * in" cookie stops matching and API tokens are revoked (EnsureUserIsActive covers session
 * stores that cannot be searched by User).
 */
final class DeactivateUser
{
    public function __construct(
        private readonly ProtectAdminAccess $protectAdminAccess,
        private readonly AuditTrail $auditTrail,
        private readonly Config $config,
    ) {}

    public function handle(User $actor, User $user): User
    {
        return DB::transaction(function () use ($actor, $user): User {
            if (! $user->is_active) {
                return $user;
            }

            $this->protectAdminAccess->handle($actor, $user);

            $before = $user->auditValues();
            $user->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            $this->endStoredSessions($user);

            $this->auditTrail->updated($user, $before);

            return $user;
        });
    }

    private function endStoredSessions(User $user): void
    {
        if ($this->config->get('session.driver') !== 'database') {
            return;
        }

        DB::connection($this->config->get('session.connection'))
            ->table((string) $this->config->get('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }
}
