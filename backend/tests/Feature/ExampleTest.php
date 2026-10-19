<?php

it('reports healthy', function () {
    $this->get('/up')->assertOk();
});

it('serves versioned API routes as JSON and rejects guests', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});
