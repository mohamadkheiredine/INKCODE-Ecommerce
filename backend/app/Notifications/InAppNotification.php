<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Notification;

/**
 * A notification shown in the User's bell. It stores the values its message needs, not
 * the text, so each User reads it in the language they use at the time, plus a link to
 * the record it is about (a path in the SPA).
 */
abstract class InAppNotification extends Notification
{
    abstract public function type(): NotificationType;

    /**
     * @return array<string, string|int>
     */
    abstract protected function messageParameters(object $notifiable): array;

    abstract protected function link(): string;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type()->value;
    }

    /**
     * @return array{parameters: array<string, string|int>, link: string}
     */
    public function toDatabase(object $notifiable): array
    {
        return ['parameters' => $this->messageParameters($notifiable), 'link' => $this->link()];
    }
}
