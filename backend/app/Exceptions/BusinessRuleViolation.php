<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A request the API understood but refuses because a business rule forbids it, for
 * example deleting a Category that still has children. It reaches the SPA as the error
 * envelope with its own stable `code` and a translated message (409 Conflict unless a
 * rule says otherwise), plus optional `details` the SPA can show (for example which
 * Order lines lack stock).
 *
 *     throw new BusinessRuleViolation('category_has_children', __('catalog.categories.has_children', [...]));
 */
final class BusinessRuleViolation extends HttpException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        int $status = 409,
        public readonly array $details = [],
    ) {
        parent::__construct($status, $message);
    }
}
