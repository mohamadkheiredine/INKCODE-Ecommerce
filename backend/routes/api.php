<?php

use App\Enums\Permission;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\DemoAccountController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

// Every route in this file is served under /api/v1 (see bootstrap/app.php).

Route::post('/auth/login', [SessionController::class, 'store'])->name('auth.login');
Route::get('/auth/demo-accounts', [DemoAccountController::class, 'index'])->name('auth.demo-accounts');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [CurrentUserController::class, 'show'])->name('auth.me');
    Route::post('/auth/logout', [SessionController::class, 'destroy'])->name('auth.logout');

    Route::get('/roles', [RoleController::class, 'index'])
        ->can(Permission::UsersManage->value)
        ->name('roles.index');
});
