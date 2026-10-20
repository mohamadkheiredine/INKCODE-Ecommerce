<?php

use App\Enums\Role;

it('seeds demo zones that quote Beirut, the rest of Lebanon and the Gulf, and demo Carriers, idempotently', function () {
    seedStore(demoMode: true);
    seedStore(demoMode: true);

    $this->actingAs(userWithRole(Role::Sales));
    $this->getJson('/api/v1/shipping/quote?country_code=LB&city=Beirut')
        ->assertJsonPath('data.zone.name', 'Beirut')
        ->assertJsonPath('data.methods.*.name', ['Standard', 'Same-day']);
    $this->getJson('/api/v1/shipping/quote?country_code=LB&city=Byblos')
        ->assertJsonPath('data.zone.name', 'Lebanon');
    $this->getJson('/api/v1/shipping/quote?country_code=AE&city=Dubai')
        ->assertJsonPath('data.zone.name', 'Gulf');
    $this->getJson('/api/v1/shipping-zones')->assertJsonPath('meta.total', 3);
    $this->getJson('/api/v1/carriers')->assertJsonPath('meta.total', 3);
});
