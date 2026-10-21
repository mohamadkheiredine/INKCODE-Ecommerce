<?php

namespace App\Enums;

/**
 * How the Sales report groups Counted orders: one row per day, per week (starting Monday)
 * or per month, each dated by its first day.
 */
enum SalesInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
}
