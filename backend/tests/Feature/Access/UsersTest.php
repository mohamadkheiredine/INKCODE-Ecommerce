<?php

use App\Actions\Access\DeactivateUser;
use App\Actions\Access\UpdateUser;
use App\Enums\AuditAction;
use App\Enums\Locale;
use App\Enums\Role;
use App\Enums\Theme;
use App\Exceptions\BusinessRuleViolation;
use App\Models\StoreSettings;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newUser(array $overrides = []): array
{
    return [
        'name' => 'Lina Saleh',
        'email' => 'lina@inkcode.test',
        'role' => 'sales',
        'password' => 'Temporary-pass-1',
        'password_confirmation' => 'Temporary-pass-1',
        ...$overrides,
    ];
}

/**
 * Every Role that may not manage Users: everyone but the Admin.
 *
 * @return list<Role>
 */
function rolesThatMayNotManageUsers(): array
{
    return array_values(array_filter(Role::cases(), fn (Role $role): bool => $role !== Role::Admin));
}

describe('listing Users', function () {
    it('lists Users with their Role and status, searched, filtered and sorted by name', function () {
        $admin = userWithRole(Role::Admin, ['name' => 'Adam Admin', 'email' => 'adam@inkcode.test']);
        userWithRole(Role::Sales, ['name' => 'Lina Saleh', 'email' => 'lina@inkcode.test']);
        User::factory()->inactive()->withRole(Role::Sales)->create(['name' => 'Karim Nassar', 'email' => 'karim@shop.test']);

        $this->actingAs($admin);
        $names = fn (string $query): array => $this->getJson("/api/v1/users?$query")->assertOk()->json('data.*.name');

        $this->getJson('/api/v1/users?search=lina')
            ->assertJsonPath('data.0.role', 'sales')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonMissingPath('data.0.password');
        expect($names(''))->toBe(['Adam Admin', 'Karim Nassar', 'Lina Saleh'])
            ->and($names('search=shop.test'))->toBe(['Karim Nassar'])
            ->and($names('filter[role]=sales'))->toBe(['Karim Nassar', 'Lina Saleh'])
            ->and($names('filter[status]=deactivated'))->toBe(['Karim Nassar'])
            ->and($names('filter[status]=active&sort=-name'))->toBe(['Lina Saleh', 'Adam Admin']);

        $this->getJson('/api/v1/users?filter[role]=owner')->assertUnprocessable()->assertJsonValidationErrors('filter.role');
    });
});

