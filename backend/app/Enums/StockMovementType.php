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

    /** A correction with a reason (count correction, damaged, lost, found): adds or removes On-hand. */
    case Adjustment = 'adjustment';

    /** A sent Transfer leaving its source Warehouse: removes On-hand. */
    case TransferOut = 'transfer_out';

    /** A received Transfer arriving at its destination Warehouse: adds On-hand. */
    case TransferIn = 'transfer_in';
}
