<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * One requested change to one Variant's balance in one Warehouse, handed to
 * {@see Stock::move()}. Deltas are signed: +5 On-hand receives five units, −2 Reserved
 * releases two.
 */
final readonly class StockChange
{
    public function __construct(
        public int $variantId,
        public int $warehouseId,
        public int $onHandDelta = 0,
        public int $reservedDelta = 0,
    ) {
        if ($onHandDelta === 0 && $reservedDelta === 0) {
            throw new InvalidArgumentException('A stock change must change On-hand or Reserved.');
        }
    }
}
