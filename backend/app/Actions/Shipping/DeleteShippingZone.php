<?php

namespace App\Actions\Shipping;

use App\Models\ShippingZone;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a Shipping zone with its places and Shipping methods; each method gets its own
 * audit entry. A zone whose methods Orders already point at (ticket #16) is refused with
 * 409 `in_use`.
 */
final class DeleteShippingZone
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(ShippingZone $zone): void
    {
        DatabaseErrors::refuseDeleteInUse(
            fn () => DB::transaction(function () use ($zone): void {
                $this->auditTrail->deleted($zone);
                foreach ($zone->methods as $method) {
                    $this->auditTrail->deleted($method);
                }
                $zone->delete();
            }),
            __('shipping.zones.in_use', ['name' => $zone->name]),
        );
    }
}
