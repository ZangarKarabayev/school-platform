<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Modules\Access\Enums\RoleCode;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPasswordResetActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_password_reset_action_on_user_edit_page(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $admin->roles()->attach($this->makeRole(RoleCode::SuperAdmin->value)->id);

        $this->actingAs($admin)
            ->get("/admin/users/{$target->id}/edit")
            ->assertOk()
            ->assertSeeText('Сбросить пароль');
    }

    public function test_support_admin_sees_password_reset_action_on_user_edit_page(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $admin->roles()->attach($this->makeRole(RoleCode::SupportAdmin->value)->id);

        $this->actingAs($admin)
            ->get("/admin/users/{$target->id}/edit")
            ->assertOk()
            ->assertSeeText('Сбросить пароль');
    }

    public function test_non_privileged_admin_does_not_see_password_reset_action_on_user_edit_page(): void
    {
        $permission = Permission::query()->create([
            'code' => 'filament.access',
            'name' => 'Filament access',
            'group' => 'admin',
        ]);

        $role = Role::query()->create([
            'code' => RoleCode::Director->value,
            'name' => 'Director',
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission->id);

        $admin = User::factory()->create();
        $target = User::factory()->create();

        $admin->roles()->attach($role->id);

        $this->actingAs($admin)
            ->get("/admin/users/{$target->id}/edit")
            ->assertOk()
            ->assertDontSeeText('Сбросить пароль');
    }

    private function makeRole(string $code): Role
    {
        return Role::query()->create([
            'code' => $code,
            'name' => $code,
            'is_system' => true,
        ]);
    }
}
