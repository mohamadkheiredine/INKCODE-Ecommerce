<?php

use App\Enums\Permission;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\DemoAccountController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Notifications\ReadAllNotificationsController;
use App\Http\Controllers\Notifications\ReadNotificationController;
use App\Http\Controllers\Profile\PasswordController;
use App\Http\Controllers\Profile\PreferencesController;
use Illuminate\Support\Facades\Route;

// Every route in this file is served under /api/v1 (see bootstrap/app.php).

Route::post('/auth/login', [SessionController::class, 'store'])->name('auth.login');
Route::get('/auth/demo-accounts', [DemoAccountController::class, 'index'])->name('auth.demo-accounts');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [CurrentUserController::class, 'show'])->name('auth.me');
    Route::post('/auth/logout', [SessionController::class, 'destroy'])->name('auth.logout');

    // Every User manages their own profile (Profile controllers); being signed in is enough.
    Route::patch('/auth/me/preferences', [PreferencesController::class, 'update'])->name('auth.me.preferences');
    Route::put('/auth/me/password', [PasswordController::class, 'update'])
        ->middleware('throttle:password-change')
        ->name('auth.me.password');

    // Every User has their own notifications; these only ever touch the signed-in User's.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [ReadAllNotificationsController::class, 'store'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [ReadNotificationController::class, 'store'])
        ->whereUuid('notification')
        ->name('notifications.read');

    Route::get('/roles', [RoleController::class, 'index'])
        ->can(Permission::UsersManage->value)
        ->name('roles.index');
});
