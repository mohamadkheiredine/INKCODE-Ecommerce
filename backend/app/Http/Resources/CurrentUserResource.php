<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in User as the SPA needs them: identity, Role, permissions (only used
 * to hide UI; the API still checks every request) and display preferences.
 *
 * @mixin User
 */
class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role()?->value,
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'preferences' => [
                'locale' => $this->locale->value,
                'theme' => $this->theme->value,
            ],
        ];
    }
}
