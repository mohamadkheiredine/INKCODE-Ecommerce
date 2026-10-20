<?php

namespace App\Services;

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
 */
final class OrderPricing
{
    private const int SCALE = 6;

    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  list<array{variant: Variant, quantity: int}>  $items  Variants with their Product and Attribute values loaded
     * @return array{vat_rate: string, items_total: string, discount_total: string, vat_total: string, shipping_total: string, total: string, lines: list<array{variant_id: int, product_name: string, sku: string, options: list<array{attribute: string, value: string}>, quantity: int, unit_price: string, discount_amount: string, taxable: bool, vat_rate: string, vat_amount: string, line_total: string}>}
     */
    public function price(array $items, string $shippingPrice): array
    {
        $vatRate = $this->settings->vatRate();
        $lines = [];
        $itemsTotal = $discountTotal = $vatTotal = '0.00';

        foreach ($items as ['variant' => $variant, 'quantity' => $quantity]) {
            $amount = bcmul((string) $quantity, $variant->price, 2);
            $discount = '0.00';
            $taxable = $variant->product->taxable;
            $vat = $taxable ? $this->roundToCents(bcdiv(bcmul(bcsub($amount, $discount, 2), $vatRate, self::SCALE), '100', self::SCALE)) : '0.00';

            $lines[] = [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'sku' => $variant->sku,
                'options' => $variant->options(),
                'quantity' => $quantity,
                'unit_price' => $variant->price,
                'discount_amount' => $discount,
                'taxable' => $taxable,
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
            'lines' => $lines,
        ];
    }

    /** Half up to two decimals (amounts here are never negative). */
    private function roundToCents(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }
}
