<?php

use App\Enums\Role;

it('ends the session when the User signs out', function () {
    userWithRole(Role::Admin, ['email' => 'ahmed@inkcode.test', 'password' => 'secret-pass-1']);
    $this->postJson('/api/v1/auth/login', ['email' => 'ahmed@inkcode.test', 'password' => 'secret-pass-1'])->assertOk();

    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    // Tests reuse one app instance; a real next request starts with fresh guards.
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('does not let a guest sign out', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});
