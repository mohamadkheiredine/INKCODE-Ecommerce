<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\DemoAccountResource;
use App\Support\DemoAccounts;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DemoAccountController extends Controller
{
    /**
     * The demo accounts the sign-in page may offer as one-click logins. Only exists in demo mode.
     */
    public function index(): AnonymousResourceCollection
    {
        abort_unless(config('app.demo_mode'), 404);

        return DemoAccountResource::collection(DemoAccounts::available());
    }
}
