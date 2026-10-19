<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    /**
     * An Attribute with a unique name and no values yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => Str::title(fake()->unique()->word())];
    }

    /**
     * With these values, in order; each value's code is its name in capitals.
     *
     * @param  list<string>  $names
     */
    public function withValues(array $names): static
    {
        return $this->afterCreating(function (Attribute $attribute) use ($names): void {
            foreach ($names as $position => $name) {
                AttributeValue::factory()->for($attribute)->create([
                    'name' => $name,
                    'code' => Str::upper(Str::slug($name)),
                    'position' => $position,
                ]);
            }
        });
    }
}
