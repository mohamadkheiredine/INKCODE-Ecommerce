<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderStatus;
use App\Http\Requests\ListRequest;
use App\Models\Order;
use Illuminate\Validation\Rule;

/**
 * Orders: search the number or the Customer's name, email or phone; filter by status,
 * Fulfilling warehouse, Customer and a range of days placed; sort by date placed,
 * number or total; newest first by default.
 */
class ListOrdersRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Order::class) ?? false;
    }

    protected function sorts(): array
    {
        return ['placed_at', 'number', 'total'];
    }

    protected function defaultSort(): string
    {
        return '-placed_at';
    }

    protected function filters(): array
    {
        return [
            'status' => [Rule::enum(OrderStatus::class)],
            'warehouse' => ['integer', 'min:1'],
            'customer' => ['integer', 'min:1'],
            'from' => ['date_format:Y-m-d'],
            'to' => ['date_format:Y-m-d', ...($this->filled('filter.from') ? ['after_or_equal:filter.from'] : [])],
        ];
    }
}
