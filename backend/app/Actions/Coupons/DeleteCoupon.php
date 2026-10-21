<?php

namespace App\Actions\Coupons;

use App\Models\Coupon;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a Coupon no Order uses. One that Orders use (restricting foreign keys) is
 * refused with 409 `in_use`; ending it is the way to stop it.
 */
final class DeleteCoupon
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Coupon $coupon): void
    {
        DatabaseErrors::refuseDeleteInUse(
            fn () => DB::transaction(function () use ($coupon): void {
                $this->auditTrail->deleted($coupon);
                $coupon->delete();
            }),
            __('coupons.in_use', ['code' => $coupon->code]),
        );
    }
}
