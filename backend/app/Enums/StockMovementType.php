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

    /** An Order was Confirmed: its units are promised to it (adds Reserved). */
    case Reservation = 'reservation';

    /** An Order holding Reserved units was cancelled: they are free again (removes Reserved). */
    case Release = 'release';
    /** An Order reached the Deduction stage: its Reserved units leave On-hand (removes both). */
    case Deduction = 'deduction';
    /** Units deducted for an Order were cancelled before it shipped: they are back on hand. */
    case CancellationReturn = 'cancellation_return';

    /** A correction with a reason (count correction, damaged, lost, found): adds or removes On-hand. */
    case Adjustment = 'adjustment';

    /** A sent Transfer leaving its source Warehouse: removes On-hand. */
    case TransferOut = 'transfer_out';

    /** A received Transfer arriving at its destination Warehouse: adds On-hand. */
    case TransferIn = 'transfer_in';
}
