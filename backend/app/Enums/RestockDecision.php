<?php

namespace App\Enums;

/**
 * What happens to a Received Return line: back into On-hand at the Fulfilling warehouse,
 * or a Write-off (it never re-enters stock). Values are stored.
 */
enum RestockDecision: string
{
    case Restock = 'restock';
    case WriteOff = 'write_off';
}
