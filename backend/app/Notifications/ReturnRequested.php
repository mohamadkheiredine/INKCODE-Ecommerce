<?php

namespace App\Notifications;

use App\Enums\NotificationType;

/**
 * A new Return was Requested; sent to every active Support User.
 */
class ReturnRequested extends InAppNotification
{
    public function __construct(
        private readonly int $returnId,
        private readonly string $number,
        private readonly string $orderNumber,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::ReturnRequested;
    }

    /**
     * @return array<string, string|int>
     */
    protected function messageParameters(object $notifiable): array
    {
        return ['number' => $this->number, 'order' => $this->orderNumber];
    }

    protected function link(): string
    {
        return "/returns/$this->returnId";
    }
}
