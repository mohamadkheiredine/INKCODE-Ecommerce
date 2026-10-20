<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerNote;
use App\Models\Tag;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The demo store's 300 Customers, joined over the last year: most have one to three
 * addresses (the first is the default for billing and shipping), some carry Tags and
 * notes from the demo Sales and Support Users, and every tenth has only a phone. A fixed
 * random seed makes every run produce the same people. Rows are inserted in bulk (a few
 * queries, not thousands); once the demo Customers exist, running it again does nothing.
 * Orders, Lifetime value and Segments come with later tickets (#16, #22, #28).
 */
class DemoCustomersSeeder extends Seeder
{
    public const int CUSTOMERS = 300;

    private const int RANDOM_SEED = 13;

    private const int CHUNK = 500;

    /** @var list<string> */
    private const TAGS = ['wholesale', 'repeat buyer', 'corporate', 'newsletter', 'prefers WhatsApp', 'gift buyer'];

    /** @var array<string, list<string>> */
    private const CITIES = [
        'LB' => ['Beirut', 'Tripoli', 'Sidon', 'Jounieh', 'Zahle', 'Byblos'],
        'AE' => ['Dubai', 'Abu Dhabi', 'Sharjah'],
        'SA' => ['Riyadh', 'Jeddah'],
        'JO' => ['Amman'],
    ];

    /** @var list<string> */
    private const NOTES = [
        'Prefers delivery after 5 pm.',
        'Asked to be called before shipping.',
        'Wants invoices sent to the company email.',
        'Returned an item last season without issues.',
        'Interested in the new collection; follow up next month.',
        'Pays cash on delivery only.',
    ];

    public function run(): void
    {
        if (Customer::query()->where('phone', self::phone(1))->exists()) {
            return;
        }

        $faker = FakerFactory::create();
        $faker->seed(self::RANDOM_SEED);

        $tagIds = array_map(fn (string $name): int => Tag::query()->firstOrCreate(['name' => $name])->id, self::TAGS);
        $authorIds = User::query()
            ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', [Role::Sales->value, Role::Support->value]))
            ->pluck('id')
            ->all();

        $people = [];
        for ($number = 1; $number <= self::CUSTOMERS; $number++) {
            $people[] = $this->person($faker, $number, $tagIds, $authorIds);
        }

        DB::transaction(function () use ($people): void {
            $this->insert('customers', array_column($people, 'customer'));
            /** @var array<string, int> $ids */
            $ids = Customer::query()->whereIn('phone', array_column(array_column($people, 'customer'), 'phone'))->pluck('id', 'phone')->all();

            $addresses = $tags = $notes = [];
            foreach ($people as $person) {
                $customerId = $ids[$person['customer']['phone']];
                foreach ($person['addresses'] as $address) {
                    $addresses[] = ['customer_id' => $customerId, ...$address];
                }
                foreach ($person['tag_ids'] as $tagId) {
                    $tags[] = ['customer_id' => $customerId, 'tag_id' => $tagId];
                }
                foreach ($person['notes'] as $note) {
                    $notes[] = ['customer_id' => $customerId, ...$note];
                }
            }

            $this->insert((new CustomerAddress)->getTable(), $addresses);
            $this->insert('customer_tag', $tags);
            $this->insert((new CustomerNote)->getTable(), $notes);
        });
    }

    /**
     * One demo Customer's rows, before their id is known.
     *
     * @param  list<int>  $tagIds
     * @param  list<int>  $authorIds
     * @return array{customer: array<string, mixed>, addresses: list<array<string, mixed>>, tag_ids: list<int>, notes: list<array<string, mixed>>}
     */
    private function person(Generator $faker, int $number, array $tagIds, array $authorIds): array
    {
        $name = $faker->name();
        $phone = self::phone($number);
        $joinedAt = Carbon::now()->subDays($faker->numberBetween(0, 365))->setTime($faker->numberBetween(8, 20), $faker->numberBetween(0, 59));

        $addresses = [];
        $addressCount = $faker->numberBetween(0, 3);
        for ($index = 0; $index < $addressCount; $index++) {
            $addresses[] = $this->address($faker, $index === 0 ? $name : $faker->name(), $phone, $index === 0, $joinedAt);
        }

        $notes = [];
        if ($authorIds !== [] && $faker->boolean(25)) {
            $writtenAt = $joinedAt->copy()->addDays($faker->numberBetween(0, 30));
            $notes[] = [
                'user_id' => $faker->randomElement($authorIds),
                'body' => $faker->randomElement(self::NOTES),
                'created_at' => $writtenAt,
                'updated_at' => $writtenAt,
            ];
        }

        return [
            'customer' => [
                'name' => $name,
                'email' => $number % 10 === 0 ? null : sprintf('%s.%03d@example.com', str($name)->slug('.'), $number),
                'phone' => $phone,
                'created_at' => $joinedAt,
                'updated_at' => $joinedAt,
            ],
            'addresses' => $addresses,
            'tag_ids' => $faker->boolean(35) ? array_values($faker->randomElements($tagIds, $faker->numberBetween(1, 2))) : [],
            'notes' => $notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function address(Generator $faker, string $recipient, string $phone, bool $isFirst, Carbon $at): array
    {
        $countryCode = $faker->randomElement(['LB', 'LB', 'LB', 'AE', 'SA', 'JO']);

        return [
            'label' => $isFirst ? 'Home' : $faker->randomElement(['Office', 'Parents', 'Warehouse']),
            'recipient_name' => $recipient,
            'phone' => $phone,
            'country_code' => $countryCode,
            'city' => $faker->randomElement(self::CITIES[$countryCode]),
            'line1' => $faker->streetAddress(),
            'line2' => $faker->boolean(40) ? 'Floor '.$faker->numberBetween(1, 12) : null,
            'postal_code' => $faker->boolean(60) ? $faker->numerify('####') : null,
            'is_default_billing' => $isFirst,
            'is_default_shipping' => $isFirst,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    /** Demo phone numbers are unique, so they identify the demo Customers. */
    private static function phone(int $number): string
    {
        return sprintf('+961 3 %03d %03d', intdiv($number, 1000), $number % 1000);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
