<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;

/**
 * Greets a demo account, so the bell has something to show from the first sign-in.
 */
class Welcome extends InAppNotification
{
    public function type(): NotificationType
    {
        return NotificationType::Welcome;
    }

    /**
     * @return array<string, string>
     */
    protected function messageParameters(object $notifiable): array
    {
        return ['name' => $notifiable instanceof User ? $notifiable->name : ''];
    }

    protected function link(): string
    {
        return '/profile';
    }
}
