<?php

namespace App\Models;

use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A completed request sent with an `Idempotency-Key`, kept so a retry of it gets the same
 * answer instead of running again ({@see EnsureIdempotency}).
 *
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string $request_hash
 * @property int $response_status
 * @property string $response_body
 * @property Carbon $created_at
 */
class IdempotencyKey extends Model
{
    public const null UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['response_status' => 'integer'];
    }
}
