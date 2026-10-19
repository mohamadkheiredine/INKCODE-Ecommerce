<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Attribute;
use App\Models\Variant;

describe('the Attribute library', function () {
    it('lists Attributes with their values in order, to every Role', function () {
        Attribute::factory()->withValues(['S', 'M', 'L'])->create(['name' => 'Size']);
        Attribute::factory()->withValues(['Red'])->create(['name' => 'Color']);

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson('/api/v1/attributes')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Color')
            ->assertJsonPath('data.1.name', 'Size')
            ->assertJsonPath('data.1.values.*.name', ['S', 'M', 'L'])
            ->assertJsonPath('data.1.values.1.code', 'M');
    });

    it('creates an Attribute with its values and records it', function () {
        $id = $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/attributes', ['name' => 'Color', 'values' => [
                ['name' => 'Red', 'code' => 'RED'],
                ['name' => 'Navy blue', 'code' => 'NAVY'],
            ]])
            ->assertCreated()
            ->assertJsonPath('data.values.*.code', ['RED', 'NAVY'])
            ->json('data.id');

        expect(auditTrailOf('attribute', $id)->sole()->after)->toBe(['name' => 'Color', 'values' => ['Red (RED)', 'Navy blue (NAVY)']]);
    });

    it('keeps values unique within an Attribute, by name and by code', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/attributes', ['name' => 'Color', 'values' => [
                ['name' => 'Red', 'code' => 'RED'],
                ['name' => 'red', 'code' => 'RED'],
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'values.1.name' => 'Each value of an Attribute needs its own name and code.',
                'values.1.code' => 'Each value of an Attribute needs its own name and code.',
            ]);
    });

    it('allows the same value in different Attributes, but not two Attributes with one name', function () {
        Attribute::factory()->withValues(['Cotton'])->create(['name' => 'Material']);
        $this->actingAs(userWithRole(Role::Operations));

        $this->postJson('/api/v1/attributes', ['name' => 'Lining', 'values' => [['name' => 'Cotton', 'code' => 'COTTON']]])->assertCreated();
        $this->postJson('/api/v1/attributes', ['name' => 'Material', 'values' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    });

    it('validates value codes', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/attributes', ['name' => 'Size', 'values' => [['name' => 'Small', 'code' => 'small']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['values.0.code' => 'Use capital letters, digits and single dashes only, for example "RED" or "TSHIRT-RED-M".']);
    });

    it('renames, adds, reorders and removes values in one save, keeping the ids of kept values', function () {
        $size = Attribute::factory()->withValues(['S', 'M', 'L'])->create(['name' => 'Size']);
        [$small, $medium] = $size->values;

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/attributes/{$size->id}", ['name' => 'Size', 'values' => [
                ['id' => $medium->id, 'name' => 'Medium', 'code' => 'M'],
                ['id' => $small->id, 'name' => 'S', 'code' => 'S'],
                ['name' => 'XL', 'code' => 'XL'],
            ]])
            ->assertOk()
            ->assertJsonPath('data.values.*.name', ['Medium', 'S', 'XL'])
            ->assertJsonPath('data.values.0.id', $medium->id);

        $entry = auditTrailOf('attribute', $size->id)->sole();
        expect($entry->action)->toBe(AuditAction::Updated)
            ->and($entry->before)->toBe(['values' => ['S (S)', 'M (M)', 'L (L)']])
            ->and($entry->after)->toBe(['values' => ['Medium (M)', 'S (S)', 'XL (XL)']]);
    });

    it('refuses to remove a value that Variants use', function () {
        $size = Attribute::factory()->withValues(['S', 'M'])->create(['name' => 'Size']);
        [$small, $medium] = $size->values;
        Variant::factory()->create(['combination_key' => (string) $small->id])->attributeValues()->attach($small);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/attributes/{$size->id}", ['name' => 'Size', 'values' => [
                ['id' => $medium->id, 'name' => 'M', 'code' => 'M'],
            ]])
            ->assertConflict()
            ->assertJsonPath('code', 'attribute_value_in_use')
            ->assertJsonPath('message', 'S is used by Variants, so it cannot be removed.');

        expect($size->values()->count())->toBe(2);
    });

    it("refuses another Attribute's value", function () {
        $size = Attribute::factory()->withValues(['S'])->create(['name' => 'Size']);
        $red = Attribute::factory()->withValues(['Red'])->create(['name' => 'Color'])->values->first();

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/attributes/{$size->id}", ['name' => 'Size', 'values' => [
                ['id' => $red?->id, 'name' => 'Red', 'code' => 'RED'],
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('values.0.id');
    });

    it('deletes an unused Attribute, and refuses one Variants use', function () {
        $unused = Attribute::factory()->withValues(['Wool'])->create(['name' => 'Material']);
        $size = Attribute::factory()->withValues(['S'])->create(['name' => 'Size']);
        Variant::factory()->create()->attributeValues()->attach($size->values->first());
        $this->actingAs(userWithRole(Role::Operations));

        $this->deleteJson("/api/v1/attributes/{$unused->id}")->assertNoContent();
        $this->deleteJson("/api/v1/attributes/{$size->id}")
            ->assertConflict()
            ->assertJsonPath('code', 'in_use');

        expect(auditTrailOf('attribute', $unused->id)->sole()->action)->toBe(AuditAction::Deleted)
            ->and(Attribute::query()->pluck('name')->all())->toBe(['Size']);
    });

    it('refuses Roles that may not change the catalog', function () {
        $size = Attribute::factory()->create(['name' => 'Size']);
        $this->actingAs(userWithRole(Role::Inventory));

        $this->postJson('/api/v1/attributes', ['name' => 'Color', 'values' => []])->assertForbidden();
        $this->putJson("/api/v1/attributes/{$size->id}", ['name' => 'Fit', 'values' => []])->assertForbidden();
        $this->deleteJson("/api/v1/attributes/{$size->id}")->assertForbidden();
    });
});
