<?php

namespace App\Http\Controllers\Coupons;

use App\Actions\Coupons\DeleteCoupon;
use App\Actions\Coupons\ListCoupons;
use App\Actions\Coupons\SaveCoupon;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coupons\ListCouponsRequest;
use App\Http\Requests\Coupons\StoreCouponRequest;
use App\Http\Requests\Coupons\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CouponController extends Controller
{
    public function index(ListCouponsRequest $request, ListCoupons $listCoupons): AnonymousResourceCollection
    {
        return CouponResource::collection($listCoupons->handle($request->listParameters()));
    }

    public function store(StoreCouponRequest $request, SaveCoupon $saveCoupon): JsonResponse
    {
        return (new CouponResource(self::loadForDisplay($saveCoupon->handle(null, $request->values()))))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,coupon`).
     */
    public function show(Coupon $coupon): CouponResource
    {
        return new CouponResource(self::loadForDisplay($coupon));
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon, SaveCoupon $saveCoupon): CouponResource
    {
        return new CouponResource(self::loadForDisplay($saveCoupon->handle($coupon, $request->values())));
    }

    /**
     * A Coupon that Orders use is refused with 409 `in_use`. Authorized by the route
     * (`can:delete,coupon`).
     */
    public function destroy(Coupon $coupon, DeleteCoupon $deleteCoupon): Response
    {
        $deleteCoupon->handle($coupon);

        return response()->noContent();
    }

    private static function loadForDisplay(Coupon $coupon): Coupon
    {
        return $coupon
            ->load(['products:id,name', 'categories:id,name'])
            ->loadCount(['redemptions as times_used' => fn (Builder $query) => $query->whereNull('released_at')]);
    }
}
