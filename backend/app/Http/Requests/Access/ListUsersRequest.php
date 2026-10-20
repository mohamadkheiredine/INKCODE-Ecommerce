<?php

namespace App\Http\Requests\Access;

use App\Enums\Role;
use App\Http\Requests\ListRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * The Users list: search by name or email, filter by Role and by status (`active` or
 * `deactivated`), sort by name, email or date (by name by default).
 */
class ListUsersRequest extends ListRequest
{
    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_DEACTIVATED = 'deactivated';

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    protected function sorts(): array
    {
        return ['name', 'email', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filters(): array
    {
        return [
            'role' => [Rule::enum(Role::class)],
            'status' => [Rule::in([self::STATUS_ACTIVE, self::STATUS_DEACTIVATED])],
        ];
    }
}
