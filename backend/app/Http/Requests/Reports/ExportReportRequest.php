<?php

namespace App\Http\Requests\Reports;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * A report downloaded as `format` csv or xlsx, with the same filters as the screen.
 * Exporting is its own permission: Roles with both reports.view and reports.export.
 */
class ExportReportRequest extends ShowReportRequest
{
    public function authorize(): bool
    {
        return parent::authorize()
            && ($this->user()?->hasPermissionTo(Permission::ReportsExport->value) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'format' => ['required', Rule::in(['csv', 'xlsx'])],
        ];
    }

    public function exportFormat(): string
    {
        return $this->input('format') === 'xlsx' ? 'xlsx' : 'csv';
    }
}
