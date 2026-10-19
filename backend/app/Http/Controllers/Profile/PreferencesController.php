<?php

namespace App\Http\Controllers\Profile;

use App\Actions\Profile\UpdatePreferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePreferencesRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class PreferencesController extends Controller
{
    public function update(
        UpdatePreferencesRequest $request,
        #[CurrentUser] User $user,
        UpdatePreferences $updatePreferences,
    ): CurrentUserResource {
        return new CurrentUserResource(
            $updatePreferences->handle($user, $request->locale(), $request->theme()),
        );
    }
}
