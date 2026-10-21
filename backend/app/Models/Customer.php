<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentOutcome;
use App\Enums\Segment;
use App\Models\Concerns\Audited;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
 * @property-read string|null $lifetime_value only when loaded {@see withValue()}
 * @property-read Carbon|null $last_order_at only when loaded {@see withValue()}
 * @property-read Segment|null $segment only when loaded {@see withValue()}
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifetime_value' => 'decimal:2',
            'last_order_at' => 'datetime',
            'segment' => Segment::class,
        ];
    }

    /**
     * Adds each Customer's Lifetime value (the Revenue of their Counted orders minus the
     * Refunds in those Orders' Payment ledgers), the date of their last Counted order and
     * their Segment, all computed in SQL so a list can filter and sort by them like any
     * other column. Read-only: a Customer loaded this way is never saved.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withValue(Builder $query): void
    {
        $counted = array_map(fn (OrderStatus $status): string => $status->value, OrderStatus::counted());

        $orders = DB::table('orders')
            ->whereIn('status', $counted)
            ->groupBy('customer_id')
            ->select('customer_id')
            ->selectRaw('SUM(total) AS revenue, MIN(placed_at) AS first_order_at, MAX(placed_at) AS last_order_at');

        $refunds = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->whereIn('orders.status', $counted)
            ->where('payments.kind', PaymentKind::Refund->value)
            ->where('payments.status', PaymentOutcome::Succeeded->value)
            ->groupBy('orders.customer_id')
            ->select('orders.customer_id')
            ->selectRaw('SUM(payments.amount) AS refunded');

        $lifetimeValue = 'COALESCE(counted_orders.revenue, 0) - COALESCE(refunds.refunded, 0)';
        [$segment, $bindings] = Segment::sqlCase('counted_orders.first_order_at', 'counted_orders.last_order_at', $lifetimeValue, now());

        $values = DB::table('customers')
            ->leftJoinSub($orders, 'counted_orders', 'counted_orders.customer_id', '=', 'customers.id')
            ->leftJoinSub($refunds, 'refunds', 'refunds.customer_id', '=', 'customers.id')
            ->select('customers.*', 'counted_orders.last_order_at')
            ->selectRaw("$lifetimeValue AS lifetime_value")
            ->selectRaw("$segment AS segment", $bindings);

        $query->fromSub($values, 'customers');
    }

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
