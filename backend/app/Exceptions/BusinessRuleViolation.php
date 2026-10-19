<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A request the API understood but refuses because a business rule forbids it, for
 * example deleting a Category that still has children. It reaches the SPA as the error
 * envelope with its own stable `code` and a translated message (409 Conflict unless a
 * rule says otherwise).
 *
 *     throw new BusinessRuleViolation('category_has_children', __('catalog.categories.has_children', [...]));
 */
final class BusinessRuleViolation extends HttpException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        int $status = 409,
    ) {
        parent::__construct($status, $message);
    }
}
