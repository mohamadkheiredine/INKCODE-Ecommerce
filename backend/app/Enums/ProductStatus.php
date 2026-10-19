<?php

namespace App\Enums;

/**
 * Where a Product stands in the catalog. A Product that appears on any Order can be
 * Archived but never deleted.
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
