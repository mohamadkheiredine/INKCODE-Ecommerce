<?php

namespace App\Http\Requests\Shipping;

use App\Models\Carrier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new Carrier: a unique name and an optional tracking link template, an http(s) address
 * holding {tracking_number} where a Shipment's tracking number goes, for example
 * "https://www.aramex.com/track/results?ShipmentNumber={tracking_number}".
 */
class StoreCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Carrier::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('carriers', 'name')->ignore($this->carrier())],
            'tracking_url_template' => ['nullable', 'string', 'max:500', $this->validTrackingTemplate(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['tracking_url_template' => __('shipping.attributes.tracking_url_template')];
    }

    /**
     * The Carrier being edited; none while creating one.
     */
    public function carrier(): ?Carrier
    {
        $carrier = $this->route('carrier');

        return $carrier instanceof Carrier ? $carrier : null;
    }

    /**
     * @return array{name: string, tracking_url_template: string|null}
     */
    public function values(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'tracking_url_template' => $this->filled('tracking_url_template')
                ? $this->string('tracking_url_template')->trim()->toString()
                : null,
        ];
    }

    /**
     * @param  Closure(string): mixed  $fail
     */
    private function validTrackingTemplate(string $attribute, mixed $value, Closure $fail): void
    {
        $template = is_string($value) ? trim($value) : '';
        $example = str_replace(Carrier::TRACKING_PLACEHOLDER, 'AB123', $template);
        $scheme = parse_url($example, PHP_URL_SCHEME);

        if (! str_contains($template, Carrier::TRACKING_PLACEHOLDER)
            || filter_var($example, FILTER_VALIDATE_URL) === false
            || ! in_array($scheme, ['http', 'https'], true)) {
            $fail(__('shipping.carriers.tracking_template_format', ['placeholder' => Carrier::TRACKING_PLACEHOLDER]));
        }
    }
}
