<?php

namespace App\Enums;

/**
 * Where a Return is: Requested → Approved or Rejected; Approved → Received (the items
 * arrived) → Completed (every item has its Restock decision and the Refund, if any, is
 * recorded). This enum is the one place the allowed moves are defined.
 */
enum ReturnStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Received = 'received';
    case Completed = 'completed';

    public function canMoveTo(self $status): bool
    {
        return in_array($status, match ($this) {
            self::Requested => [self::Approved, self::Rejected],
            self::Approved => [self::Received],
            self::Received => [self::Completed],
            self::Rejected, self::Completed => [],
        }, true);
    }
}
