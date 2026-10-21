<?php

namespace App\Http\Requests\Orders;

use App\Actions\Orders\ListOrderAssignees;
use App\Models\Order;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Order's new Assignee (an active Admin, Operations or Sales User), or null to leave
 * it unassigned.
 */
class AssignOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('assign', $order) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assignee_id' => ['present', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_numeric($value) || ! ListOrderAssignees::query()->whereKey((int) $value)->exists()) {
                    $fail(__('orders.assignee_unavailable'));
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['assignee_id' => __('orders.attributes.assignee_id')];
    }

    public function assignee(): ?User
    {
        return $this->filled('assignee_id') ? User::query()->findOrFail($this->integer('assignee_id')) : null;
    }
}
