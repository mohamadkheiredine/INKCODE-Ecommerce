<?php

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Marks one of a User's own notifications as read. Only that User's notifications are
 * searched, so another User's is "not found": its existence is never revealed. Marking
 * it again keeps the first time it was read.
 */
final class MarkNotificationAsRead
{
    /**
     * @throws ModelNotFoundException<DatabaseNotification> when the User has no such notification
     */
    public function handle(User $user, string $notificationId): DatabaseNotification
    {
        $notification = $user->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        return $notification;
    }
}
