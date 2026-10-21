<?php

namespace App\Actions\Orders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Users an Order may be assigned to: active Admin, Operations and Sales Users.
 */
final class ListOrderAssignees
{
    /**
     * @return list<array{id: int, name: string}>
     */
    public function handle(): array
    {
        return self::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }

    /**
     * @return Builder<User>
     */
    public static function query(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', [Role::Admin->value, Role::Operations->value, Role::Sales->value]));
    }
}
