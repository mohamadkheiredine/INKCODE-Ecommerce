<?php

it('offers one demo account per Role when demo data is seeded', function () {
    seedStore(demoMode: true);

    $this->getJson('/api/v1/auth/demo-accounts')
        ->assertOk()
        ->assertJsonPath('data.*.role', ['admin', 'operations', 'inventory', 'sales', 'support', 'finance', 'viewer'])
        ->assertJsonPath('data.*.email', [
            'admin@inkcode.test',
            'operations@inkcode.test',
            'inventory@inkcode.test',
            'sales@inkcode.test',
            'support@inkcode.test',
            'finance@inkcode.test',
            'viewer@inkcode.test',
        ]);
});

it('lets each demo account sign in with the documented password and its own Role', function (string $role) {
    seedStore(demoMode: true);

    $this->postJson('/api/v1/auth/login', ['email' => "{$role}@inkcode.test", 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.role', $role);
})->with(['admin', 'operations', 'inventory', 'sales', 'support', 'finance', 'viewer']);

it('keeps exactly seven demo accounts when demo data is seeded twice', function () {
    seedStore(demoMode: true);
    seedStore(demoMode: true);

    $this->getJson('/api/v1/auth/demo-accounts')->assertJsonCount(7, 'data');
});

it('creates no demo accounts and hides the list outside demo mode', function () {
    seedStore(demoMode: false);

    $this->getJson('/api/v1/auth/demo-accounts')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    $this->postJson('/api/v1/auth/login', ['email' => 'admin@inkcode.test', 'password' => 'password'])
        ->assertUnprocessable();
});

it('brings demo accounts back to their documented password, English and light when seeded again', function () {
    seedStore(demoMode: true);
    $this->postJson('/api/v1/auth/login', ['email' => 'support@inkcode.test', 'password' => 'password'])->assertOk();
    $this->patchJson('/api/v1/auth/me/preferences', ['locale' => 'ar', 'theme' => 'dark'])->assertOk();
    $this->putJson('/api/v1/auth/me/password', [
        'current_password' => 'password',
        'password' => 'Changed-in-review-1',
        'password_confirmation' => 'Changed-in-review-1',
    ])->assertNoContent();

    seedStore(demoMode: true);

    signInFromNewBrowser('support@inkcode.test', 'password')
        ->assertOk()
        ->assertJsonPath('data.preferences', ['locale' => 'en', 'theme' => 'light']);
});
