<?php

namespace App\Actions\Access;

use App\Http\Requests\Access\ListUsersRequest;
use App\Models\User;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A page of Users with their Role.
 */
final class ListUsers
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = User::query()->with('roles');

        $parameters->search($query, ['name', 'email']);

        if ($parameters->hasFilter('role')) {
            $query->role((string) $parameters->filter('role'));
        }

        if ($parameters->hasFilter('status')) {
            $query->where('is_active', $parameters->filter('status') === ListUsersRequest::STATUS_ACTIVE);
        }

        return $parameters->paginate($query);
    }
}
