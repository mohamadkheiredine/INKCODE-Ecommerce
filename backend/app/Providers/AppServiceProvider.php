<?php

namespace App\Providers;

use App\Actions\Profile\ChangePassword;
use App\Contracts\PaymentGateway;
use App\Models\Adjustment;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\Receipt;
use App\Models\Shipment;
use App\Models\Transfer;
use App\Services\Payments\MockCardGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // The demo store charges Cards through the mock gateway; a real provider binds here instead.
        $this->app->bind(PaymentGateway::class, MockCardGateway::class);
    }

    /**
     * Bootstrap any application services.
     *
     * `Password::defaults()` is the minimum strength for every password a User chooses
     * (their own, and the temporary one an Admin gives a new User). The `password-change`
     * limiter keeps a stolen session from guessing the current password. The morph map
     * gives Stock movement sources short, stable names (`receipt`) instead of class names.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'receipt' => Receipt::class,
            'adjustment' => Adjustment::class,
            'transfer' => Transfer::class,
            'order' => Order::class,
            'order_note' => OrderNote::class,
            'shipment' => Shipment::class,
        ]);

        Password::defaults(fn (): Password => Password::min(10)->max(72)->letters()->mixedCase()->numbers());

        RateLimiter::for('password-change', fn (Request $request): Limit => Limit::perMinute(ChangePassword::ATTEMPTS_PER_MINUTE)
            ->by('password-change:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
