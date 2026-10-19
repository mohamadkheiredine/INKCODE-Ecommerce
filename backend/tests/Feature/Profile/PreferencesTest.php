<?php

use App\Enums\AuditAction;
use App\Enums\Role;

it('keeps the language and theme a User chooses on their account', function () {
    $user = userWithRole(Role::Sales);

    $this->actingAs($user)
        ->patchJson('/api/v1/auth/me/preferences', ['locale' => 'ar', 'theme' => 'dark'])
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.preferences', ['locale' => 'ar', 'theme' => 'dark']);

    $this->getJson('/api/v1/auth/me')
        ->assertJsonPath('data.preferences', ['locale' => 'ar', 'theme' => 'dark']);
});

it('records the changed preference in the audit trail', function () {
    $user = userWithRole(Role::Sales, ['name' => 'Lina Saleh']);

    $this->actingAs($user)
        ->patchJson('/api/v1/auth/me/preferences', ['locale' => 'ar', 'theme' => 'light'])
        ->assertOk();

    $entry = auditTrailOf('user', $user->id)->sole();
    expect($entry->action)->toBe(AuditAction::Updated)
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->record_label)->toBe('Lina Saleh')
        ->and($entry->before)->toBe(['locale' => 'en'])
        ->and($entry->after)->toBe(['locale' => 'ar']);
});

it('records nothing when the preferences are already the saved ones', function () {
    $user = userWithRole(Role::Sales);

    $this->actingAs($user)->patchJson('/api/v1/auth/me/preferences', ['locale' => 'en'])->assertOk();

    expect(auditTrailOf('user', $user->id))->toBeEmpty();
});

it('applies the saved preferences on the next sign-in, from any device', function () {
    $user = userWithRole(Role::Support, ['email' => 'karim@inkcode.test', 'password' => 'secret-pass-1']);
    $this->actingAs($user)->patchJson('/api/v1/auth/me/preferences', ['locale' => 'ar', 'theme' => 'dark'])->assertOk();

    signInFromNewBrowser('karim@inkcode.test', 'secret-pass-1')
        ->assertOk()
        ->assertJsonPath('data.preferences', ['locale' => 'ar', 'theme' => 'dark']);
});

it('changes only the preference that is sent', function () {
    $user = userWithRole(Role::Finance);

    $this->actingAs($user)
        ->patchJson('/api/v1/auth/me/preferences', ['theme' => 'dark'])
        ->assertOk()
        ->assertJsonPath('data.preferences', ['locale' => 'en', 'theme' => 'dark']);
});

it('lets a User of every Role keep their own preferences', function (Role $role) {
    $this->actingAs(userWithRole($role))
        ->patchJson('/api/v1/auth/me/preferences', ['locale' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.preferences.locale', 'ar');
})->with(Role::cases());

it('refuses a language or theme the interface does not offer', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->patchJson('/api/v1/auth/me/preferences', ['locale' => 'fr', 'theme' => 'sepia'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['locale', 'theme']);
});

it('needs at least one preference to change', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->patchJson('/api/v1/auth/me/preferences', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['locale', 'theme']);
});

it('does not let a guest change preferences', function () {
    $this->patchJson('/api/v1/auth/me/preferences', ['locale' => 'ar'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});
