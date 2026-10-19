<?php

namespace App\Http\Controllers\Profile;

use App\Actions\Profile\ChangePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

class PasswordController extends Controller
{
    public function update(
        ChangePasswordRequest $request,
        #[CurrentUser] User $user,
        ChangePassword $changePassword,
    ): Response {
        $changePassword->handle($user, $request->string('password')->toString());

        return response()->noContent();
    }
}
