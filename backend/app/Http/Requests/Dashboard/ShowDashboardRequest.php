<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The dashboard for a date range (`from` and `to`, both days included, at most a year);
 * the last 30 days up to today when none is given. Roles with dashboard.view.
 */
class ShowDashboardRequest extends FormRequest
{
    public const int MAX_DAYS = 366;

    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::DashboardView->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['required_with:to', 'date_format:Y-m-d'],
            'to' => ['required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->from()->diffInDays($this->to()) + 1 > self::MAX_DAYS) {
                $validator->errors()->add('to', __('dashboard.range_too_long', ['days' => self::MAX_DAYS]));
            }
        }];
    }

    public function from(): CarbonImmutable
    {
        return $this->filled('from')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->string('from')->toString()) ?: CarbonImmutable::today()
            : CarbonImmutable::today()->subDays(29);
    }

    public function to(): CarbonImmutable
    {
        return $this->filled('to')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->string('to')->toString()) ?: CarbonImmutable::today()
            : CarbonImmutable::today();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => __('dashboard.attributes.from'), 'to' => __('dashboard.attributes.to')];
    }
}
