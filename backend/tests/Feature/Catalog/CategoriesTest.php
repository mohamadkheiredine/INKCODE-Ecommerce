<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Category;

describe('listing Categories', function () {
    it('lists Categories a page at a time, each with its parent and how many children it has', function () {
        $clothing = Category::factory()->create(['name' => 'Clothing']);
        Category::factory()->childOf($clothing)->create(['name' => 'Shirts']);
        Category::factory()->childOf($clothing)->create(['name' => 'Trousers']);

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson('/api/v1/categories?sort=name&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Clothing')
            ->assertJsonPath('data.0.slug', 'clothing')
            ->assertJsonPath('data.0.parent', null)
            ->assertJsonPath('data.0.children_count', 2)
            ->assertJsonPath('data.1.name', 'Shirts')
            ->assertJsonPath('data.1.parent.name', 'Clothing')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    });

    it('lets every Role list Categories', function (Role $role) {
        $this->actingAs(userWithRole($role))
            ->getJson('/api/v1/categories')
            ->assertOk();
    })->with(Role::cases());

    it('does not list Categories to a guest', function () {
        $this->getJson('/api/v1/categories')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    });
});

describe('creating a Category', function () {
    it('creates a top-level Category with a slug generated from its name', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/categories', ['name' => 'Summer Collection', 'description' => 'Light clothes for hot days.'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Summer Collection')
            ->assertJsonPath('data.slug', 'summer-collection')
            ->assertJsonPath('data.description', 'Light clothes for hot days.')
            ->assertJsonPath('data.parent', null);

        $this->getJson('/api/v1/categories?search=summer')
            ->assertJsonPath('data.0.slug', 'summer-collection');
    });

    it('records who created the Category, and its values, in the audit trail', function () {
        $operations = userWithRole(Role::Operations);

        $id = $this->actingAs($operations)
            ->postJson('/api/v1/categories', ['name' => 'Shoes'])
            ->assertCreated()
            ->json('data.id');

        $entry = auditTrailOf('category', $id)->sole();
        expect($entry->user_id)->toBe($operations->id)
            ->and($entry->action)->toBe(AuditAction::Created)
            ->and($entry->record_label)->toBe('Shoes')
            ->and($entry->before)->toBeNull()
            ->and($entry->after)->toEqual(['parent_id' => null, 'name' => 'Shoes', 'slug' => 'shoes', 'description' => null]);
    });

    it('gives a Category its own slug when another one already has the generated slug', function () {
        Category::factory()->create(['name' => 'Bags', 'slug' => 'bags']);
        Category::factory()->create(['name' => 'Bags old', 'slug' => 'bags-2']);

        $this->actingAs(userWithRole(Role::Admin))
            ->postJson('/api/v1/categories', ['name' => 'Bags'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'bags-3');
    });

    it('writes a Latin slug for a name in Arabic', function () {
        $this->actingAs(userWithRole(Role::Admin))
            ->postJson('/api/v1/categories', ['name' => 'ملابس رجالية'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'mlabs-rgaly');
    });

    it('falls back to a plain slug when the name has no letters or digits', function () {
        $this->actingAs(userWithRole(Role::Admin))
            ->postJson('/api/v1/categories', ['name' => '🎉'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'category');
    });

    it('keeps a slug the User chooses', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/categories', ['name' => 'Summer Collection', 'slug' => 'summer'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'summer');
    });

    it('refuses a slug another Category already has', function () {
        Category::factory()->create(['slug' => 'summer']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/categories', ['name' => 'Summer', 'slug' => 'summer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug' => 'The slug has already been taken.']);
    });

    it('refuses a slug that is not lowercase letters, digits and single dashes', function (string $slug) {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/categories', ['name' => 'Summer', 'slug' => $slug])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug' => 'Use lowercase letters, digits and single dashes only, for example "summer-shirts".']);
    })->with(['Summer', 'summer shirts', 'summer--shirts', '-summer', 'été']);

    it('needs a name', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/categories', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['name' => 'The name field is required.']);
    });

    it('creates a child under a top-level Category', function () {
        $clothing = Category::factory()->create(['name' => 'Clothing']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/categories', ['name' => 'Shirts', 'parent_id' => $clothing->id])
            ->assertCreated()
            ->assertJsonPath('data.parent.id', $clothing->id)
            ->assertJsonPath('data.parent.name', 'Clothing');
    });

    it('refuses a child as the parent, so Categories never go deeper than two levels', function () {
        $shirts = Category::factory()->childOf(Category::factory()->create())->create(['name' => 'Shirts']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/categories', ['name' => 'Polo shirts', 'parent_id' => $shirts->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id' => 'Shirts is already a child Category. Choose a top-level Category as the parent.']);
    });

    it('refuses a parent that does not exist', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/categories', ['name' => 'Shirts', 'parent_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id' => 'The selected parent Category does not exist.']);
    });

    it('refuses Roles that may not change the catalog, and creates nothing', function (Role $role) {
        $this->actingAs(userWithRole($role))
            ->postJson('/api/v1/categories', ['name' => 'Shoes'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->actingAs(userWithRole(Role::Admin))->getJson('/api/v1/categories')->assertJsonCount(0, 'data');
    })->with(rolesThatMayNotManageTheCatalog());

    it('refuses a guest', function () {
        $this->postJson('/api/v1/categories', ['name' => 'Shoes'])->assertUnauthorized();
    });
});

