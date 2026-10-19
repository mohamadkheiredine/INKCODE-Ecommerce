<?php

use App\Actions\Profile\ChangePassword;
use App\Enums\Role;

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function passwordChange(array $overrides = []): array
{
    return [
        'current_password' => 'Temporary-pass-1',
        'password' => 'My-new-Secret-42',
        'password_confirmation' => 'My-new-Secret-42',
        ...$overrides,
    ];
}

it('replaces the password once the User confirms their current one', function () {
    $user = userWithRole(Role::Sales, ['email' => 'lina@inkcode.test', 'password' => 'Temporary-pass-1']);

    $this->actingAs($user)
        ->putJson('/api/v1/auth/me/password', passwordChange())
        ->assertNoContent();

    signInFromNewBrowser('lina@inkcode.test', 'Temporary-pass-1')->assertUnprocessable();
    signInFromNewBrowser('lina@inkcode.test', 'My-new-Secret-42')->assertOk();
});

it('signs the User out on their other devices but keeps this one signed in', function () {
    userWithRole(Role::Support, ['email' => 'karim@inkcode.test', 'password' => 'Temporary-pass-1']);
    $laptop = sessionOf(signInFromNewBrowser('karim@inkcode.test', 'Temporary-pass-1')->assertOk());
    $phone = sessionOf(signInFromNewBrowser('karim@inkcode.test', 'Temporary-pass-1')->assertOk());

    asBrowser($laptop)->putJson('/api/v1/auth/me/password', passwordChange())->assertNoContent();

    asBrowser($phone)->getJson('/api/v1/auth/me')->assertUnauthorized();
    asBrowser($laptop)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', 'karim@inkcode.test');
});

it('keeps "keep me signed in" working on the device that changed the password', function () {
    userWithRole(Role::Support, ['email' => 'karim@inkcode.test', 'password' => 'Temporary-pass-1']);
    $rememberCookie = app('auth')->guard('web')->getRecallerName();
    $signIn = asBrowser()->postJson('/api/v1/auth/login', [
        'email' => 'karim@inkcode.test', 'password' => 'Temporary-pass-1', 'remember' => true,
    ])->assertOk();

    $change = asBrowser(sessionOf($signIn))
        ->withCookie($rememberCookie, (string) $signIn->getCookie($rememberCookie)?->getValue())
        ->putJson('/api/v1/auth/me/password', passwordChange())
        ->assertNoContent();

    // Days later the session has expired; only the "keep me signed in" cookie is left.
    asBrowser()
        ->withCookie($rememberCookie, (string) $change->getCookie($rememberCookie)?->getValue())
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});

it('refuses the change when the current password is wrong', function () {
    $user = userWithRole(Role::Finance, ['password' => 'Temporary-pass-1']);

    $this->actingAs($user)
        ->putJson('/api/v1/auth/me/password', passwordChange(['current_password' => 'not-my-password']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.current_password.0', 'The password is incorrect.');

    signInFromNewBrowser($user->email, 'Temporary-pass-1')->assertOk();
});

it('refuses a password below the minimum strength', function (string $weak) {
    $this->actingAs(userWithRole(Role::Viewer, ['password' => 'Temporary-pass-1']))
        ->putJson('/api/v1/auth/me/password', passwordChange(['password' => $weak, 'password_confirmation' => $weak]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['password']);
})->with([
    'shorter than 10 characters' => 'Short-pw1',
    'no uppercase letter' => 'all-lowercase-42',
    'no lowercase letter' => 'ALL-UPPERCASE-42',
    'no number' => 'No-Numbers-Here',
    'no letter' => '1234567890-12',
]);

it('asks for the new password twice', function () {
    $this->actingAs(userWithRole(Role::Viewer, ['password' => 'Temporary-pass-1']))
        ->putJson('/api/v1/auth/me/password', passwordChange(['password_confirmation' => 'Something-else-42']))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['password']);
});

it('refuses keeping the current password as the new one', function () {
    $this->actingAs(userWithRole(Role::Viewer, ['password' => 'Temporary-pass-1']))
        ->putJson('/api/v1/auth/me/password', passwordChange([
            'password' => 'Temporary-pass-1',
            'password_confirmation' => 'Temporary-pass-1',
        ]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['password']);
});

it('lets a User of every Role change their own password', function (Role $role) {
    $this->actingAs(userWithRole($role, ['password' => 'Temporary-pass-1']))
        ->putJson('/api/v1/auth/me/password', passwordChange())
        ->assertNoContent();
})->with(Role::cases());

it('slows down repeated attempts to guess the current password', function () {
    $this->actingAs(userWithRole(Role::Sales, ['password' => 'Temporary-pass-1']));

    foreach (range(1, ChangePassword::ATTEMPTS_PER_MINUTE) as $attempt) {
        $this->putJson('/api/v1/auth/me/password', passwordChange(['current_password' => "guess-{$attempt}"]))
            ->assertUnprocessable();
    }

    $this->putJson('/api/v1/auth/me/password', passwordChange())
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests');
});

it('does not let a guest change a password', function () {
    $this->putJson('/api/v1/auth/me/password', passwordChange())
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});
