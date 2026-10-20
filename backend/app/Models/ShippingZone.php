<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use Database\Factories\ShippingZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A group of countries or cities that share the same Shipping methods and prices.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ShippingZoneLocation> $locations
 * @property-read Collection<int, ShippingMethod> $methods
 */
#[Fillable(['name'])]
class ShippingZone extends Model implements Auditable
{
    use Audited {
        auditValues as private attributeAuditValues;
    }

    /** @use HasFactory<ShippingZoneFactory> */
    use HasFactory;

    /**
     * @return HasMany<ShippingZoneLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(ShippingZoneLocation::class)->orderBy('country_code')->orderBy('city_key');
    }

    /**
     * @return HasMany<ShippingMethod, $this>
     */
    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }

    /**
     * The zone's name and the places it covers ("LB", "AE/Dubai"), so the trail shows
     * coverage changes too.
     *
     * @return array<string, mixed>
     */
    public function auditValues(): array
    {
        return [
            ...$this->attributeAuditValues(),
            'locations' => $this->locations()->get()
                ->map(fn (ShippingZoneLocation $location): string => $location->label())
                ->all(),
        ];
    }
}
