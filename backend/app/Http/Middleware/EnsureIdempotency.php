<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessRuleViolation;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes a mutating endpoint safe to retry (ADR 0008). A request sent with an
 * `Idempotency-Key` header runs at most once per User and key: a retry gets the first
 * answer again (with `Idempotent-Replayed: true`) and changes nothing.
 *
 * The key is claimed in the same transaction as the request's own changes, so the
 * change and its stored answer commit together. A retry arriving while the first
 * request still runs waits on the key's row and then replays its answer. Only successful
 * answers are kept: after a refusal (422, 409…) the same key may be sent again. A key
 * reused for a different request is refused with 422 `idempotency_key_reused`.
 */
final class EnsureIdempotency
{
    public const string HEADER = 'Idempotency-Key';

    private const int MAX_KEY_LENGTH = 255;

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header(self::HEADER, '');
        if ($key === '') {
            return $next($request);
        }
        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw new BusinessRuleViolation('idempotency_key_invalid', __('errors.idempotency_key_invalid', ['max' => self::MAX_KEY_LENGTH]), 422);
        }

        $userId = (int) $request->user()?->getAuthIdentifier();
        $requestHash = hash('sha256', $request->method().' '.$request->path().' '.$request->getContent());

        DB::beginTransaction();

        try {
            $response = $this->claim($userId, $key, $requestHash) ? $next($request) : null;

            if ($response === null || ! $response->isSuccessful()) {
                DB::rollBack();
            } else {
                IdempotencyKey::query()->where(['user_id' => $userId, 'key' => $key])->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => (string) $response->getContent(),
                ]);
                DB::commit();
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        return $response ?? $this->replay(IdempotencyKey::query()->where(['user_id' => $userId, 'key' => $key])->sole(), $requestHash);
    }

    /**
     * Claims the key for this request. Waits while a concurrent request holds the same key;
     * false once that one has committed it.
     */
    private function claim(int $userId, string $key, string $requestHash): bool
    {
        return IdempotencyKey::query()->insertOrIgnore([
            'user_id' => $userId,
            'key' => $key,
            'request_hash' => $requestHash,
            'response_status' => 0,
            'response_body' => '',
        ]) === 1;
    }

    private function replay(IdempotencyKey $stored, string $requestHash): Response
    {
        if (! hash_equals($stored->request_hash, $requestHash)) {
            throw new BusinessRuleViolation('idempotency_key_reused', __('errors.idempotency_key_reused'), 422);
        }

        return response($stored->response_body, $stored->response_status, [
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }
}
