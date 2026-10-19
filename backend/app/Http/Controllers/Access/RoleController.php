<?php

namespace App\Http\Controllers\Access;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Role as RoleModel;

class RoleController extends Controller
{
    /**
     * The read-only Role → permission matrix, in the fixed Role order.
     */
    public function index(): AnonymousResourceCollection
    {
        $roles = RoleModel::query()
            ->with('permissions:id,name')
            ->whereIn('name', array_column(Role::cases(), 'value'))
            ->get()
            ->sortBy(fn (RoleModel $role): int => Role::from($role->name)->position())
            ->values();

        return RoleResource::collection($roles);
    }
}
