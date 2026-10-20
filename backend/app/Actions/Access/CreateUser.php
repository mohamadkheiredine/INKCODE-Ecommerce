<?php

namespace App\Actions\Access;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\Settings;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Creates an active User with one Role and a temporary password. They start with the
 * store's default language and theme. The audit entry holds the details and the Role,
 * never the password.
 */
final class CreateUser
{
    public function __construct(
        private readonly Settings $settings,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * @param  array{name: string, email: string}  $details
     */
    public function handle(array $details, Role $role, #[\SensitiveParameter] string $temporaryPassword): User
    {
        return DatabaseErrors::refuseDuplicate(fn (): User => DB::transaction(function () use ($details, $role, $temporaryPassword): User {
            $store = $this->settings->current();

            $user = User::query()->create([
                ...$details,
                'password' => $temporaryPassword,
                'locale' => $store->default_locale,
                'theme' => $store->default_theme,
            ]);
            $user->assignOnlyRole($role);

            $this->auditTrail->created($user);

            return $user;
        }), 'email');
    }
}
