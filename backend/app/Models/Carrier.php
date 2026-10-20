<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use Database\Factories\CarrierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The company that physically delivers a Shipment. Its optional tracking link template
 * holds {@see self::TRACKING_PLACEHOLDER}, which is replaced by a Shipment's tracking number.
 *
 * @property int $id
 * @property string $name
 * @property string|null $tracking_url_template
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'tracking_url_template'])]
class Carrier extends Model implements Auditable
{
    use Audited;

    /** @use HasFactory<CarrierFactory> */
    use HasFactory;

    public const string TRACKING_PLACEHOLDER = '{tracking_number}';

    /** @var array<string, mixed> */
    protected $attributes = [
        'tracking_url_template' => null,
    ];

    /**
     * The tracking page for a tracking number, or null when the Carrier has no template.
     */
    public function trackingUrl(string $trackingNumber): ?string
    {
        return $this->tracking_url_template === null
            ? null
            : str_replace(self::TRACKING_PLACEHOLDER, rawurlencode($trackingNumber), $this->tracking_url_template);
    }
}
