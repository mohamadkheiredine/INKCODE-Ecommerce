<?php

namespace App\Actions\Profile;

use App\Enums\Locale;
use App\Enums\Theme;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a User's language and theme on their account, so they apply on every device.
 * A preference that is not given stays as it was. A real change is audited.
 */
final class UpdatePreferences
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(User $user, ?Locale $locale, ?Theme $theme): User
    {
        return DB::transaction(function () use ($user, $locale, $theme): User {
            $before = $user->auditValues();
            $user->fill(array_filter(['locale' => $locale, 'theme' => $theme]))->save();
            $this->auditTrail->updated($user, $before);

            return $user;
        });
    }
}
