<?php

use App\Enums\Role;

it('greets every demo account with an unread welcome in demo mode', function (string $role, string $name) {
    seedStore(demoMode: true);

    signInFromNewBrowser("{$role}@inkcode.test", 'password')->assertOk();

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('meta.unread_count', 1)
        ->assertJsonPath('data.0.type', 'welcome')
        ->assertJsonPath('data.0.message', "Welcome to Inkcode Commerce, {$name}. Your language and theme are kept on your profile.")
        ->assertJsonPath('data.0.link', '/profile');
})->with([
    ['admin', 'Ahmed Ali'],
    ['operations', 'Rania Khoury'],
    ['inventory', 'Omar Haddad'],
    ['sales', 'Lina Saleh'],
    ['support', 'Karim Mansour'],
    ['finance', 'Nadia Farouk'],
    ['viewer', 'Youssef Nasser'],
]);

it('brings back exactly one unread welcome when demo data is seeded again', function () {
    seedStore(demoMode: true);
    signInFromNewBrowser('sales@inkcode.test', 'password')->assertOk();
    $this->postJson('/api/v1/notifications/read-all')->assertNoContent();

    seedStore(demoMode: true);

    $this->getJson('/api/v1/notifications')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.unread_count', 1);
});

it('sends no welcome outside demo mode', function () {
    $user = userWithRole(Role::Admin);

    seedStore(demoMode: false);

    $this->actingAs($user)->getJson('/api/v1/notifications')->assertJsonPath('meta.total', 0);
});
