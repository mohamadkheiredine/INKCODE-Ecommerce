<?php

namespace App\Services;

use App\Enums\DeductionStage;
use App\Models\StoreSettings;
use Illuminate\Support\Facades\Cache;

/**
 * The one way to read the store's settings. They are read by almost every request that
 * prices, reserves or returns anything, so the row is cached (and memoized for the
 * request). Every change goes through an action that calls {@see forget()} once saved.
 *
 *     $settings->vatRate();          // "5.00", a decimal string for BCMath
 *     $settings->deductionStage();   // DeductionStage::Shipped
 */
final class Settings
{
    private const string CACHE_KEY = 'store-settings';

    public function current(): StoreSettings
    {
        /** @var array<string, mixed> $attributes */
        $attributes = Cache::memo()->rememberForever(
            self::CACHE_KEY,
            fn (): array => StoreSettings::query()->findOrFail(StoreSettings::ID)->getAttributes(),
        );

        return (new StoreSettings)->newFromBuilder($attributes);
    }

    /** The VAT rate in percent, as a decimal string ("5.00"). */
    public function vatRate(): string
    {
        return $this->current()->vat_rate;
    }

    public function currencyCode(): string
    {
        return $this->current()->currency_code;
    }

    public function deductionStage(): DeductionStage
    {
        return $this->current()->deduction_stage;
    }

    public function returnWindowDays(): int
    {
        return $this->current()->return_window_days;
    }

    /** The Low-stock threshold a new Variant starts with. */
    public function lowStockThreshold(): int
    {
        return $this->current()->low_stock_threshold;
    }

    /** Drops the cached settings; call it once a change is committed. */
    public function forget(): void
    {
        Cache::memo()->forget(self::CACHE_KEY);
    }
}
