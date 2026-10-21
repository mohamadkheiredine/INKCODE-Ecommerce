<?php

namespace App\Contracts;

use App\Enums\CardTestOutcome;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\MockCardGateway;

/**
 * Charges and refunds Cards. The store only ever talks to a gateway through this, so a
 * real provider can replace the mock ({@see MockCardGateway})
 * without touching the Payment ledger. No card number ever reaches the API.
 */
interface PaymentGateway
{
    /**
     * @param  string  $amount  decimal string in the Store currency
     */
    public function charge(string $amount, string $currencyCode, CardTestOutcome $testOutcome): GatewayResult;

    /**
     * Gives back part or all of an approved charge.
     */
    public function refund(string $chargeReference, string $amount, string $currencyCode): GatewayResult;
}
