<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Access\Enums\RoleCode;
use App\Modules\Access\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $admin = User::factory()->create([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'middle_name' => null,
            'phone' => '+77010000000',
            'preferred_locale' => 'ru',
        ]);

        $superAdminRole = Role::query()
            ->where('code', RoleCode::SuperAdmin->value)
            ->firstOrFail();

        $admin->roles()->syncWithoutDetaching([$superAdminRole->id]);
    }
}
