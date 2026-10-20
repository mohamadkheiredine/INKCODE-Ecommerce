<?php

namespace App\Actions\Settings;

use App\Enums\DeductionStage;
use App\Enums\Locale;
use App\Enums\Theme;
use App\Models\StoreSettings;
use App\Services\AuditTrail;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Saves the settings form. The row is locked while it changes, so two Admins saving at
 * once each get an audit entry with the true values before their change. The cached
 * settings are dropped once the change is committed.
 */
final class UpdateStoreSettings
{
    public function __construct(
        private readonly AuditTrail $auditTrail,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array{name: string, contact_email: string|null, contact_phone: string|null, address: string|null, currency_code: string, currency_symbol: string, vat_rate: string, deduction_stage: DeductionStage, return_window_days: int, low_stock_threshold: int, default_locale: Locale, default_theme: Theme}  $values
     */
    public function handle(array $values): StoreSettings
    {
        $storeSettings = DB::transaction(function () use ($values): StoreSettings {
            $storeSettings = StoreSettings::query()->lockForUpdate()->findOrFail(StoreSettings::ID);
            $before = $storeSettings->auditValues();

            $storeSettings->update($values);
            $this->auditTrail->updated($storeSettings, $before);

            return $storeSettings;
        });

        $this->settings->forget();

        return $storeSettings;
    }
}
