<?php

namespace App\Enums;

/**
 * A Transfer's two steps: Draft → In transit (sent) → Received, or Draft → Cancelled.
 */
enum TransferStatus: string
{
    case Draft = 'draft';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';
}
