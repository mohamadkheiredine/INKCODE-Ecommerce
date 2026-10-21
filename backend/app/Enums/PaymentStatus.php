<?php

namespace App\Enums;

use App\Models\Order;

/**
 * A summary read from an Order's Payment ledger, recomputed after every ledger write
 * ({@see derive()}). It is separate from the Order status.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    /**
     * Nothing captured is Unpaid. Once a Refund exists, the Order is Refunded when every
     * captured cent went back, else Partially refunded. Otherwise it is Paid when the
     * Captured amount covers what it owes, else Partially paid.
     *
     * @param  string  $captured  succeeded payments, decimal string
     * @param  string  $refunded  succeeded Refunds, decimal string
     * @param  string  $owed  what the Order owes ({@see Order::amountOwed()})
     */
    public static function derive(string $captured, string $refunded, string $owed): self
    {
        return match (true) {
            bccomp($captured, '0', 2) === 0 => self::Unpaid,
            bccomp($refunded, '0', 2) > 0 => bccomp($refunded, $captured, 2) >= 0 ? self::Refunded : self::PartiallyRefunded,
            bccomp($captured, $owed, 2) >= 0 => self::Paid,
            default => self::PartiallyPaid,
        };
    }
}
