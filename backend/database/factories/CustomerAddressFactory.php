<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    /**
     * A non-default address in Lebanon. Use {@see defaultForBoth()} for a Customer's first one.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'label' => fake()->randomElement(['Home', 'Office', 'Warehouse']),
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('+961 ## ### ###'),
            'country_code' => 'LB',
            'city' => fake()->randomElement(['Beirut', 'Tripoli', 'Sidon', 'Jounieh']),
            'line1' => fake()->streetAddress(),
            'line2' => null,
            'postal_code' => fake()->numerify('####'),
            'is_default_billing' => false,
            'is_default_shipping' => false,
        ];
    }

    public function defaultForBoth(): static
    {
        return $this->state(['is_default_billing' => true, 'is_default_shipping' => true]);
    }
}
