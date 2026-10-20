<?php

namespace App\Actions\Shipping;

use App\Models\Carrier;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a Carrier nothing depends on. One still named on Shipments (ticket #18, through
 * a restricting foreign key) is refused with 409 `in_use`.
 */
final class DeleteCarrier
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Carrier $carrier): void
    {
        DatabaseErrors::refuseDeleteInUse(
            fn () => DB::transaction(function () use ($carrier): void {
                $carrier->delete();
                $this->auditTrail->deleted($carrier);
            }),
            __('shipping.carriers.in_use', ['name' => $carrier->name]),
        );
    }
}
