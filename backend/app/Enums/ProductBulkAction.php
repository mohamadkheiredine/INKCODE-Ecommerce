<?php

namespace App\Enums;

/**
 * What the Products list can do to several Products at once.
 */
enum ProductBulkAction: string
{
    case Activate = 'activate';
    case Archive = 'archive';
    /** Deletes the Products that may be deleted and skips the ones on Orders. */
    case Delete = 'delete';

    /**
     * The status the action sets; none for deleting.
     */
    public function status(): ?ProductStatus
    {
        return match ($this) {
            self::Activate => ProductStatus::Active,
            self::Archive => ProductStatus::Archived,
            self::Delete => null,
        };
    }
}
