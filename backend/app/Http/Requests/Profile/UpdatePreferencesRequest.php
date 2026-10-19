<?php

namespace App\Http\Requests\Profile;

use App\Enums\Locale;
use App\Enums\Theme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The signed-in User's own language and theme. Every User may change their own, so
 * being signed in is the only requirement.
 */
class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required_without:theme', Rule::enum(Locale::class)],
            'theme' => ['required_without:locale', Rule::enum(Theme::class)],
        ];
    }

    public function locale(): ?Locale
    {
        return $this->enum('locale', Locale::class);
    }

    public function theme(): ?Theme
    {
        return $this->enum('theme', Theme::class);
    }
}
