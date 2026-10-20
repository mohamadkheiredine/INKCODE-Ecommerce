<?php

namespace App\Enums;

/**
 * Why an Adjustment corrected stock. Values are stored, so cases are never renamed.
 */
enum AdjustmentReason: string
{
    case CountCorrection = 'count_correction';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Found = 'found';
}
