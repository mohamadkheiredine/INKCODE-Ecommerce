<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEntry>
 */
class AuditEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'user_id' => User::factory(),
            'action' => AuditAction::Updated,
            'record_type' => 'category',
            'record_id' => fake()->numberBetween(1, 1000),
            'record_label' => $name,
            'before' => ['name' => fake()->word()],
            'after' => ['name' => $name],
        ];
    }
}
