<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * Recognises the database refusing a delete because other rows still reference the row
 * (MySQL error 1451, "Cannot delete or update a parent row").
 */
final class ForeignKeys
{
    private const int MYSQL_ROW_IS_REFERENCED = 1451;

    public static function isRestrictViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === self::MYSQL_ROW_IS_REFERENCED;
    }
}
