<?php

use App\Enums\Locale;
use App\Enums\Role;
use App\Enums\Theme;
use App\Models\User;

it('signs a User in and keeps them signed in for later requests', function () {
    userWithRole(Role::Sales, ['name' => 'Lina Haddad', 'email' => 'lina@inkcode.test', 'password' => 'secret-pass-1']);

    $this->postJson('/api/v1/auth/login', ['email' => 'lina@inkcode.test', 'password' => 'secret-pass-1'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Lina Haddad')
        ->assertJsonPath('data.email', 'lina@inkcode.test')
        ->assertJsonPath('data.role', 'sales');

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'lina@inkcode.test');
});

it('describes the current User with their Role, permissions and preferences', function () {
    $viewer = userWithRole(Role::Viewer, ['locale' => Locale::Ar, 'theme' => Theme::Dark]);

    $this->actingAs($viewer)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $viewer->id,
            'name' => $viewer->name,
            'email' => $viewer->email,
            'role' => 'viewer',
            'permissions' => [
                'catalog.view',
                'coupons.view',
                'customers.view',
                'dashboard.view',
                'inventory.view',
                'orders.view',
                'payments.view',
                'reports.view',
                'returns.view',
            ],
            'preferences' => ['locale' => 'ar', 'theme' => 'dark'],
        ]]);
});

it('rejects a wrong password with a field error on email and stays signed out', function () {
    userWithRole(Role::Sales, ['email' => 'lina@inkcode.test', 'password' => 'secret-pass-1']);

    $this->postJson('/api/v1/auth/login', ['email' => 'lina@inkcode.test', 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('requires an email and a password', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['email', 'password']);
});

it('requires the email to look like an email address', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email', 'password' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('refuses a deactivated User even with the right password', function () {
    User::factory()->inactive()->withRole(Role::Sales)->create(['email' => 'gone@inkcode.test', 'password' => 'secret-pass-1']);

    $this->postJson('/api/v1/auth/login', ['email' => 'gone@inkcode.test', 'password' => 'secret-pass-1'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Your access has been deactivated. Ask an Admin to reactivate it.');

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('refuses a User who has not been given a Role yet', function () {
    User::factory()->create(['email' => 'new@inkcode.test', 'password' => 'secret-pass-1']);

    $this->postJson('/api/v1/auth/login', ['email' => 'new@inkcode.test', 'password' => 'secret-pass-1'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', "You don't have a Role yet. Ask an Admin to assign one.");

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('answers validation errors in Arabic when the SPA asks for Arabic', function () {
    $this->withHeader('Accept-Language', 'ar')
        ->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'حقل البريد الإلكتروني مطلوب.');
});

it('gives a new User English and the light theme by default', function () {
    $user = User::factory()->create()->refresh();

    expect($user->locale)->toBe(Locale::En)
        ->and($user->theme)->toBe(Theme::Light);
});
