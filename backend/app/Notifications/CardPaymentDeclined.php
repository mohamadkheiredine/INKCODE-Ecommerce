<?php

namespace App\Notifications;

use App\Enums\NotificationType;

/**
 * The mock Card gateway declined a charge on an Order; sent to every active Finance User.
 */
class CardPaymentDeclined extends InAppNotification
{
    public function __construct(
        private readonly int $orderId,
        private readonly string $number,
        private readonly string $amount,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::PaymentDeclined;
    }

    /**
     * @return array<string, string|int>
     */
    protected function messageParameters(object $notifiable): array
    {
        return ['number' => $this->number, 'amount' => $this->amount];
    }

    protected function link(): string
    {
        return "/orders/$this->orderId";
    }
}
