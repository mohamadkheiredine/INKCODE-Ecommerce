<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Starts a session for an active User who holds a Role. Failed attempts are limited
 * per email address (from one IP) and per IP address across all email addresses.
 */
final class SignIn
{
    public const MAX_FAILED_ATTEMPTS = 5;

    public const MAX_FAILED_ATTEMPTS_PER_IP = 20;

    public const LOCKOUT_SECONDS = 60;

    private readonly SessionGuard $guard;

    public function __construct(AuthFactory $auth, private readonly RateLimiter $limiter)
    {
        $guard = $auth->guard('web');

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Signing in requires the session-based "web" guard.');
        }

        $this->guard = $guard;
    }

    /**
     * @throws ThrottleRequestsException when this email or this IP is locked out
     * @throws ValidationException when the credentials are wrong, or the User is deactivated or has no Role
     */
    public function handle(string $email, string $password, bool $remember, string $ip, Session $session): User
    {
        $emailKey = 'sign-in:'.Str::transliterate(Str::lower($email)).'|'.$ip;
        $ipKey = 'sign-in-ip:'.$ip;

        $this->ensureNotLockedOut($emailKey, self::MAX_FAILED_ATTEMPTS);
        $this->ensureNotLockedOut($ipKey, self::MAX_FAILED_ATTEMPTS_PER_IP);

        $refusal = null;

        // The callback only runs once the password is verified, so these reasons are
        // never revealed to someone who doesn't know the password.
        $signedIn = $this->guard->attemptWhen(
            ['email' => $email, 'password' => $password],
            function (User $user) use (&$refusal): bool {
                $refusal = match (true) {
                    ! $user->is_active => 'auth.deactivated',
                    $user->role() === null => 'auth.no_role',
                    default => null,
                };

                return $refusal === null;
            },
            $remember,
        );

        if (! $signedIn) {
            $this->limiter->hit($emailKey, self::LOCKOUT_SECONDS);
            $this->limiter->hit($ipKey, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages(['email' => __($refusal ?? 'auth.failed')]);
        }

        $this->limiter->clear($emailKey);
        $session->regenerate();

        /** @var User $user */
        $user = $this->guard->user();

        return $user;
    }

    private function ensureNotLockedOut(string $key, int $maxAttempts): void
    {
        if (! $this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        $seconds = $this->limiter->availableIn($key);

        throw new ThrottleRequestsException(
            __('auth.throttle', ['seconds' => $seconds]),
            headers: ['Retry-After' => (string) $seconds],
        );
    }
}
