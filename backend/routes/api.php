<?php

use App\Enums\Permission;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\DemoAccountController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Catalog\AttributeController;
use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\BrandLogoController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductBulkActionController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProductImageController;
use App\Http\Controllers\Catalog\ProductImageOrderController;
use App\Http\Controllers\Catalog\VariantController;
use App\Http\Controllers\Catalog\VariantGenerationController;
use App\Http\Controllers\Customers\CustomerAddressController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\CustomerNoteController;
use App\Http\Controllers\Customers\TagController;
use App\Http\Controllers\Inventory\ReceiptController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Notifications\ReadAllNotificationsController;
use App\Http\Controllers\Notifications\ReadNotificationController;
use App\Http\Controllers\Profile\PasswordController;
use App\Http\Controllers\Profile\PreferencesController;
use App\Http\Controllers\Settings\StoreLogoController;
use App\Http\Controllers\Settings\StoreProfileController;
use App\Http\Controllers\Settings\StoreSettingsController;
use App\Http\Controllers\Shipping\CarrierController;
use App\Http\Controllers\Shipping\ShippingMethodController;
use App\Http\Controllers\Shipping\ShippingQuoteController;
use App\Http\Controllers\Shipping\ShippingZoneController;
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
    Route::apiResource('attributes', AttributeController::class)
        ->middlewareFor('show', 'can:view,attribute')
        ->middlewareFor('destroy', 'can:delete,attribute');
    Route::post('/products/bulk', [ProductBulkActionController::class, 'store'])->name('products.bulk');
    Route::apiResource('products', ProductController::class)
        ->middlewareFor('show', 'can:view,product')
        ->middlewareFor('destroy', 'can:delete,product');
    Route::post('/products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
    Route::put('/products/{product}/images/order', [ProductImageOrderController::class, 'update'])->name('products.images.reorder');
    Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
        ->scopeBindings()
        ->can('update', 'product')
        ->name('products.images.destroy');
    Route::post('/products/{product}/variants/generate', [VariantGenerationController::class, 'store'])
        ->name('products.variants.generate');
    Route::put('/products/{product}/variants', [VariantController::class, 'update'])->name('products.variants.update');
    Route::delete('/products/{product}/variants/{variant}', [VariantController::class, 'destroy'])
        ->scopeBindings()
        ->can('update', 'product')
        ->name('products.variants.destroy');

    // Customers: Roles with customers.view look; Policies let only customers.manage change a
    // Customer, their addresses, Tags and notes.
    Route::get('/customer-tags', [TagController::class, 'index'])->name('customer-tags.index');
    Route::apiResource('customers', CustomerController::class)
        ->middlewareFor('show', 'can:view,customer')
        ->middlewareFor('destroy', 'can:delete,customer');
    Route::apiResource('customers.addresses', CustomerAddressController::class)
        ->only(['store', 'update', 'destroy'])
        ->scoped()
        ->middlewareFor('destroy', 'can:update,customer');
    Route::apiResource('customers.notes', CustomerNoteController::class)->only(['index', 'store']);

    // Shipping: Roles that see Orders may look; Policies let only shipping.manage change.
    // The quote is for Roles that create Orders (QuoteShippingRequest).
    Route::get('/shipping/quote', [ShippingQuoteController::class, 'show'])->name('shipping.quote');
    Route::apiResource('shipping-zones', ShippingZoneController::class)
        ->middlewareFor('show', 'can:view,shipping_zone')
        ->middlewareFor('destroy', 'can:delete,shipping_zone');
    Route::post('/shipping-zones/{shipping_zone}/methods', [ShippingMethodController::class, 'store'])->name('shipping-zones.methods.store');
    Route::put('/shipping-methods/{shipping_method}', [ShippingMethodController::class, 'update'])->name('shipping-methods.update');
    Route::delete('/shipping-methods/{shipping_method}', [ShippingMethodController::class, 'destroy'])
        ->can('delete', 'shipping_method')
        ->name('shipping-methods.destroy');
    Route::apiResource('carriers', CarrierController::class)
        ->middlewareFor('show', 'can:view,carrier')
        ->middlewareFor('destroy', 'can:delete,carrier');

    // Inventory: Roles with inventory.view look; warehouses.manage changes Warehouses and
    // inventory.receive records Receipts (safe to retry with an Idempotency-Key).
    Route::apiResource('warehouses', WarehouseController::class)
        ->except('destroy')
        ->middlewareFor('show', 'can:view,warehouse');
    Route::get('/stock-levels', [StockController::class, 'levels'])->name('stock-levels.index');
    Route::get('/stock-movements', [StockController::class, 'movements'])->name('stock-movements.index');
    Route::apiResource('receipts', ReceiptController::class)
        ->only(['index', 'store', 'show'])
        ->middlewareFor('show', 'can:view,receipt')
        ->middlewareFor('store', 'idempotent');

    // Settings: only the Admin (settings.manage) sees or changes them all.
    Route::get('/settings', [StoreSettingsController::class, 'show'])
        ->can(Permission::SettingsManage->value)
        ->name('settings.show');
    Route::put('/settings', [StoreSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/logo', [StoreLogoController::class, 'store'])->name('settings.logo.store');
    Route::delete('/settings/logo', [StoreLogoController::class, 'destroy'])
        ->can(Permission::SettingsManage->value)
        ->name('settings.logo.destroy');

    // The public part of the settings (name, logo, Store currency): every signed-in User.
    Route::get('/store', [StoreProfileController::class, 'show'])->name('store.show');

    Route::get('/roles', [RoleController::class, 'index'])
        ->can(Permission::UsersManage->value)
        ->name('roles.index');
});
