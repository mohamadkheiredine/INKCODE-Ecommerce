<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * A page of the signed-in User's own notifications, newest first, with how many of
     * all of them are still unread (for the bell's badge).
     */
    public function index(ListNotificationsRequest $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        return NotificationResource::collection($user->notifications()->paginate($request->perPage()))
            ->additional(['meta' => ['unread_count' => $user->unreadNotifications()->count()]]);
    }
}
