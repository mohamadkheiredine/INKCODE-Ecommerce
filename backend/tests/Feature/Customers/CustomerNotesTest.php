<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;

it('lists a Customer\'s notes newest first, each with its author and time', function () {
    $customer = Customer::factory()->create();
    $author = User::factory()->create(['name' => 'Maya Support']);
    CustomerNote::factory()->for($customer)->for($author, 'author')->create(['body' => 'Prefers WhatsApp', 'created_at' => now()->subDay()]);
    CustomerNote::factory()->for($customer)->create(['body' => 'Asked for an invoice copy']);
    CustomerNote::factory()->create(['body' => 'Another Customer\'s note']);

    $this->actingAs(userWithRole(Role::Viewer))
        ->getJson("/api/v1/customers/{$customer->id}/notes")
        ->assertOk()
        ->assertJsonPath('data.*.body', ['Asked for an invoice copy', 'Prefers WhatsApp'])
        ->assertJsonPath('data.1.author.name', 'Maya Support')
        ->assertJsonPath('data.1.created_at', fn (string $time): bool => $time !== '');
});

it('keeps a note readable after its author is removed', function () {
    $note = CustomerNote::factory()->create();
    $note->author?->delete();

    $this->actingAs(userWithRole(Role::Viewer))
        ->getJson("/api/v1/customers/{$note->customer_id}/notes")
        ->assertOk()
        ->assertJsonPath('data.0.author', null);
});

it('adds a note signed by the User who wrote it and records it in the audit trail', function () {
    $customer = Customer::factory()->create(['name' => 'Rana Haddad']);
    $support = userWithRole(Role::Support, ['name' => 'Maya Support']);

    $id = $this->actingAs($support)
        ->postJson("/api/v1/customers/{$customer->id}/notes", ['body' => '  Called about a late delivery. '])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Called about a late delivery.')
        ->assertJsonPath('data.author.name', 'Maya Support')
        ->json('data.id');

    $entry = auditTrailOf('customer_note', $id)->sole();
    expect($entry->action)->toBe(AuditAction::Created)
        ->and($entry->user_id)->toBe($support->id)
        ->and($entry->record_label)->toBe('Rana Haddad')
        ->and($entry->after)->toBe(['body' => 'Called about a late delivery.']);
});

it('refuses an empty or over-long note', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole(Role::Sales));
    $this->postJson("/api/v1/customers/{$customer->id}/notes", ['body' => '   '])->assertJsonValidationErrors(['body']);
    $this->postJson("/api/v1/customers/{$customer->id}/notes", ['body' => str_repeat('x', CustomerNote::MAX_LENGTH + 1)])
        ->assertJsonValidationErrors(['body']);
});

it('refuses notes to Roles without the matching access', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole(Role::Finance))
        ->postJson("/api/v1/customers/{$customer->id}/notes", ['body' => 'Hello'])
        ->assertForbidden();
    $this->actingAs(userWithRole(Role::Inventory))
        ->getJson("/api/v1/customers/{$customer->id}/notes")
        ->assertForbidden();
});
