<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_admin_panel(): void
    {
        $this->get('/admin')
            ->assertRedirect('/login');
    }

    public function test_user_without_filament_access_cannot_open_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_user_with_filament_access_can_open_admin_panel(): void
    {
        $permission = Permission::query()->create([
            'code' => 'filament.access',
            'name' => 'Filament access',
            'group' => 'admin',
        ]);

        $role = Role::query()->create([
            'code' => 'support_admin',
            'name' => 'Support Admin',
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }
}
