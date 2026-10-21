<?php

namespace App\Enums;

/**
 * What a notification is about. Stored in the `type` column and sent to the SPA, which
 * picks the icon from it; the message text comes from `lang/{en,ar}/notifications.php`.
 *
 * Every stored type comes from a case here (InAppNotification::type()), so a new type
 * adds a case and its two messages together. Never remove a case: stored rows use it.
 */
enum NotificationType: string
{
    case Welcome = 'welcome';

    case LowStock = 'low_stock';

    case OrderAssigned = 'order_assigned';

    /**
     * The message in the current language, filled with the values saved when it was sent.
     *
     * @param  array<string, string|int>  $parameters
     */
    public function message(array $parameters): string
    {
        return __("notifications.{$this->value}", $parameters);
    }
}
