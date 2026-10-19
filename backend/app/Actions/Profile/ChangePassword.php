<?php

namespace App\Actions\Profile;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Replaces a User's password. Every other session of that User ends (Sanctum's
 * AuthenticateSession compares the password hash on each request), while the device
 * that made the change stays signed in, including its "keep me signed in" cookie. The
 * change is audited, without any password value.
 */
final class ChangePassword
{
    /**
     * Attempts a User may make per minute (the `password-change` rate limiter).
     */
    public const ATTEMPTS_PER_MINUTE = 5;

    private readonly SessionGuard $guard;

    public function __construct(AuthFactory $auth, private readonly AuditTrail $auditTrail)
    {
        $guard = $auth->guard('web');

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Changing a password requires the session-based "web" guard.');
        }

        $this->guard = $guard;
    }

    /**
     * The current password must already be confirmed (see ChangePasswordRequest).
     */
    public function handle(User $user, #[\SensitiveParameter] string $newPassword): void
    {
        DB::transaction(function () use ($user, $newPassword): void {
            $user->forceFill(['password' => $newPassword])->save();
            $this->auditTrail->record(AuditAction::PasswordChanged, $user);
        });

        $this->guard->logoutOtherDevices($newPassword);
    }
}
