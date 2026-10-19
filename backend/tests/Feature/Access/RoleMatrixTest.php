<?php

use App\Enums\Permission;
use App\Enums\Role;
use Database\Seeders\RolesAndPermissionsSeeder;

it('lists the seven Roles with their permissions for the Admin', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonPath('data.*.key', ['admin', 'operations', 'inventory', 'sales', 'support', 'finance', 'viewer'])
        ->assertJsonCount(count(Permission::cases()), 'data.0.permissions')
        ->assertJsonPath('data.6.permissions', [
            'catalog.view',
            'coupons.view',
            'customers.view',
            'dashboard.view',
            'inventory.view',
            'orders.view',
            'payments.view',
            'reports.view',
            'returns.view',
        ]);
});

it('keeps the agreed separation of duties between Roles', function () {
    $permissionsOf = fn (string $role) => collect($this->actingAs(userWithRole(Role::Admin))
        ->getJson('/api/v1/roles')->json('data'))->firstWhere('key', $role)['permissions'];

    expect($permissionsOf('finance'))->toContain('refunds.issue', 'payments.record', 'reports.export')->not->toContain('orders.transition')
        ->and($permissionsOf('support'))->toContain('returns.request', 'returns.approve')->not->toContain('refunds.issue', 'returns.receive')
        ->and($permissionsOf('inventory'))->toContain('returns.receive', 'inventory.adjust')->not->toContain('orders.create')
        ->and($permissionsOf('sales'))->toContain('orders.create', 'coupons.manage')->not->toContain('catalog.manage')
        ->and($permissionsOf('operations'))->toContain('orders.transition', 'catalog.manage')->not->toContain('users.manage');
});

it('refuses the Role matrix to every Role except Admin', function (Role $role) {
    $this->actingAs(userWithRole($role))
        ->getJson('/api/v1/roles')
        ->assertForbidden()
        ->assertExactJson([
            'message' => 'You are not allowed to perform this action.',
            'code' => 'forbidden',
        ]);
})->with([Role::Operations, Role::Inventory, Role::Sales, Role::Support, Role::Finance, Role::Viewer]);

it('refuses the Role matrix to guests', function () {
    $this->getJson('/api/v1/roles')->assertUnauthorized();
});

it('seeds Roles idempotently and restores the matrix when run again', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actingAs(userWithRole(Role::Admin))
        ->getJson('/api/v1/roles')
        ->assertJsonCount(count(Role::cases()), 'data')
        ->assertJsonCount(count(Permission::cases()), 'data.0.permissions');
});

it('gives each User exactly one Role: assigning another replaces the first', function () {
    $user = userWithRole(Role::Sales);

    $user->assignRole(Role::Finance->value);

    $me = $this->actingAs($user->fresh())->getJson('/api/v1/auth/me')->assertJsonPath('data.role', 'finance');

    expect($me->json('data.permissions'))->toContain('refunds.issue')->not->toContain('orders.create')
        ->and($user->fresh()->getRoleNames()->all())->toBe(['finance']);
});

it('refuses to give a User two Roles at once', function () {
    $user = userWithRole(Role::Sales);

    expect(fn () => $user->assignRole(Role::Finance->value, Role::Viewer->value))
        ->toThrow(InvalidArgumentException::class, 'A User has exactly one Role.');

    expect($user->fresh()->getRoleNames()->all())->toBe(['sales']);
});
