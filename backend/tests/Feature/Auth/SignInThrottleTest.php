<?php

use App\Actions\Auth\SignIn;
use App\Enums\Role;

beforeEach(function () {
    userWithRole(Role::Sales, ['email' => 'lina@inkcode.test', 'password' => 'secret-pass-1']);
});

function failSignIn(int $times, string $email = 'lina@inkcode.test'): void
{
    foreach (range(1, $times) as $_) {
        test()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong'])->assertUnprocessable();
    }
}

it('locks sign-in for that email after five failed attempts', function () {
    failSignIn(5);

    $this->postJson('/api/v1/auth/login', ['email' => 'lina@inkcode.test', 'password' => 'secret-pass-1'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('code', 'too_many_requests')
        ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Too many sign-in attempts. Please try again in'));
});

it('still allows a correct sign-in after four failed attempts', function () {
    failSignIn(4);

    $this->postJson('/api/v1/auth/login', ['email' => 'lina@inkcode.test', 'password' => 'secret-pass-1'])->assertOk();
});

it('forgets earlier failures once the User signs in successfully', function () {
    failSignIn(4);
    $this->postJson('/api/v1/auth/login', ['email' => 'lina@inkcode.test', 'password' => 'secret-pass-1'])->assertOk();
    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    failSignIn(4);

    $this->postJson('/api/v1/auth/login', ['email' => 'lina@inkcode.test', 'password' => 'secret-pass-1'])->assertOk();
});

it('does not lock other email addresses', function () {
    userWithRole(Role::Viewer, ['email' => 'omar@inkcode.test', 'password' => 'secret-pass-2']);
    failSignIn(5);

    $this->postJson('/api/v1/auth/login', ['email' => 'omar@inkcode.test', 'password' => 'secret-pass-2'])->assertOk();
});

it('locks out an IP address that keeps guessing across many email addresses', function () {
    $emails = array_map(fn (int $i) => "guess{$i}@inkcode.test", range(1, SignIn::MAX_FAILED_ATTEMPTS_PER_IP));
    foreach ($emails as $email) {
        failSignIn(1, $email);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'lina@inkcode.test', 'password' => 'secret-pass-1'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests');
});
