<?php

namespace App\Enums;

/**
 * What a User did to a record, as kept in the audit trail. Values are stored, so cases
 * are added (status changes, Refunds…) but never renamed or removed.
 */
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    /** A User's password was replaced; the trail never stores password values. */
    case PasswordChanged = 'password_changed';
}
