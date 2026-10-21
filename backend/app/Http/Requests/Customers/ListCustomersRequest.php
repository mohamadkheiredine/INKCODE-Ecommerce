<?php

namespace App\Http\Requests\Customers;

use App\Enums\Segment;
use App\Http\Requests\ListRequest;
use App\Models\Customer;
use Illuminate\Validation\Rule;

/**
 * The Customers list: search by name, email or phone; filter by a Tag's id, Segment, a
 * Lifetime value range and a range of days of the last Counted order; sort by name, date
 * added, Lifetime value or last Order date (newest first by default).
 */
class ListCustomersRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Customer::class) ?? false;
    }

    protected function sorts(): array
    {
        return ['name', 'created_at', 'lifetime_value', 'last_order_at'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function filters(): array
    {
        return [
            'tag' => ['integer'],
            'segment' => [Rule::enum(Segment::class)],
            'lifetime_value_min' => ['decimal:0,2'],
            'lifetime_value_max' => ['decimal:0,2', ...($this->filled('filter.lifetime_value_min') ? ['gte:filter.lifetime_value_min'] : [])],
            'last_order_from' => ['date_format:Y-m-d'],
            'last_order_to' => ['date_format:Y-m-d', ...($this->filled('filter.last_order_from') ? ['after_or_equal:filter.last_order_from'] : [])],
        ];
    }
}
