<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SignIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignInRequest;
use App\Http\Resources\CurrentUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    public function store(SignInRequest $request, SignIn $signIn): CurrentUserResource
    {
        $user = $signIn->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
            (string) $request->ip(),
            $request->session(),
        );

        return new CurrentUserResource($user);
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
