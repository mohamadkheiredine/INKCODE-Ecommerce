<?php

namespace Database\Seeders;

use App\Contracts\Auditable;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\Brand;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ShippingZone;
use App\Models\StoreSettings;
use App\Models\User;
use App\Support\DemoAccounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The history behind the demo store, so the audit log has something to show: the demo
 * Users set up the catalog, shipping and Customers over the last two weeks, and the
 * Admin adjusted the settings. Every entry's values match the seeded records, so the
 * log agrees with what the screens show. Runs once; entries are never rewritten.
 */
class DemoAuditSeeder extends Seeder
{
    public function run(): void
    {
        if (AuditEntry::query()->exists()) {
            return;
        }

        $users = User::query()->whereIn('email', array_map(fn ($account): string => $account->email, DemoAccounts::all()))
            ->get()
            ->keyBy(fn (User $user): string => strstr($user->email, '@', true) ?: $user->email);
        $at = Carbon::now()->subDays(14)->setTime(9, 0);
        $next = function () use (&$at): Carbon {
            $at = $at->copy()->addMinutes(47);

            return $at;
        };

        foreach (Category::query()->whereNull('parent_id')->orderBy('id')->get() as $category) {
            $this->write($users->get(Role::Admin->value), AuditAction::Created, $category, null, $category->auditValues(), $next());
        }
        foreach (Brand::query()->orderBy('id')->get() as $brand) {
            $this->write($users->get(Role::Operations->value), AuditAction::Created, $brand, null, $brand->auditValues(), $next());
        }
        $renamed = Brand::query()->orderBy('id')->first();
        if ($renamed !== null) {
            $this->write($users->get(Role::Operations->value), AuditAction::Updated, $renamed, ['name' => "{$renamed->name} Co."], ['name' => $renamed->name], $next());
        }
        foreach ([...ShippingZone::query()->orderBy('id')->get(), ...Carrier::query()->orderBy('id')->get()] as $record) {
            $this->write($users->get(Role::Operations->value), AuditAction::Created, $record, null, $record->auditValues(), $next());
        }
        foreach (Customer::query()->orderBy('id')->limit(6)->get() as $customer) {
            $this->write($users->get(Role::Sales->value), AuditAction::Created, $customer, null, $customer->auditValues(), $next());
        }

        $settings = StoreSettings::query()->find(StoreSettings::ID);
        if ($settings !== null) {
            $this->write(
                $users->get(Role::Admin->value),
                AuditAction::Updated,
                $settings,
                ['name' => 'Inkcode Store', 'contact_phone' => null],
                ['name' => $settings->name, 'contact_phone' => $settings->contact_phone],
                $next(),
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function write(?User $user, AuditAction $action, Model&Auditable $record, ?array $before, ?array $after, Carbon $at): void
    {
        (new AuditEntry)->forceFill([
            'user_id' => $user?->id,
            'action' => $action,
            'record_type' => $record->auditRecordType(),
            'record_id' => (int) $record->getKey(),
            'record_label' => $record->auditLabel(),
            'before' => $before,
            'after' => $after,
            'created_at' => $at,
        ])->save();
    }
}
