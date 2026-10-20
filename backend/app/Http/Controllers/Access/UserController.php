<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\CreateUser;
use App\Actions\Access\ListUsers;
use App\Actions\Access\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\ListUsersRequest;
use App\Http\Requests\Access\StoreUserRequest;
use App\Http\Requests\Access\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(ListUsersRequest $request, ListUsers $listUsers): AnonymousResourceCollection
    {
        return UserResource::collection($listUsers->handle($request->listParameters()));
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): JsonResponse
    {
        $user = $createUser->handle($request->details(), $request->role(), $request->string('password')->toString());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,user`).
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new UserResource($updateUser->handle($actor, $user, $request->details(), $request->role()));
    }
}