describe('showing a Category', function () {
    it('shows one Category with its parent and number of children', function () {
        $clothing = Category::factory()->create(['name' => 'Clothing']);
        $shirts = Category::factory()->childOf($clothing)->create(['name' => 'Shirts']);

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson("/api/v1/categories/{$shirts->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Shirts')
            ->assertJsonPath('data.parent.name', 'Clothing')
            ->assertJsonPath('data.children_count', 0);
    });

    it('answers 404 for a Category that does not exist', function () {
        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson('/api/v1/categories/999')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    });
});

describe('editing a Category', function () {
    it('saves the new values and records only what changed in the audit trail', function () {
        $category = Category::factory()->create(['name' => 'Shoes', 'slug' => 'shoes', 'description' => 'All shoes.']);
        $admin = userWithRole(Role::Admin);

        $this->actingAs($admin)
            ->putJson("/api/v1/categories/{$category->id}", ['name' => 'Footwear', 'slug' => 'shoes', 'description' => 'All shoes.'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Footwear')
            ->assertJsonPath('data.slug', 'shoes');

        $entry = auditTrailOf('category', $category->id)->sole();
        expect($entry->action)->toBe(AuditAction::Updated)
            ->and($entry->user_id)->toBe($admin->id)
            ->and($entry->record_label)->toBe('Footwear')
            ->and($entry->before)->toBe(['name' => 'Shoes'])
            ->and($entry->after)->toBe(['name' => 'Footwear']);
    });

    it('generates a new slug from the new name when the slug is left empty', function () {
        $category = Category::factory()->create(['name' => 'Shoes', 'slug' => 'shoes']);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/categories/{$category->id}", ['name' => 'Running Shoes', 'slug' => ''])
            ->assertOk()
            ->assertJsonPath('data.slug', 'running-shoes');
    });

    it('keeps its own slug when it is regenerated from an unchanged name', function () {
        $category = Category::factory()->create(['name' => 'Shoes', 'slug' => 'shoes']);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/categories/{$category->id}", ['name' => 'Shoes'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'shoes');
    });

    it('writes no audit entry when nothing changed', function () {
        $category = Category::factory()->create(['name' => 'Shoes', 'slug' => 'shoes', 'description' => null]);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/categories/{$category->id}", ['name' => 'Shoes', 'slug' => 'shoes'])
            ->assertOk();

        expect(auditTrailOf('category', $category->id))->toBeEmpty();
    });

    it('refuses a slug another Category already has', function () {
        Category::factory()->create(['slug' => 'boots']);
        $category = Category::factory()->create(['slug' => 'shoes']);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/categories/{$category->id}", ['name' => 'Shoes', 'slug' => 'boots'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug' => 'The slug has already been taken.']);
    });

    it('moves a child under another top-level Category, or makes it top-level', function () {
        $clothing = Category::factory()->create();
        $sport = Category::factory()->create();
        $shirts = Category::factory()->childOf($clothing)->create(['name' => 'Shirts']);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/categories/{$shirts->id}", ['name' => 'Shirts', 'parent_id' => $sport->id])
            ->assertOk()
            ->assertJsonPath('data.parent.id', $sport->id);

        $this->putJson("/api/v1/categories/{$shirts->id}", ['name' => 'Shirts', 'parent_id' => null])
            ->assertOk()
            ->assertJsonPath('data.parent', null);
    });

    it('refuses to make a Category its own parent', function () {
        $category = Category::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/categories/{$category->id}", ['name' => 'Shoes', 'parent_id' => $category->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id' => 'A Category cannot be its own parent.']);
    });

    it('refuses to move a Category that has children under another one', function () {
        $clothing = Category::factory()->create();
        Category::factory()->childOf($clothing)->create();
        $sport = Category::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/categories/{$clothing->id}", ['name' => 'Clothing', 'parent_id' => $sport->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id' => 'This Category has children of its own, so it must stay top-level.']);
    });

    it('refuses Roles that may not change the catalog, and changes nothing', function (Role $role) {
        $category = Category::factory()->create(['name' => 'Shoes']);

        $this->actingAs(userWithRole($role))
            ->putJson("/api/v1/categories/{$category->id}", ['name' => 'Boots'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        expect($category->fresh()?->name)->toBe('Shoes');
    })->with(rolesThatMayNotManageTheCatalog());
});

describe('deleting a Category', function () {
    it('deletes a Category nothing uses and records its last values in the audit trail', function () {
        $category = Category::factory()->create(['name' => 'Hats', 'slug' => 'hats', 'description' => null]);
        $operations = userWithRole(Role::Operations);

        $this->actingAs($operations)
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertNoContent();

        $this->getJson("/api/v1/categories/{$category->id}")->assertNotFound();
        $entry = auditTrailOf('category', $category->id)->sole();
        expect($entry->action)->toBe(AuditAction::Deleted)
            ->and($entry->user_id)->toBe($operations->id)
            ->and($entry->record_label)->toBe('Hats')
            ->and($entry->before)->toEqual(['parent_id' => null, 'name' => 'Hats', 'slug' => 'hats', 'description' => null])
            ->and($entry->after)->toBeNull();
    });

    it('refuses to delete a parent Category that still has children', function () {
        $clothing = Category::factory()->create(['name' => 'Clothing']);
        Category::factory()->childOf($clothing)->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->deleteJson("/api/v1/categories/{$clothing->id}")
            ->assertConflict()
            ->assertJsonPath('code', 'category_has_children')
            ->assertJsonPath('message', 'Clothing still has child Categories. Move or delete them first.');

        $this->getJson("/api/v1/categories/{$clothing->id}")->assertOk();
        expect(auditTrailOf('category', $clothing->id))->toBeEmpty();
    });

    it('explains the refusal in Arabic when the interface is in Arabic', function () {
        $clothing = Category::factory()->create(['name' => 'Clothing']);
        Category::factory()->childOf($clothing)->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->deleteJson("/api/v1/categories/{$clothing->id}", [], ['Accept-Language' => 'ar'])
            ->assertConflict()
            ->assertJsonPath('message', 'ما زالت لـ Clothing فئات فرعية. انقلها أو احذفها أولاً.');
    });

    it('refuses Roles that may not change the catalog, and deletes nothing', function (Role $role) {
        $category = Category::factory()->create();

        $this->actingAs(userWithRole($role))
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        expect($category->fresh())->not->toBeNull();
    })->with(rolesThatMayNotManageTheCatalog());

    it('refuses a guest', function () {
        $category = Category::factory()->create();

        $this->deleteJson("/api/v1/categories/{$category->id}")->assertUnauthorized();
    });
});
