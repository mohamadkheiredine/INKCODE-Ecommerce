<?php

namespace App\Http\Requests\Access;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A User's new name, email and Role. The password is never set here: the User changes
 * their own in their profile.
 */
class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        $user = $this->editedUser();

        return $user !== null && ($this->user()?->can('update', $user) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_diff_key(parent::rules(), ['password' => true]);
    }
}
