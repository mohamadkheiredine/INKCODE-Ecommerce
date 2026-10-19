<?php

namespace App\Actions\Profile;

use App\Enums\Locale;
use App\Enums\Theme;
use App\Models\User;

/**
 * Keeps a User's language and theme on their account, so they apply on every device.
 * A preference that is not given stays as it was.
 */
final class UpdatePreferences
{
    public function handle(User $user, ?Locale $locale, ?Theme $theme): User
    {
        $user->fill(array_filter(['locale' => $locale, 'theme' => $theme]))->save();

        return $user;
    }
}
