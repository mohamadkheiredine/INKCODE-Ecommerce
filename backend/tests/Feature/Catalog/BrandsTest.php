<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Brand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

describe('listing Brands', function () {
    it('lists Brands a page at a time, with their logos', function () {
        Brand::factory()->create(['name' => 'Nordic Wear', 'logo_path' => 'brands/nordic.png']);
        Brand::factory()->create(['name' => 'Atlas']);

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson('/api/v1/brands?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Atlas')
            ->assertJsonPath('data.0.slug', 'atlas')
            ->assertJsonPath('data.0.logo_url', null)
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/brands?page=2&per_page=1')
            ->assertJsonPath('data.0.logo_url', Storage::disk('public')->url('brands/nordic.png'));
    });

    it('searches by name or slug, filters by logo and sorts', function () {
        Brand::factory()->create(['name' => 'Nordic Wear', 'logo_path' => 'brands/nordic.png']);
        Brand::factory()->create(['name' => 'Atlas', 'slug' => 'atlas-outdoor']);
        Brand::factory()->create(['name' => 'Zest']);

        $this->actingAs(userWithRole(Role::Viewer));
        expect($this->getJson('/api/v1/brands?search=outdoor')->json('data.*.name'))->toBe(['Atlas'])
            ->and($this->getJson('/api/v1/brands?filter[logo]=with')->json('data.*.name'))->toBe(['Nordic Wear'])
            ->and($this->getJson('/api/v1/brands?filter[logo]=without')->json('data.*.name'))->toBe(['Atlas', 'Zest'])
            ->and($this->getJson('/api/v1/brands?sort=-name')->json('data.*.name'))->toBe(['Zest', 'Nordic Wear', 'Atlas']);
    });

    it('lets every Role list Brands', function (Role $role) {
        $this->actingAs(userWithRole($role))->getJson('/api/v1/brands')->assertOk();
    })->with(Role::cases());

    it('does not list Brands to a guest', function () {
        $this->getJson('/api/v1/brands')->assertUnauthorized();
    });
});

