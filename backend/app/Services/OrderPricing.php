<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\Variant;

/**
 * Prices an Order the one way the store does (docs/spec.md, "Money math"): each line is
 * quantity × unit price, less its share of the discount; VAT is charged on that amount at
 * the store's VAT rate for taxable Products and rounded to cents per line; shipping is not
 * taxed. Total = Σ(discounted line + its VAT) + shipping. All maths is BCMath on decimal
 * strings, never floats.
 *
 *     $priced = $pricing->price([['variant' => $variant, 'quantity' => 3]], '5.00');
 *     $priced['total']; // "77.97"
 *
 * A Coupon's discount is spread across its eligible lines in proportion to their value,
 * each share cut to the cent, with the cents left over going to the largest line.
 *
 * An existing Order is re-priced from its own snapshots, VAT rate and Coupon terms
 * ({@see reprice()}), so editing or cancelling part of it never picks up later catalog,
 * settings or Coupon changes.
 */
final class OrderPricing
{
    private const int SCALE = 6;

    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  list<array{variant: Variant, quantity: int}>  $items  Variants with their Product and Attribute values loaded
     * @return array{vat_rate: string, items_total: string, discount_total: string, vat_total: string, shipping_total: string, total: string, lines: list<array{variant_id: int, product_name: string, sku: string, options: list<array{attribute: string, value: string}>, quantity: int, unit_price: string, coupon_eligible: bool, discount_amount: string, taxable: bool, vat_rate: string, vat_amount: string, line_total: string}>}
     */
    public function price(array $items, string $shippingPrice, ?AppliedCoupon $coupon = null): array
    {
        $snapshots = array_map(
            fn (array $item): array => self::snapshot($item['variant'], $item['quantity'], $coupon?->isEligible($item['variant']->id) ?? false),
            $items,
        );
        $priced = $this->reprice($snapshots, $this->settings->vatRate(), $shippingPrice, $coupon?->terms());

        return [
            ...$priced,
            'lines' => array_map(fn (array $snapshot, array $amounts): array => [...$snapshot, ...$amounts], $snapshots, $priced['lines']),
        ];
    }

    /**
     * What a line keeps of its Variant when it is added to an Order.
     *
     * @param  Variant  $variant  with its Product and Attribute values loaded
     * @return array{variant_id: int, product_name: string, sku: string, options: list<array{attribute: string, value: string}>, quantity: int, unit_price: string, coupon_eligible: bool, taxable: bool}
     */
    public static function snapshot(Variant $variant, int $quantity, bool $couponEligible = false): array
    {
        return [
            'variant_id' => $variant->id,
            'product_name' => $variant->product->name,
            'sku' => $variant->sku,
            'options' => $variant->options(),
            'quantity' => $quantity,
            'unit_price' => $variant->price,
            'coupon_eligible' => $couponEligible,
            'taxable' => $variant->product->taxable,
        ];
    }

    /**
     * Prices lines at the given VAT rate, less the Coupon's discount on the lines marked
     * `coupon_eligible`. `lines` holds each line's amounts, in the order the lines were given.
     *
     * @param  list<array{quantity: int, unit_price: string, taxable: bool, coupon_eligible?: bool}>  $lines
     * @param  array{type: CouponType, value: string}|null  $coupon
     * @return array{vat_rate: string, items_total: string, discount_total: string, vat_total: string, shipping_total: string, total: string, lines: list<array{discount_amount: string, vat_rate: string, vat_amount: string, line_total: string}>}
     */
    public function reprice(array $lines, string $vatRate, string $shippingPrice, ?array $coupon = null): array
    {
        $amounts = array_map(fn (array $line): string => bcmul((string) $line['quantity'], $line['unit_price'], 2), $lines);
        $discounts = $this->discounts($lines, $amounts, $coupon);

        $priced = [];
        $itemsTotal = $discountTotal = $vatTotal = '0.00';

        foreach ($lines as $index => $line) {
            $amount = $amounts[$index];
            $discount = $discounts[$index];
            $taxable = $line['taxable'];
            $vat = $taxable ? $this->roundToCents(bcdiv(bcmul(bcsub($amount, $discount, 2), $vatRate, self::SCALE), '100', self::SCALE)) : '0.00';

            $priced[] = [
                'discount_amount' => $discount,
                'vat_rate' => $taxable ? $vatRate : '0.00',
                'vat_amount' => $vat,
                'line_total' => bcadd(bcsub($amount, $discount, 2), $vat, 2),
            ];
            $itemsTotal = bcadd($itemsTotal, $amount, 2);
            $discountTotal = bcadd($discountTotal, $discount, 2);
            $vatTotal = bcadd($vatTotal, $vat, 2);
        }

        $shippingTotal = bcadd($shippingPrice, '0', 2);

        return [
            'vat_rate' => $vatRate,
            'items_total' => $itemsTotal,
            'discount_total' => $discountTotal,
            'vat_total' => $vatTotal,
            'shipping_total' => $shippingTotal,
            'total' => bcadd(bcadd(bcsub($itemsTotal, $discountTotal, 2), $vatTotal, 2), $shippingTotal, 2),
            'lines' => $priced,
        ];
    }

    /**
     * Each line's share of the Coupon's discount: a percentage of the eligible lines
     * (rounded to the cent) or a fixed amount (at most what they are worth), spread in
     * proportion to each eligible line's value. Shares are cut to the cent; the cents left
     * over go to the largest line (then the next largest, never beyond a line's own value).
     *
     * @param  list<array{quantity: int, unit_price: string, taxable: bool, coupon_eligible?: bool}>  $lines
     * @param  list<string>  $amounts
     * @param  array{type: CouponType, value: string}|null  $coupon
     * @return list<string>
     */
    private function discounts(array $lines, array $amounts, ?array $coupon): array
    {
        $discounts = array_fill(0, count($lines), '0.00');
        $eligible = array_keys(array_filter($lines, fn (array $line): bool => $line['coupon_eligible'] ?? false));
        $eligibleTotal = '0.00';
        foreach ($eligible as $index) {
            $eligibleTotal = bcadd($eligibleTotal, $amounts[$index], 2);
        }

        if ($coupon === null || bccomp($eligibleTotal, '0', 2) <= 0) {
            return $discounts;
        }

        $discount = match ($coupon['type']) {
            CouponType::Percentage => $this->roundToCents(bcdiv(bcmul($eligibleTotal, $coupon['value'], self::SCALE), '100', self::SCALE)),
            CouponType::Fixed => bccomp($coupon['value'], $eligibleTotal, 2) > 0 ? $eligibleTotal : bcadd($coupon['value'], '0', 2),
        };

        $spread = '0.00';
        foreach ($eligible as $index) {
            $discounts[$index] = bcdiv(bcmul($discount, $amounts[$index], self::SCALE), $eligibleTotal, 2);
            $spread = bcadd($spread, $discounts[$index], 2);
        }

        $remainder = bcsub($discount, $spread, 2);
        usort($eligible, fn (int $a, int $b): int => bccomp($amounts[$b], $amounts[$a], 2) ?: $a <=> $b);
        foreach ($eligible as $index) {
            if (bccomp($remainder, '0', 2) <= 0) {
                break;
            }
            $room = bcsub($amounts[$index], $discounts[$index], 2);
            $extra = bccomp($room, $remainder, 2) < 0 ? $room : $remainder;
            $discounts[$index] = bcadd($discounts[$index], $extra, 2);
            $remainder = bcsub($remainder, $extra, 2);
        }

        return $discounts;
    }

    /** Half up to two decimals (amounts here are never negative). */
    private function roundToCents(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }
}
