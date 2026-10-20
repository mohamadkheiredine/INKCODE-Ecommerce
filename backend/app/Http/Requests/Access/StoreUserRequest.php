<?php

namespace App\Http\Requests\Access;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use LogicException;

/**
 * A new User: name, email, one Role and a temporary password the Admin hands over. The
 * password meets the same strength as any other ({@see Password::defaults()}); the User
 * changes it in their profile.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Input is already trimmed (TrimStrings); emails are kept in lowercase.
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->editedUser())],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * The User being edited; none while creating one.
     */
    public function editedUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array{name: string, email: string}
     */
    public function details(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
        ];
    }

    public function role(): Role
    {
        return $this->enum('role', Role::class) ?? throw new LogicException('The Role is validated before it is read.');
    }
}
