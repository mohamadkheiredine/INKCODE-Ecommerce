<?php

namespace App\Enums;

/**
 * Why a Stock movement happened. Values are stored, so cases are added but never renamed
 * or removed. Each later ticket that changes stock adds its own case.
 */
enum StockMovementType: string
{
    /** Stock arriving from a supplier (a Receipt): adds On-hand. */
    case Receipt = 'receipt';
}
