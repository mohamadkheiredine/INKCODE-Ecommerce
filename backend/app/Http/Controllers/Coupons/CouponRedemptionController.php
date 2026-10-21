<?php

namespace App\Http\Controllers\Coupons;

use App\Actions\Coupons\ListCouponRedemptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coupons\ListCouponRedemptionsRequest;
use App\Http\Resources\CouponRedemptionResource;
use App\Models\Coupon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CouponRedemptionController extends Controller
{
    /**
     * The Orders and Customers that used the Coupon.
     */
    public function index(ListCouponRedemptionsRequest $request, Coupon $coupon, ListCouponRedemptions $listRedemptions): AnonymousResourceCollection
    {
        return CouponRedemptionResource::collection($listRedemptions->handle($coupon, $request->listParameters()));
    }
}
