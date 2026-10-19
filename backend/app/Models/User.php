<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Locale;
use App\Enums\Role;
use App\Enums\Theme;
use BackedEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Traits\HasRoles;

/**
 * A staff member who signs in to the admin dashboard. Every User has exactly one {@see Role}.
 *
 * `is_active` is deliberately not fillable: only an Admin action may (de)activate a User.
 *
 * @property Locale $locale
 * @property Theme $theme
 * @property bool $is_active
 */
#[Fillable(['name', 'email', 'password', 'locale', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use HasRoles {
        assignRole as private addRoles;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'locale' => Locale::class,
            'theme' => Theme::class,
        ];
    }

    /**
     * The User's single Role, or null for a User who has not been given one yet.
     */
    public function role(): ?Role
    {
        $name = $this->getRoleNames()->first();

        return $name === null ? null : Role::tryFrom($name);
    }

    /**
     * Give the User exactly one Role, replacing any Role they had. Spatie's
     * `syncRoles()` also lands here, so no path can leave a User with two Roles.
     *
     * @param  string|int|array<mixed>|BackedEnum|RoleContract|Collection<int, mixed>  ...$roles
     *
     * @throws InvalidArgumentException when anything other than one Role is given
     */
    public function assignRole(...$roles): static
    {
        $roles = collect($roles)->flatten()->filter(fn (mixed $role): bool => $role !== null && $role !== '');

        if ($roles->count() !== 1) {
            throw new InvalidArgumentException('A User has exactly one Role.');
        }

        $this->roles()->detach();

        return $this->addRoles($roles->first());
    }

    public function assignOnlyRole(Role $role): static
    {
        return $this->assignRole($role->value);
    }
}
