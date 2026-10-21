<?php

namespace App\Notifications;

use App\Enums\NotificationType;

/**
 * An Order was assigned to the User who receives this.
 */
class OrderAssigned extends InAppNotification
{
    public function __construct(
        private readonly int $orderId,
        private readonly string $number,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::OrderAssigned;
    }

    /**
     * @return array<string, string|int>
     */
    protected function messageParameters(object $notifiable): array
    {
        return ['number' => $this->number];
    }

    protected function link(): string
    {
        return "/orders/$this->orderId";
    }
}
