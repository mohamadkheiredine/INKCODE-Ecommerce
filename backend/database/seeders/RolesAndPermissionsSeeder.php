<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reference data, not demo data: safe to run on every deploy. Running it again
 * brings the database back in line with the matrix in {@see Role::permissions()}.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(PermissionRegistrar $registrar): void
    {
        $registrar->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        PermissionModel::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', array_column(Permission::cases(), 'value'))
            ->delete();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')
                ->syncPermissions(array_map(fn (Permission $p): string => $p->value, $role->permissions()));
        }

        $registrar->forgetCachedPermissions();
    }
}
