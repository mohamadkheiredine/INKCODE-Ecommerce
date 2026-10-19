<?php

namespace App\Http\Resources;

use App\Support\DemoAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One demo account the sign-in page may offer as a one-click login.
 *
 * @mixin DemoAccount
 */
class DemoAccountResource extends JsonResource
{
    /**
     * @return array{role: string, name: string, email: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->role->value,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
