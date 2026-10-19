<?php

namespace App\Http\Controllers\Notifications;

use App\Actions\Notifications\MarkAllNotificationsAsRead;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

class ReadAllNotificationsController extends Controller
{
    /**
     * Marks every one of the signed-in User's notifications as read.
     */
    public function store(#[CurrentUser] User $user, MarkAllNotificationsAsRead $markAllAsRead): Response
    {
        $markAllAsRead->handle($user);

        return response()->noContent();
    }
}
