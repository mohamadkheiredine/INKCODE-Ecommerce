<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * An automatic grouping of Customers by buying behaviour, from their Counted orders. A
 * Customer is in the first Segment that matches, checked in this order: New (first Order
 * in the last 30 days), VIP (Lifetime value of $1,000 or more), At risk (no Order for 90
 * days), Regular (everyone else, including a Customer with no Counted order yet).
 */
enum Segment: string
{
    case New = 'new';
    case Vip = 'vip';
    case AtRisk = 'at_risk';
    case Regular = 'regular';

    public const int NEW_FOR_DAYS = 30;

    public const string VIP_FROM = '1000.00';

    public const int AT_RISK_AFTER_DAYS = 90;

    /**
     * The SQL CASE that picks the Segment from the first and last Counted order dates and
     * the Lifetime value expression, with its bindings, as of the given moment.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public static function sqlCase(string $firstOrderAt, string $lastOrderAt, string $lifetimeValue, Carbon $now): array
    {
        return [
            "CASE WHEN $firstOrderAt >= ? THEN ? WHEN $lifetimeValue >= ? THEN ? WHEN $lastOrderAt <= ? THEN ? ELSE ? END",
            [
                $now->copy()->subDays(self::NEW_FOR_DAYS), self::New->value,
                self::VIP_FROM, self::Vip->value,
                $now->copy()->subDays(self::AT_RISK_AFTER_DAYS), self::AtRisk->value,
                self::Regular->value,
            ],
        ];
    }
}
