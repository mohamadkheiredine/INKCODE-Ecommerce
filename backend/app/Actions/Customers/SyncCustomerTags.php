<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\Tag;

/**
 * Makes a Customer carry exactly these Tags, creating the ones nobody has typed before.
 * Names match ignoring case, so "VIP" reuses an existing "vip". Runs inside the caller's
 * transaction; the caller records the change on the Customer's audit trail.
 */
final class SyncCustomerTags
{
    /**
     * @param  list<string>  $names
     */
    public function handle(Customer $customer, array $names): void
    {
        // firstOrCreate falls back to createOrFirst, so two Users typing the same new Tag
        // at once end up sharing one row instead of failing on the unique name.
        $customer->tags()->sync(array_map(
            fn (string $name): int => Tag::query()->firstOrCreate(['name' => $name])->id,
            $names,
        ));
    }
}
