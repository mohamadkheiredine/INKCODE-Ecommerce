<?php

namespace App\Http\Controllers\Notifications;

use App\Actions\Notifications\MarkNotificationAsRead;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class ReadNotificationController extends Controller
{
    /**
     * Marks one of the signed-in User's own notifications as read.
     */
    public function store(
        #[CurrentUser] User $user,
        string $notification,
        MarkNotificationAsRead $markAsRead,
    ): NotificationResource {
        return new NotificationResource($markAsRead->handle($user, $notification));
    }
}
