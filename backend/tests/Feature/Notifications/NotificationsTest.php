<?php

use App\Enums\Role;
use App\Notifications\Welcome;

it('lists a User\'s notifications newest first, each with a type, a message and a link', function () {
    $user = userWithRole(Role::Sales, ['name' => 'Lina Saleh']);
    $this->travelTo(now()->subDay());
    $user->notify(new Welcome);
    $this->travelBack();
    $user->notify(new Welcome);

    $response = $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk();

    $response->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'welcome')
        ->assertJsonPath('data.0.message', 'Welcome to Inkcode Commerce, Lina Saleh. Your language and theme are kept on your profile.')
        ->assertJsonPath('data.0.link', '/profile')
        ->assertJsonPath('data.0.read_at', null);
    expect($response->json('data.0.created_at'))->toBeGreaterThan($response->json('data.1.created_at'));
});

it('never lists another User\'s notifications', function () {
    $user = userWithRole(Role::Support);
    userWithRole(Role::Admin)->notify(new Welcome);

    $this->actingAs($user)->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.unread_count', 0);
});

it('writes each message in the language the interface asks for', function () {
    $user = userWithRole(Role::Finance, ['name' => 'Nadia Farouk']);
    $user->notify(new Welcome);

    $this->actingAs($user)->getJson('/api/v1/notifications', ['Accept-Language' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.0.message', 'أهلاً بك في إنككود كوميرس يا Nadia Farouk. تُحفظ لغتك ومظهرك في ملفك الشخصي.');
});

it('pages through the notifications and counts the unread ones across every page', function () {
    $user = userWithRole(Role::Operations);
    foreach (range(1, 3) as $_) {
        $user->notify(new Welcome);
    }
    $user->notifications()->latest()->first()?->markAsRead();

    $this->actingAs($user)->getJson('/api/v1/notifications?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.unread_count', 2);
});

it('refuses a page size outside 1 to 50', function (string $perPage, string $message) {
    $this->actingAs(userWithRole(Role::Admin))
        ->getJson('/api/v1/notifications?per_page='.$perPage)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['per_page' => $message]);
})->with([
    'zero' => ['0', 'The per page field must be between 1 and 50.'],
    'too many' => ['51', 'The per page field must be between 1 and 50.'],
    'not a number' => ['ten', 'The per page field must be an integer.'],
]);

it('refuses a page number below 1', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->getJson('/api/v1/notifications?page=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page' => 'The page field must be at least 1.']);
});

it('marks one notification as read', function () {
    $user = userWithRole(Role::Inventory);
    $user->notify(new Welcome);
    $user->notify(new Welcome);
    $id = $user->notifications()->value('id');

    $this->actingAs($user)->postJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.read_at', fn (?string $readAt) => $readAt !== null);

    $this->getJson('/api/v1/notifications')->assertJsonPath('meta.unread_count', 1);
});

it('keeps the first time a notification was read when it is marked again', function () {
    $user = userWithRole(Role::Sales);
    $user->notify(new Welcome);
    $id = $user->notifications()->value('id');
    $this->travelTo(now()->subHour());
    $firstRead = $this->actingAs($user)->postJson("/api/v1/notifications/{$id}/read")->json('data.read_at');
    $this->travelBack();

    $this->postJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.read_at', $firstRead);
});

it('does not let a User mark another User\'s notification, nor reveal that it exists', function () {
    $owner = userWithRole(Role::Admin);
    $owner->notify(new Welcome);
    $id = $owner->notifications()->value('id');

    $this->actingAs(userWithRole(Role::Admin))->postJson("/api/v1/notifications/{$id}/read")
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    $this->actingAs($owner)->getJson('/api/v1/notifications')->assertJsonPath('meta.unread_count', 1);
});

it('answers 404 for a notification that does not exist', function (string $id) {
    $this->actingAs(userWithRole(Role::Viewer))
        ->postJson("/api/v1/notifications/{$id}/read")
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
})->with(['unknown id' => '8f14e45f-ceea-467f-a0e6-1b3f15b94ef7', 'not an id' => 'abc']);

it('marks all of a User\'s notifications as read, and nobody else\'s', function () {
    $user = userWithRole(Role::Operations);
    $user->notify(new Welcome);
    $user->notify(new Welcome);
    $colleague = userWithRole(Role::Operations);
    $colleague->notify(new Welcome);

    $this->actingAs($user)->postJson('/api/v1/notifications/read-all')->assertNoContent();

    $this->getJson('/api/v1/notifications')
        ->assertJsonPath('meta.unread_count', 0)
        ->assertJsonPath('data.0.read_at', fn (?string $readAt) => $readAt !== null)
        ->assertJsonPath('data.1.read_at', fn (?string $readAt) => $readAt !== null);
    $this->actingAs($colleague)->getJson('/api/v1/notifications')->assertJsonPath('meta.unread_count', 1);
});

it('lets a User of every Role read and mark their own notifications', function (Role $role) {
    $user = userWithRole($role);
    $user->notify(new Welcome);
    $user->notify(new Welcome);
    $id = $user->notifications()->value('id');

    $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.unread_count', 2);
    $this->postJson("/api/v1/notifications/{$id}/read")->assertOk();
    $this->postJson('/api/v1/notifications/read-all')->assertNoContent();
})->with(Role::cases());

it('does not let a guest near any notification', function (string $method, string $uri) {
    $this->json($method, $uri)
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
})->with([
    'list' => ['GET', '/api/v1/notifications'],
    'mark one' => ['POST', '/api/v1/notifications/8f14e45f-ceea-467f-a0e6-1b3f15b94ef7/read'],
    'mark all' => ['POST', '/api/v1/notifications/read-all'],
]);
