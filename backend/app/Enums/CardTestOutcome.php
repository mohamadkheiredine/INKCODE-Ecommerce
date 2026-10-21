<?php

namespace App\Enums;

/**
 * The test option the mock Card gateway decides by: approve or decline the charge. It
 * stands in for the card details a real gateway would collect; none are ever taken.
 */
enum CardTestOutcome: string
{
    case Approve = 'approve';
    case Decline = 'decline';
}
