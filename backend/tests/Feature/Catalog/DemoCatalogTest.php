<?php

use App\Enums\Role;

it('seeds demo Categories in two levels and demo Brands', function () {
    seedStore(demoMode: true);

    $this->actingAs(userWithRole(Role::Viewer));
    $this->getJson('/api/v1/categories?filter[level]=top')
        ->assertJsonPath('data.*.name', ['Accessories', 'Clothing', 'Footwear', 'Home']);
    $this->getJson('/api/v1/categories?search=shirts')
        ->assertJsonPath('data.0.parent.name', 'Clothing');
    $this->getJson('/api/v1/brands')->assertJsonPath('meta.total', 6);
    $this->getJson('/api/v1/attributes')
        ->assertJsonPath('data.*.name', ['Color', 'Material', 'Size'])
        ->assertJsonPath('data.2.values.*.code', ['XS', 'S', 'M', 'L', 'XL']);
});

it('keeps the same demo catalog when demo data is seeded twice', function () {
    seedStore(demoMode: true);
    seedStore(demoMode: true);

    $this->actingAs(userWithRole(Role::Viewer))
        ->getJson('/api/v1/categories')
        ->assertJsonPath('meta.total', 16);
});

it('seeds no catalog outside demo mode', function () {
    seedStore(demoMode: false);

    $this->actingAs(userWithRole(Role::Viewer))
        ->getJson('/api/v1/categories')
        ->assertJsonPath('meta.total', 0);
});
