<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\DeactivateUser;
use App\Actions\Access\ReactivateUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Deactivating and reactivating a User. Both are authorized by the route (`can:update,user`).
 */
class UserActivationController extends Controller
{
    public function destroy(Request $request, User $user, DeactivateUser $deactivateUser): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new UserResource($deactivateUser->handle($actor, $user));
    }

    public function store(User $user, ReactivateUser $reactivateUser): UserResource
    {
        return new UserResource($reactivateUser->handle($user));
    }
}
