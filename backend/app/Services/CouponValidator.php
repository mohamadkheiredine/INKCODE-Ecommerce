<?php

namespace App\Services;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Variant;
use Illuminate\Support\Carbon;

/**
 * The one place that decides whether a Coupon code applies to an Order's lines, used by
 * the live quote, creating and editing an Order, and confirming it. A refusal is a 422
 * `coupon_invalid` whose `details.reason` says why: `not_found`, `not_started`, `expired`,
 * `limit_reached`, `customer_limit_reached`, `below_minimum` or `no_eligible_items`.
 *
 * Usage limits count the redemptions not released, which are made at Confirm. With
 * `$lock` the Coupon's row stays locked until the caller's transaction ends, so
 * simultaneous confirmations for the last use are counted one after another.
 *
 * An Order that already holds a redemption of the Coupon keeps it: its dates and limits
 * are not checked again (they were when it was Confirmed), only its minimum and items.
 */
final class CouponValidator
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  list<array{variant_id: int, quantity: int, unit_price: string}>  $lines
     *
     * @throws BusinessRuleViolation 422 `coupon_invalid`
     */
    public function validate(string $code, array $lines, ?int $customerId, ?Order $order = null, bool $lock = false): AppliedCoupon
    {
        $code = self::normalize($code);
        $query = Coupon::query()->where('code', $code);
        $coupon = ($lock ? $query->lockForUpdate() : $query)->first()
            ?? throw self::refusal('not_found', ['code' => $code]);

        $holdsRedemption = $order !== null && CouponRedemption::query()
            ->where(['order_id' => $order->id, 'coupon_id' => $coupon->id])
            ->whereNull('released_at')
            ->exists();

        if (! $holdsRedemption) {
            $this->checkDates($coupon);
            $this->checkLimits($coupon, $customerId, $lock);
        }

        $itemsTotal = '0.00';
        foreach ($lines as $line) {
            $itemsTotal = bcadd($itemsTotal, bcmul((string) $line['quantity'], $line['unit_price'], 2), 2);
        }
        if (bccomp($itemsTotal, $coupon->minimum_order_amount, 2) < 0) {
            throw self::refusal('below_minimum', [
                'code' => $coupon->code,
                'amount' => $coupon->minimum_order_amount,
                'currency' => $this->settings->currencyCode(),
            ]);
        }

        $eligible = $this->eligibleVariantIds($coupon, array_values(array_map(
            fn (array $line): int => $line['variant_id'],
            array_filter($lines, fn (array $line): bool => $line['quantity'] > 0),
        )));
        if ($eligible === []) {
            throw self::refusal('no_eligible_items', ['code' => $coupon->code]);
        }

        return new AppliedCoupon($coupon, $eligible);
    }

    /** Codes are kept in capitals; Users may type them in any case. */
    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /**
     * @param  array<string, string>  $replace
     */
    public static function refusal(string $reason, array $replace): BusinessRuleViolation
    {
        return new BusinessRuleViolation('coupon_invalid', __("coupons.invalid.$reason", $replace), 422, [
            'reason' => $reason,
            'field' => 'coupon_code',
        ]);
    }

    private function checkDates(Coupon $coupon): void
    {
        $today = Carbon::today();

        if ($coupon->starts_on->greaterThan($today)) {
            throw self::refusal('not_started', ['code' => $coupon->code, 'date' => $coupon->starts_on->toDateString()]);
        }
        if ($coupon->ends_on !== null && $coupon->ends_on->lessThan($today)) {
            throw self::refusal('expired', ['code' => $coupon->code, 'date' => $coupon->ends_on->toDateString()]);
        }
    }

    /**
     * With `$lock` the uses are counted with a locking read: under REPEATABLE READ a plain
     * read could still see the transaction's earlier snapshot, missing uses committed while
     * it waited for the Coupon's row.
     */
    private function checkLimits(Coupon $coupon, ?int $customerId, bool $lock): void
    {
        $active = CouponRedemption::query()->where('coupon_id', $coupon->id)->whereNull('released_at');
        if ($lock) {
            $active->sharedLock();
        }

        if ($coupon->usage_limit !== null && (clone $active)->count() >= $coupon->usage_limit) {
            throw self::refusal('limit_reached', ['code' => $coupon->code]);
        }
        if ($coupon->per_customer_limit !== null && $customerId !== null
            && (clone $active)->where('customer_id', $customerId)->count() >= $coupon->per_customer_limit) {
            throw self::refusal('customer_limit_reached', ['code' => $coupon->code]);
        }
    }

    /**
     * Every Variant when the Coupon names no Products or Categories; otherwise those of its
     * Products and of Products in its Categories (or their child Categories).
     *
     * @param  list<int>  $variantIds
     * @return list<int>
     */
    private function eligibleVariantIds(Coupon $coupon, array $variantIds): array
    {
        $productIds = $coupon->products()->pluck('products.id')->all();
        $categoryIds = $coupon->categories()->pluck('categories.id')->all();

        if ($productIds === [] && $categoryIds === []) {
            return $variantIds;
        }

        if ($categoryIds !== []) {
            $categoryIds = [...$categoryIds, ...Category::query()->whereIn('parent_id', $categoryIds)->pluck('id')->all()];
        }

        return Variant::query()
            ->whereIn('variants.id', $variantIds)
            ->whereHas('product', fn ($query) => $query->where(fn ($query) => $query
                ->whereIn('products.id', $productIds)
                ->orWhereIn('products.category_id', $categoryIds)))
            ->orderBy('variants.id')
            ->pluck('variants.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
