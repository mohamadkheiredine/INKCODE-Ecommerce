<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * A Customer with a unique email and a phone.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+961 ## ### ###'),
        ];
    }

    /**
     * Carrying these Tags, made when they don't exist yet.
     */
    public function withTags(string ...$names): static
    {
        return $this->afterCreating(function (Customer $customer) use ($names): void {
            $customer->tags()->attach(array_map(
                fn (string $name): int => Tag::query()->firstOrCreate(['name' => $name])->id,
                $names,
            ));
        });
    }
}
