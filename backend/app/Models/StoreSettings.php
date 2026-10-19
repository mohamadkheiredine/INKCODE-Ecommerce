<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Enums\DeductionStage;
use App\Enums\Locale;
use App\Enums\Theme;
use App\Models\Concerns\Audited;
use App\Services\Settings;
use App\Support\LogoFile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The store's one row of settings (id 1): its profile and the business settings other
 * modules depend on. Read it through {@see Settings}, which caches it.
 *
 * @property int $id
 * @property string $name
 * @property string|null $logo_path
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $address
 * @property string $currency_code
 * @property string $currency_symbol
 * @property string $vat_rate
 * @property DeductionStage $deduction_stage
 * @property int $return_window_days
 * @property int $low_stock_threshold
 * @property Locale $default_locale
 * @property Theme $default_theme
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'contact_email', 'contact_phone', 'address',
    'currency_code', 'currency_symbol', 'vat_rate',
    'deduction_stage', 'return_window_days', 'low_stock_threshold',
    'default_locale', 'default_theme',
])]
class StoreSettings extends Model implements Auditable
{
    use Audited;

    public const int ID = 1;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:2',
            'deduction_stage' => DeductionStage::class,
            'return_window_days' => 'integer',
            'low_stock_threshold' => 'integer',
            'default_locale' => Locale::class,
            'default_theme' => Theme::class,
        ];
    }

    public function auditRecordType(): string
    {
        return 'store_settings';
    }

    public function logoUrl(): ?string
    {
        return LogoFile::url($this->logo_path);
    }
}
