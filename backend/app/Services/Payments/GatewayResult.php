<?php

namespace App\Services\Payments;

/**
 * What a Payment gateway answered: approved or declined, with its own reference.
 */
final readonly class GatewayResult
{
    public function __construct(
        public bool $approved,
        public string $reference,
    ) {}
}
