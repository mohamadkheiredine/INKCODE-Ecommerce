<?php

namespace App\Actions\Notifications;

use App\Models\User;

/**
 * Marks every unread notification of one User as read, in one query.
 */
final class MarkAllNotificationsAsRead
{
    public function handle(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
