<?php

namespace App\Actions\Shipping;

use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Adds a Shipping method to a zone, or saves an existing method's new values.
 */
final class SaveShippingMethod
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array{name: string, price: string, min_delivery_days: int, max_delivery_days: int, is_active: bool}  $values
     */
    public function handle(ShippingZone $zone, ?ShippingMethod $method, array $values): ShippingMethod
    {
        return DatabaseErrors::refuseDuplicate(fn (): ShippingMethod => DB::transaction(function () use ($zone, $method, $values): ShippingMethod {
            if ($method === null) {
                $method = $zone->methods()->create($values);
                $this->auditTrail->created($method);

                return $method;
            }

            $before = $method->auditValues();
            $method->update($values);
            $this->auditTrail->updated($method, $before);

            return $method;
        }), [
            'shipping_methods_shipping_zone_id_name_unique' => ['name', __('validation.unique', ['attribute' => 'name'])],
        ]);
    }
}
