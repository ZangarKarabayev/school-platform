<?php

namespace Database\Seeders;

use App\Modules\Access\Enums\RoleCode;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            RoleCode::Teacher->value => 'Учитель',
            RoleCode::Director->value => 'Директор',
            RoleCode::DistrictOperator->value => 'Ответственный по району',
            RoleCode::RegionOperator->value => 'Ответственный по области',
            RoleCode::SuperAdmin->value => 'Супер администратор',
            RoleCode::SupportAdmin->value => 'Техподдержка',
        ];

        foreach ($roles as $code => $name) {
            Role::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_system' => true],
            );
        }

        $permissions = [
            ['code' => 'filament.access', 'name' => 'Доступ в админ панель', 'group' => 'admin'],
            ['code' => 'schools.view', 'name' => 'Просмотр школ', 'group' => 'organizations'],
            ['code' => 'reports.school.view', 'name' => 'Просмотр отчетов школы', 'group' => 'reports'],
            ['code' => 'reports.district.view', 'name' => 'Просмотр отчетов района', 'group' => 'reports'],
            ['code' => 'reports.region.view', 'name' => 'Просмотр отчетов области', 'group' => 'reports'],
            ['code' => 'students.manage', 'name' => 'Управление учениками', 'group' => 'students'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['code' => $permission['code']],
                $permission,
            );
        }

        $rolePermissions = [
            RoleCode::Teacher->value => ['students.manage'],
            RoleCode::Director->value => ['schools.view', 'reports.school.view'],
            RoleCode::DistrictOperator->value => ['schools.view', 'reports.district.view'],
            RoleCode::RegionOperator->value => ['schools.view', 'reports.region.view'],
            RoleCode::SuperAdmin->value => ['filament.access', 'schools.view', 'reports.school.view', 'reports.district.view', 'reports.region.view', 'students.manage'],
            RoleCode::SupportAdmin->value => ['filament.access', 'schools.view'],
        ];

        foreach ($rolePermissions as $roleCode => $permissionCodes) {
            $role = Role::query()->where('code', $roleCode)->firstOrFail();
            $permissionIds = Permission::query()
                ->whereIn('code', $permissionCodes)
                ->pluck('id');

            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
