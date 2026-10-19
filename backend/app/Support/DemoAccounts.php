<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

/**
 * The local-only demo accounts: one demo User per Role, all with the documented password.
 */
final class DemoAccounts
{
    public const PASSWORD = 'password';

    private const NAMES = [
        'admin' => 'Ahmed Ali',
        'operations' => 'Rania Khoury',
        'inventory' => 'Omar Haddad',
        'sales' => 'Lina Saleh',
        'support' => 'Karim Mansour',
        'finance' => 'Nadia Farouk',
        'viewer' => 'Youssef Nasser',
    ];

    /**
     * @return list<DemoAccount>
     */
    public static function all(): array
    {
        return array_map(
            fn (Role $role): DemoAccount => new DemoAccount($role, self::NAMES[$role->value], "{$role->value}@inkcode.test"),
            Role::cases(),
        );
    }

    /**
     * The demo accounts that exist and may sign in right now, in Role order.
     *
     * @return list<DemoAccount>
     */
    public static function available(): array
    {
        $accounts = self::all();

        $existing = User::query()
            ->where('is_active', true)
            ->whereIn('email', array_map(fn (DemoAccount $account): string => $account->email, $accounts))
            ->pluck('email')
            ->all();

        return array_values(array_filter(
            $accounts,
            fn (DemoAccount $account): bool => in_array($account->email, $existing, true),
        ));
    }
}
