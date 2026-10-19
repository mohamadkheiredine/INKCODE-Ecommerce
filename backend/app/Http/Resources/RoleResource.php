<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * @mixin RoleModel
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->name,
            'permissions' => $this->permissions->pluck('name')->sort()->values()->all(),
        ];
    }
}
