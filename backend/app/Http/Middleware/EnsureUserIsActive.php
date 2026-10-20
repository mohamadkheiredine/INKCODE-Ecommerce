<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a User an Admin has deactivated on their very next request, whatever stores
 * the session. Deactivating also deletes the User's stored sessions (DeactivateUser);
 * this is the guarantee for session stores that cannot be searched by User.
 */
class EnsureUserIsActive
{
    public function __construct(private readonly AuthFactory $auth) {}

    /**
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthenticationException when the signed-in User is deactivated
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            $guard = $this->auth->guard('web');
            if ($guard instanceof SessionGuard) {
                $guard->logout();
            }
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
