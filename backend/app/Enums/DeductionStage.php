<?php

namespace App\Enums;

/**
 * The Order status at which Reserved units leave On-hand, chosen in Settings. The values
 * are the matching Order statuses, so the Order flow can compare the two directly.
 */
enum DeductionStage: string
{
    case Preparing = 'preparing';
    case ReadyForShipping = 'ready_for_shipping';
    case Shipped = 'shipped';
}
