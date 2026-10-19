<?php

namespace App\Http\Responses;

use App\Exceptions\BusinessRuleViolation;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception raised under /api as the one JSON error envelope:
 * `message`, a stable machine-readable `code`, and `errors` for validation failures.
 * A {@see BusinessRuleViolation} brings its own code (`category_has_children`, …).
 */
final class ApiExceptionRenderer
{
    /** @var array<int, string> */
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        419 => 'csrf_token_mismatch',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        503 => 'service_unavailable',
    ];

    /**
     * Statuses whose framework messages are always replaced: they can expose routes,
     * HTTP methods or model class names, and never carry a reason meant for Users.
     *
     * @var array<int, string>
     */
    private const ALWAYS_TRANSLATED = [
        404 => 'errors.not_found',
        405 => 'errors.method_not_allowed',
        419 => 'errors.csrf_token_mismatch',
    ];

    /**
     * Laravel's default texts. Any other message was raised on purpose by the application
     * (for example a Policy's `Response::deny('reason')`) and is passed through unchanged.
     *
     * @var array<int, array{default: string, translation: string}>
     */
    private const DEFAULT_MESSAGES = [
        403 => ['default' => 'This action is unauthorized.', 'translation' => 'errors.forbidden'],
        429 => ['default' => 'Too Many Attempts.', 'translation' => 'errors.too_many_requests'],
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->respond(422, $e->getMessage(), $e->errors()),
            $e instanceof AuthenticationException => $this->respond(401, __('errors.unauthenticated')),
            $e instanceof BusinessRuleViolation => $this->respond($e->getStatusCode(), $e->getMessage(), code: $e->errorCode),
            $e instanceof HttpExceptionInterface => $this->respond(
                $e->getStatusCode(),
                $this->messageFor($e),
                headers: $e->getHeaders(),
            ),
            default => $this->respond(500, config('app.debug') ? $e->getMessage() : __('errors.server_error')),
        };
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     * @param  array<string, string>  $headers
     */
    private function respond(int $status, string $message, ?array $errors = null, array $headers = [], ?string $code = null): JsonResponse
    {
        $body = ['message' => $message, 'code' => $code ?? self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'http_error')];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return new JsonResponse($body, $status, $headers);
    }

    private function messageFor(HttpExceptionInterface $e): string
    {
        $status = $e->getStatusCode();
        $message = $e->getMessage();

        if (isset(self::ALWAYS_TRANSLATED[$status])) {
            return __(self::ALWAYS_TRANSLATED[$status]);
        }

        $default = self::DEFAULT_MESSAGES[$status] ?? null;
        if ($message === '' || ($default !== null && $message === $default['default'])) {
            return __($default['translation'] ?? 'errors.server_error');
        }

        return $message;
    }
}
