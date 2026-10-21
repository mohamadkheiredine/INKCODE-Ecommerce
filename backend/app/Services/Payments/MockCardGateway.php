<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\CardTestOutcome;
use Illuminate\Support\Str;

/**
 * The demo Payment gateway: it approves or declines a charge as its test option says and
 * always approves a Refund. It never asks for, sees or stores card details.
 */
final class MockCardGateway implements PaymentGateway
{
    public function charge(string $amount, string $currencyCode, CardTestOutcome $testOutcome): GatewayResult
    {
        return new GatewayResult($testOutcome === CardTestOutcome::Approve, 'mock_ch_'.Str::lower(Str::random(20)));
    }

    public function refund(string $chargeReference, string $amount, string $currencyCode): GatewayResult
    {
        return new GatewayResult(true, 'mock_re_'.Str::lower(Str::random(20)));
    }
}
