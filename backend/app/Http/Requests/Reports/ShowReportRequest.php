<?php

namespace App\Http\Requests\Reports;

use App\Enums\Permission;
use App\Enums\SalesInterval;
use App\Http\Requests\Dashboard\ShowDashboardRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * A report for a date range, read like the dashboard's (`from` and `to`, both days
 * included, at most a year; the last 30 days by default), plus the Sales report's
 * `interval` (day, week, month) and the Products report's `rank` (sales or units).
 * Roles with reports.view.
 */
class ShowReportRequest extends ShowDashboardRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::ReportsView->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'interval' => ['sometimes', Rule::enum(SalesInterval::class)],
            'rank' => ['sometimes', Rule::in(['sales', 'units'])],
        ];
    }

    public function salesInterval(): SalesInterval
    {
        return $this->enum('interval', SalesInterval::class) ?? SalesInterval::Day;
    }

    /**
     * @return 'sales'|'units'
     */
    public function rank(): string
    {
        return $this->input('rank') === 'units' ? 'units' : 'sales';
    }
}
