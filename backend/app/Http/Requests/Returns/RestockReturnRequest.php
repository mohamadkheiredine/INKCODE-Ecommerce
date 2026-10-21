<?php

namespace App\Http\Requests\Returns;

use App\Enums\Permission;
use App\Enums\RestockDecision;
use App\Models\OrderReturn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Restock decisions for some lines of a Received Return (returns.receive): each line
 * once, back into stock or a Write-off.
 */
class RestockReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::ReturnsReceive->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $return = $this->route('orderReturn');

        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['array:line_id,decision'],
            'lines.*.line_id' => ['required', 'integer', 'distinct', Rule::exists('return_lines', 'id')->where('return_id', $return instanceof OrderReturn ? $return->id : 0)],
            'lines.*.decision' => ['required', Rule::enum(RestockDecision::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['lines.*.line_id.exists' => __('returns.line_not_on_return')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lines' => __('returns.attributes.lines'),
            'lines.*.line_id' => __('returns.attributes.line_id'),
            'lines.*.decision' => __('returns.attributes.decision'),
        ];
    }

    /**
     * @return array<int, RestockDecision> per Return line id
     */
    public function decisions(): array
    {
        /** @var array<int, array{line_id: int|string, decision: string}> $lines */
        $lines = $this->validated('lines');

        $decisions = [];
        foreach ($lines as $line) {
            $decisions[(int) $line['line_id']] = RestockDecision::from($line['decision']);
        }

        return $decisions;
    }
}
