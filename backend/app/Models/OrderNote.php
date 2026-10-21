<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A note a User added to an Order's timeline, in plain text. Notes are a record of what was
 * said and when, so they are only ever added, never edited. Locked orders still take notes.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $user_id the author; null once that User is gone
 * @property string $body
 * @property Carbon $created_at
 * @property-read Order $order
 * @property-read User|null $user
 */
#[Fillable(['body', 'user_id'])]
class OrderNote extends Model implements Auditable
{
    use Audited;

    public const int MAX_LENGTH = 2000;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditLabel(): string
    {
        return $this->order->number;
    }
}
