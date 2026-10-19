<?php

use App\Enums\Permission;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\DemoAccountController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\BrandLogoController;
use App\Http\Controllers\Catalog\CategoryController;
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

    // Catalog: every Role may look (catalog.view); Policies let only catalog.manage change.
    // Actions with a Form Request authorize there; the others through these Policy checks.
    Route::apiResource('categories', CategoryController::class)
        ->middlewareFor('show', 'can:view,category')
        ->middlewareFor('destroy', 'can:delete,category');
    Route::apiResource('brands', BrandController::class)
        ->middlewareFor('show', 'can:view,brand')
        ->middlewareFor('destroy', 'can:delete,brand');
    Route::post('/brands/{brand}/logo', [BrandLogoController::class, 'store'])->name('brands.logo.store');
    Route::delete('/brands/{brand}/logo', [BrandLogoController::class, 'destroy'])
        ->can('update', 'brand')
        ->name('brands.logo.destroy');

    Route::get('/roles', [RoleController::class, 'index'])
        ->can(Permission::UsersManage->value)
        ->name('roles.index');
});
