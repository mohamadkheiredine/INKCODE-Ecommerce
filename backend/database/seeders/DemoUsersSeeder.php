<?php

namespace Database\Seeders;

use App\Enums\Locale;
use App\Enums\NotificationType;
use App\Enums\Theme;
use App\Models\User;
use App\Notifications\Welcome;
use App\Support\DemoAccounts;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DemoAccounts::all() as $account) {
            $user = User::query()->firstOrNew(['email' => $account->email]);

            // forceFill: `is_active` is deliberately not mass assignable. Re-seeding brings each
            // demo account back to its documented password, English and the light theme.
            $user->forceFill([
                'name' => $account->name,
                'password' => DemoAccounts::PASSWORD,
                'locale' => Locale::En,
                'theme' => Theme::Light,
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $user->assignOnlyRole($account->role);

            // One unread welcome, so the bell can be shown working from the first sign-in.
            $user->notifications()->where('type', NotificationType::Welcome->value)->delete();
            $user->notify(new Welcome);
        }
    }
}
