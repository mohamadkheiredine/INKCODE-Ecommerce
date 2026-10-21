<?php

namespace App\Enums;

/** A Payment ledger row either takes money in (payment) or gives it back (Refund). */
enum PaymentKind: string
{
    case Payment = 'payment';
    case Refund = 'refund';
}