describe('creating a User', function () {
    it('creates a User with a Role and a temporary password, who can then sign in', function () {
        $admin = userWithRole(Role::Admin);

        $id = $this->actingAs($admin)
            ->postJson('/api/v1/users', newUser(['email' => 'Lina@InkCode.test']))
            ->assertCreated()
            ->assertJsonPath('data.email', 'lina@inkcode.test')
            ->assertJsonPath('data.role', 'sales')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $entry = auditTrailOf('user', $id)->sole();
        expect($entry->action)->toBe(AuditAction::Created)
            ->and($entry->user_id)->toBe($admin->id)
            ->and($entry->after)->toMatchArray(['name' => 'Lina Saleh', 'email' => 'lina@inkcode.test', 'role' => 'sales'])
            ->and(json_encode($entry->after))->not->toContain('Temporary-pass-1')->not->toContain('password');

        signInFromNewBrowser('lina@inkcode.test', 'Temporary-pass-1')->assertOk()->assertJsonPath('data.role', 'sales');
    });

    it("starts a new User with the store's default language and theme", function () {
        StoreSettings::query()->whereKey(StoreSettings::ID)->update(['default_locale' => 'ar', 'default_theme' => 'dark']);
        app(Settings::class)->forget();

        $id = $this->actingAs(userWithRole(Role::Admin))->postJson('/api/v1/users', newUser())->assertCreated()->json('data.id');

        $user = User::query()->findOrFail($id);
        expect($user->locale)->toBe(Locale::Ar)->and($user->theme)->toBe(Theme::Dark);
    });

    it('validates the details, the Role and the strength of the temporary password', function () {
        userWithRole(Role::Support, ['email' => 'taken@inkcode.test']);

        $this->actingAs(userWithRole(Role::Admin))
            ->postJson('/api/v1/users', newUser([
                'name' => '',
                'email' => 'TAKEN@inkcode.test',
                'role' => 'owner',
                'password' => 'short',
                'password_confirmation' => 'different',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'role', 'password']);

        expect(User::query()->count())->toBe(2);
    });
});

describe('changing a User', function () {
    it("changes a User's details and Role and records the Role change", function () {
        $user = userWithRole(Role::Sales, ['name' => 'Lina Saleh', 'email' => 'lina@inkcode.test']);
        $support = userWithRole(Role::Support);

        $this->actingAs(userWithRole(Role::Admin))
            ->putJson("/api/v1/users/{$user->id}", ['name' => 'Lina Haddad', 'email' => 'lina@inkcode.test', 'role' => 'finance'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Lina Haddad')
            ->assertJsonPath('data.role', 'finance');

        expect($user->fresh()?->role())->toBe(Role::Finance);
        $entry = auditTrailOf('user', $user->id)->sole();
        expect($entry->action)->toBe(AuditAction::Updated)
            ->and($entry->before)->toBe(['name' => 'Lina Saleh', 'role' => 'sales'])
            ->and($entry->after)->toBe(['name' => 'Lina Haddad', 'role' => 'finance']);

        $this->putJson("/api/v1/users/{$user->id}", ['name' => 'Lina', 'email' => $support->email, 'role' => 'finance'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    });

    it('lets the Admin edit their own details but never demote themselves', function () {
        $admin = userWithRole(Role::Admin, ['email' => 'adam@inkcode.test']);
        userWithRole(Role::Admin);

        $this->actingAs($admin)
            ->putJson("/api/v1/users/{$admin->id}", ['name' => 'Adam N.', 'email' => 'adam@inkcode.test', 'role' => 'admin'])
            ->assertOk();

        $this->putJson("/api/v1/users/{$admin->id}", ['name' => 'Adam N.', 'email' => 'adam@inkcode.test', 'role' => 'viewer'])
            ->assertForbidden()
            ->assertJsonPath('code', 'own_access')
            ->assertJsonPath('message', 'You cannot change your own Role or deactivate yourself.');

        expect($admin->fresh()?->role())->toBe(Role::Admin);
    });
});

describe('deactivating and reactivating a User', function () {
    it('ends the User\'s sessions at once and keeps them from signing in until reactivated', function () {
        config(['session.driver' => 'database']);
        $admin = userWithRole(Role::Admin);
        $user = userWithRole(Role::Support, ['email' => 'karim@inkcode.test', 'password' => 'Temporary-pass-1']);
        $laptop = sessionOf(signInFromNewBrowser('karim@inkcode.test', 'Temporary-pass-1')->assertOk());
        $user->createToken('script');

        asBrowser()->actingAs($admin)
            ->postJson("/api/v1/users/{$user->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
            ->and($user->tokens()->exists())->toBeFalse();
        asBrowser($laptop)->getJson('/api/v1/auth/me')->assertUnauthorized();
        signInFromNewBrowser('karim@inkcode.test', 'Temporary-pass-1')->assertUnprocessable();

        $entry = auditTrailOf('user', $user->id)->sole();
        expect($entry->before)->toBe(['is_active' => true])->and($entry->after)->toBe(['is_active' => false]);

        asBrowser()->actingAs($admin)->postJson("/api/v1/users/{$user->id}/reactivate")->assertOk()->assertJsonPath('data.is_active', true);
        signInFromNewBrowser('karim@inkcode.test', 'Temporary-pass-1')->assertOk();
    });

    it('signs out a deactivated User on their next request, whatever stores the session', function () {
        $user = userWithRole(Role::Support);
        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    });

    it('never lets the Admin deactivate themselves', function () {
        $admin = userWithRole(Role::Admin);
        userWithRole(Role::Admin);

        $this->actingAs($admin)
            ->postJson("/api/v1/users/{$admin->id}/deactivate")
            ->assertForbidden()
            ->assertJsonPath('code', 'own_access');

        expect($admin->fresh()?->is_active)->toBeTrue();
    });

    it('never leaves the store without an active Admin', function () {
        $admin = userWithRole(Role::Admin);
        $acting = userWithRole(Role::Admin);
        $acting->forceFill(['is_active' => false])->save();

        $details = ['name' => $admin->name, 'email' => $admin->email];

        // Only reachable when two Admins act at once; the API path is guarded by "not yourself".
        expect(fn () => app(DeactivateUser::class)->handle($acting, $admin))->toThrow(BusinessRuleViolation::class, 'The store needs at least one active Admin.')
            ->and(fn () => app(UpdateUser::class)->handle($acting, $admin, $details, Role::Viewer))->toThrow(BusinessRuleViolation::class)
            ->and($admin->fresh()?->is_active)->toBeTrue()
            ->and($admin->fresh()?->role())->toBe(Role::Admin);
    });
});

describe('who may manage Users', function () {
    it('refuses every Users endpoint to Roles without users.manage', function (Role $role) {
        $user = userWithRole(Role::Viewer);
        $this->actingAs(userWithRole($role));

        $this->getJson('/api/v1/users')->assertForbidden();
        $this->getJson("/api/v1/users/{$user->id}")->assertForbidden();
        $this->postJson('/api/v1/users', newUser())->assertForbidden();
        $this->putJson("/api/v1/users/{$user->id}", ['name' => 'X', 'email' => 'x@inkcode.test', 'role' => 'admin'])->assertForbidden();
        $this->postJson("/api/v1/users/{$user->id}/deactivate")->assertForbidden();
        $this->postJson("/api/v1/users/{$user->id}/reactivate")->assertForbidden();
    })->with(rolesThatMayNotManageUsers());
});
