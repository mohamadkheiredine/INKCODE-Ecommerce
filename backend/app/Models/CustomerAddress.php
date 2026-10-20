<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One of a Customer's addresses. Among a Customer's addresses exactly one is the default
 * billing address and exactly one the default shipping address (possibly the same one);
 * only the address actions change those flags.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $label
 * @property string $recipient_name
 * @property string|null $phone
 * @property string $country_code ISO 3166-1 alpha-2
 * @property string $city
 * @property string $line1
 * @property string|null $line2
 * @property string|null $postal_code
 * @property bool $is_default_billing
 * @property bool $is_default_shipping
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 */
#[Fillable(['label', 'recipient_name', 'phone', 'country_code', 'city', 'line1', 'line2', 'postal_code', 'is_default_billing', 'is_default_shipping'])]
#[Hidden(['default_billing_customer_id', 'default_shipping_customer_id'])]
class CustomerAddress extends Model implements Auditable
{
    use Audited;

    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    /**
     * The table's defaults, so a new address (and its audit entry) has them before being reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default_billing' => false,
        'is_default_shipping' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default_billing' => 'boolean',
            'is_default_shipping' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** "Home · Rana Haddad": the address and whose it is. */
    public function auditLabel(): string
    {
        return "{$this->label} · {$this->customer->name}";
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['customer_id'];
    }
}
