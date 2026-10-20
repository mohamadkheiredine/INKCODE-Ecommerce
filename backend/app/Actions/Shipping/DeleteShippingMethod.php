<?php

namespace App\Actions\Shipping;

use App\Models\ShippingMethod;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a Shipping method. One that Orders already point at is refused with 409
 * `in_use`; deactivating it stops it being offered instead.
 */
final class DeleteShippingMethod
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(ShippingMethod $method): void
    {
        DatabaseErrors::refuseDeleteInUse(
            fn () => DB::transaction(function () use ($method): void {
                $method->delete();
                $this->auditTrail->deleted($method);
            }),
            __('shipping.methods.in_use', ['name' => $method->name]),
        );
    }
}
