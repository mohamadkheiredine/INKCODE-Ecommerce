<?php

namespace App\Enums;

/**
 * Whether a Payment ledger row moved money. Only a Card charge the gateway declines is
 * Failed; it stays in the ledger but counts toward nothing.
 */
enum PaymentOutcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
