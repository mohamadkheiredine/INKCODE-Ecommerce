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
}