describe('creating a Brand', function () {
    it('creates a Brand with a slug generated from its name and records it in the audit trail', function () {
        $operations = userWithRole(Role::Operations);

        $id = $this->actingAs($operations)
            ->postJson('/api/v1/brands', ['name' => 'Nordic Wear'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Nordic Wear')
            ->assertJsonPath('data.slug', 'nordic-wear')
            ->assertJsonPath('data.logo_url', null)
            ->json('data.id');

        $entry = auditTrailOf('brand', $id)->sole();
        expect($entry->action)->toBe(AuditAction::Created)
            ->and($entry->user_id)->toBe($operations->id)
            ->and($entry->after)->toEqual(['name' => 'Nordic Wear', 'slug' => 'nordic-wear', 'logo_path' => null]);
    });

    it('gives a Brand its own slug when another one already has the generated slug', function () {
        Brand::factory()->create(['slug' => 'atlas']);

        $this->actingAs(userWithRole(Role::Admin))
            ->postJson('/api/v1/brands', ['name' => 'Atlas'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'atlas-2');
    });

    it('refuses a slug another Brand already has, or one in the wrong format', function () {
        Brand::factory()->create(['slug' => 'atlas']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/brands', ['name' => 'Atlas', 'slug' => 'atlas'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug' => 'The slug has already been taken.']);

        $this->postJson('/api/v1/brands', ['name' => 'Atlas', 'slug' => 'Atlas Brand'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug' => 'Use lowercase letters, digits and single dashes only, for example "summer-shirts".']);
    });

    it('needs a name', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/brands', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name field is required.']);
    });

    it('refuses Roles that may not change the catalog', function (Role $role) {
        $this->actingAs(userWithRole($role))
            ->postJson('/api/v1/brands', ['name' => 'Atlas'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        expect(Brand::query()->count())->toBe(0);
    })->with(rolesThatMayNotManageTheCatalog());
});

describe('editing a Brand', function () {
    it('saves the new values and records what changed', function () {
        $brand = Brand::factory()->create(['name' => 'Atlas', 'slug' => 'atlas']);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/brands/{$brand->id}", ['name' => 'Atlas Outdoor', 'slug' => 'atlas-outdoor'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Atlas Outdoor')
            ->assertJsonPath('data.slug', 'atlas-outdoor');

        $entry = auditTrailOf('brand', $brand->id)->sole();
        expect($entry->action)->toBe(AuditAction::Updated)
            ->and($entry->before)->toBe(['name' => 'Atlas', 'slug' => 'atlas'])
            ->and($entry->after)->toBe(['name' => 'Atlas Outdoor', 'slug' => 'atlas-outdoor']);
    });

    it('accepts its own slug unchanged', function () {
        $brand = Brand::factory()->create(['name' => 'Atlas', 'slug' => 'atlas']);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/brands/{$brand->id}", ['name' => 'Atlas', 'slug' => 'atlas'])
            ->assertOk();
    });

    it('refuses Roles that may not change the catalog', function (Role $role) {
        $brand = Brand::factory()->create(['name' => 'Atlas']);

        $this->actingAs(userWithRole($role))
            ->putJson("/api/v1/brands/{$brand->id}", ['name' => 'Zest'])
            ->assertForbidden();

        expect($brand->fresh()?->name)->toBe('Atlas');
    })->with(rolesThatMayNotManageTheCatalog());
});

describe('a Brand logo', function () {
    it('uploads a logo, shows it, and records the change', function () {
        $brand = Brand::factory()->create();

        $response = $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/brands/{$brand->id}/logo", ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertOk();

        $path = $brand->fresh()?->logo_path;
        Storage::disk('public')->assertExists((string) $path);
        $response->assertJsonPath('data.logo_url', Storage::disk('public')->url((string) $path));
        $entry = auditTrailOf('brand', $brand->id)->sole();
        expect($entry->before)->toBe(['logo_path' => null])
            ->and($entry->after)->toBe(['logo_path' => $path]);
    });

    it('replaces an earlier logo and removes the old file', function () {
        Storage::disk('public')->put('brands/old.png', 'old');
        $brand = Brand::factory()->create(['logo_path' => 'brands/old.png']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/brands/{$brand->id}/logo", ['logo' => UploadedFile::fake()->image('new.webp')])
            ->assertOk();

        Storage::disk('public')->assertMissing('brands/old.png');
    });

    it('removes a logo', function () {
        Storage::disk('public')->put('brands/old.png', 'old');
        $brand = Brand::factory()->create(['logo_path' => 'brands/old.png']);

        $this->actingAs(userWithRole(Role::Operations))
            ->deleteJson("/api/v1/brands/{$brand->id}/logo")
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing('brands/old.png');
        expect(auditTrailOf('brand', $brand->id)->sole()->after)->toBe(['logo_path' => null]);
    });

    it('accepts only PNG, JPEG or WebP images', function (UploadedFile $file) {
        $brand = Brand::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/brands/{$brand->id}/logo", ['logo' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo' => 'The logo field must be a file of type: png, jpg, jpeg, webp.']);

        expect($brand->fresh()?->logo_path)->toBeNull();
    })->with([
        'a PDF' => fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        'an SVG' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'),
        'a GIF' => fn () => UploadedFile::fake()->image('logo.gif'),
    ]);

    it('refuses a logo over 1 MB', function () {
        $brand = Brand::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/brands/{$brand->id}/logo", ['logo' => UploadedFile::fake()->image('logo.png')->size(1025)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo' => 'The logo field must not be greater than 1024 kilobytes.']);
    });

    it('needs a file', function () {
        $brand = Brand::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/brands/{$brand->id}/logo", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo' => 'The logo field is required.']);
    });

    it('refuses Roles that may not change the catalog', function (Role $role) {
        $brand = Brand::factory()->create(['logo_path' => 'brands/kept.png']);

        $this->actingAs(userWithRole($role))
            ->postJson("/api/v1/brands/{$brand->id}/logo", ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertForbidden();
        $this->deleteJson("/api/v1/brands/{$brand->id}/logo")->assertForbidden();

        expect($brand->fresh()?->logo_path)->toBe('brands/kept.png');
    })->with(rolesThatMayNotManageTheCatalog());
});

describe('deleting a Brand', function () {
    it('deletes a Brand nothing uses, with its logo, and records it in the audit trail', function () {
        Storage::disk('public')->put('brands/atlas.png', 'logo');
        $brand = Brand::factory()->create(['name' => 'Atlas', 'slug' => 'atlas', 'logo_path' => 'brands/atlas.png']);

        $this->actingAs(userWithRole(Role::Admin))
            ->deleteJson("/api/v1/brands/{$brand->id}")
            ->assertNoContent();

        $this->getJson("/api/v1/brands/{$brand->id}")->assertNotFound();
        Storage::disk('public')->assertMissing('brands/atlas.png');
        $entry = auditTrailOf('brand', $brand->id)->sole();
        expect($entry->action)->toBe(AuditAction::Deleted)
            ->and($entry->before)->toEqual(['name' => 'Atlas', 'slug' => 'atlas', 'logo_path' => 'brands/atlas.png']);
    });

    it('refuses Roles that may not change the catalog', function (Role $role) {
        $brand = Brand::factory()->create();

        $this->actingAs(userWithRole($role))
            ->deleteJson("/api/v1/brands/{$brand->id}")
            ->assertForbidden();

        expect($brand->fresh())->not->toBeNull();
    })->with(rolesThatMayNotManageTheCatalog());

    it('refuses a guest', function () {
        $brand = Brand::factory()->create();

        $this->deleteJson("/api/v1/brands/{$brand->id}")->assertUnauthorized();
    });
});
