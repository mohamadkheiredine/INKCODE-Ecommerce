<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

it('reports healthy', function () {
    $this->get('/up')->assertOk();
});

it('answers an unknown API route with the not_found envelope', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertExactJson([
            'message' => 'The requested resource was not found.',
            'code' => 'not_found',
        ]);
});

it('never leaks model or class names when a record is missing', function () {
    Route::middleware(['api', 'auth:sanctum'])->get('/api/v1/__test/users/{user}', fn (User $user) => $user->name);

    $this->actingAs(userWithRole(Role::Admin))
        ->getJson('/api/v1/__test/users/999999')
        ->assertNotFound()
        ->assertExactJson([
            'message' => 'The requested resource was not found.',
            'code' => 'not_found',
        ]);
});

it('passes a deliberate denial reason through to the SPA', function () {
    Gate::define('edit-locked-order', fn () => Response::deny('Orders can only be edited before preparation starts.'));
    Route::middleware(['api', 'auth:sanctum'])->get('/api/v1/__test/locked', fn () => 'ok')->can('edit-locked-order');

    $this->actingAs(userWithRole(Role::Admin))
        ->getJson('/api/v1/__test/locked')
        ->assertForbidden()
        ->assertExactJson([
            'message' => 'Orders can only be edited before preparation starts.',
            'code' => 'forbidden',
        ]);
});

it('answers a wrong HTTP method with the method_not_allowed envelope', function () {
    $this->getJson('/api/v1/auth/login')
        ->assertMethodNotAllowed()
        ->assertExactJson([
            'message' => 'This method is not supported for this resource.',
            'code' => 'method_not_allowed',
        ]);
});

it('answers errors in Arabic when the SPA asks for Arabic', function () {
    $this->withHeader('Accept-Language', 'ar')
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'يجب تسجيل الدخول أولاً.', 'code' => 'unauthenticated']);
});

it('hands the SPA a CSRF cookie before it signs in', function () {
    $this->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});

it('answers a guest calling a protected route with the unauthenticated envelope', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertExactJson([
            'message' => 'Unauthenticated.',
            'code' => 'unauthenticated',
        ]);
});
