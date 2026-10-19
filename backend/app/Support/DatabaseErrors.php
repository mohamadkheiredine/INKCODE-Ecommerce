<?php

namespace App\Support;

use App\Exceptions\BusinessRuleViolation;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Turns the database's own refusals into the API's answers: a delete of a row other rows
 * still reference (MySQL 1451) and a duplicate unique value (MySQL 1062) that slipped
 * past validation because two saves happened at the same moment.
 */
final class DatabaseErrors
{
    private const int MYSQL_ROW_IS_REFERENCED = 1451;

    private const int MYSQL_DUPLICATE_ENTRY = 1062;

    public static function isRestrictViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === self::MYSQL_ROW_IS_REFERENCED;
    }

    /**
     * Runs a delete; if other records still reference the row, refuses with 409 `in_use`.
     *
     * @template T
     *
     * @param  Closure(): T  $delete
     * @return T
     */
    public static function refuseDeleteInUse(Closure $delete, string $message): mixed
    {
        try {
            return $delete();
        } catch (QueryException $exception) {
            if (! self::isRestrictViolation($exception)) {
                throw $exception;
            }

            throw new BusinessRuleViolation('in_use', $message);
        }
    }

    /**
     * Runs a save; a duplicate slug from a simultaneous save answers 422 on `slug`.
     *
     * @template T
     *
     * @param  Closure(): T  $save
     * @return T
     */
    public static function refuseDuplicateSlug(Closure $save): mixed
    {
        try {
            return $save();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== self::MYSQL_DUPLICATE_ENTRY) {
                throw $exception;
            }

            throw ValidationException::withMessages(['slug' => __('validation.unique', ['attribute' => 'slug'])]);
        }
    }

    /**
     * Runs a save; a duplicate on one of the named unique indexes, from a simultaneous
     * save, answers 422 on that index's field with its message.
     *
     * @template T
     *
     * @param  Closure(): T  $save
     * @param  array<string, array{string, string}>  $fieldsByIndex  index name => [field, message]
     * @return T
     */
    public static function refuseDuplicate(Closure $save, array $fieldsByIndex): mixed
    {
        try {
            return $save();
        } catch (QueryException $exception) {
            // MySQL names the index at the end: "… for key 'shipping_zones.shipping_zones_name_unique'".
            $detail = (string) ($exception->errorInfo[2] ?? '');
            if (($exception->errorInfo[1] ?? null) === self::MYSQL_DUPLICATE_ENTRY) {
                foreach ($fieldsByIndex as $index => [$field, $message]) {
                    if (str_contains($detail, "$index'")) {
                        throw ValidationException::withMessages([$field => $message]);
                    }
                }
            }

            throw $exception;
        }
    }
}
