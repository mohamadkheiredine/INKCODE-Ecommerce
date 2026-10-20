<?php

namespace App\Notifications;

use App\Enums\NotificationType;

/**
 * A Variant's Available stock in a Warehouse dropped to its Low-stock threshold or below.
 */
class LowStock extends InAppNotification
{
    public function __construct(
        private readonly string $sku,
        private readonly string $warehouse,
        private readonly int $available,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::LowStock;
    }

    /**
     * @return array<string, string|int>
     */
    protected function messageParameters(object $notifiable): array
    {
        return ['sku' => $this->sku, 'warehouse' => $this->warehouse, 'available' => $this->available];
    }

    protected function link(): string
    {
        return '/inventory/stock?filter[low_stock]=1';
    }
}
