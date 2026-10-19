<?php

namespace App\Providers;

use App\Actions\Profile\ChangePassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * `Password::defaults()` is the minimum strength for every password a User chooses
     * (their own, and the temporary one an Admin gives a new User). The `password-change`
     * limiter keeps a stolen session from guessing the current password.
     */
    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(10)->max(72)->letters()->mixedCase()->numbers());

        RateLimiter::for('password-change', fn (Request $request): Limit => Limit::perMinute(ChangePassword::ATTEMPTS_PER_MINUTE)
            ->by('password-change:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
