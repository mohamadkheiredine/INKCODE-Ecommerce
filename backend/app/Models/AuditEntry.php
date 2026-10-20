<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Services\AuditTrail;
use Database\Factories\AuditEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One change a User made to a record: who, what, which record, the values before and
 * after, and when. Entries are written only by {@see AuditTrail} and never
 * change afterwards.
 *
 * @property int $id
 * @property int|null $user_id
 * @property AuditAction $action
 * @property string $record_type
 * @property int $record_id
 * @property string $record_label
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'action', 'record_type', 'record_id', 'record_label', 'before', 'after'])]
class AuditEntry extends Model
{
    /** @use HasFactory<AuditEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'record_id' => 'integer',
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Audit entries never change.'));
        static::deleting(fn (): never => throw new LogicException('Audit entries are never deleted.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
