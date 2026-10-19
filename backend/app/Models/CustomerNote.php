<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use Database\Factories\CustomerNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An internal note a User wrote about a Customer. Notes are a record of what was said and
 * when, so they are only ever added, never edited.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $user_id the author; null once that User is gone
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read User|null $author
 */
#[Fillable(['body'])]
class CustomerNote extends Model implements Auditable
{
    use Audited;

    /** @use HasFactory<CustomerNoteFactory> */
    use HasFactory;

    public const int MAX_LENGTH = 2000;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The Customer the note is about. */
    public function auditLabel(): string
    {
        return $this->customer->name;
    }

    /**
     * The author is already the entry's User.
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['customer_id', 'user_id'];
    }
}
