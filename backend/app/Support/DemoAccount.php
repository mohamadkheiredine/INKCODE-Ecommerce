<?php

namespace App\Support;

use App\Enums\Role;

/**
 * One local-only demo account: the demo User created for a Role when demo data is seeded.
 */
final readonly class DemoAccount
{
    public function __construct(
        public Role $role,
        public string $name,
        public string $email,
    ) {}
}
