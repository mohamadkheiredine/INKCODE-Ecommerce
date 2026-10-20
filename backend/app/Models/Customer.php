<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A person the store sells to: a record Users keep, never someone who signs in. A
 * Customer has a name and an email, a phone or both.
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, CustomerAddress> $addresses
 */
#[Fillable(['name', 'email', 'phone'])]
class Customer extends Model implements Auditable
{
    use Audited {
        auditValues as private attributeAuditValues;
    }

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    /**
     * Default billing first, then default shipping, then the oldest.
     *
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)
            ->orderByDesc('is_default_billing')
            ->orderByDesc('is_default_shipping')
            ->orderBy('id');
    }

    /**
     * @return HasMany<CustomerNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    /**
     * The Customer's details and the names of their Tags, so tagging shows in the trail.
     *
     * @return array<string, mixed>
     */
    public function auditValues(): array
    {
        return [
            ...$this->attributeAuditValues(),
            'tags' => $this->tags()->pluck('name')->all(),
        ];
    }
}
