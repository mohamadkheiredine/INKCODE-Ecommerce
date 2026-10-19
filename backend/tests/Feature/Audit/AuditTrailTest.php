<?php

use App\Enums\Role;
use App\Models\Category;
use App\Services\AuditTrail;

/*
| The audit trail itself (App\Services\AuditTrail), the one way every module records
| its changes. The modules' own tests check that each change is recorded.
*/

it('never records secrets such as the password or the remember token', function () {
    $user = userWithRole(Role::Admin, ['name' => 'Ahmed Ali']);

    $entry = app(AuditTrail::class)->created($user);

    expect($entry->after)->toHaveKeys(['name', 'email'])
        ->not->toHaveKeys(['password', 'remember_token', 'id', 'created_at', 'updated_at']);
});

it('records a change made outside a signed-in request as made by the system', function () {
    $category = Category::factory()->create();

    $entry = app(AuditTrail::class)->deleted($category);

    expect($entry->user_id)->toBeNull();
});

it('keeps every entry as it was written', function () {
    $entry = app(AuditTrail::class)->created(Category::factory()->create());

    expect(fn () => $entry->update(['record_label' => 'Changed']))->toThrow(LogicException::class, 'Audit entries never change.')
        ->and(fn () => $entry->delete())->toThrow(LogicException::class, 'Audit entries are never deleted.');
});
