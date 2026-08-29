<?php

namespace Tests\Feature\Web;

use App\Models\AcademicClass;
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

    public function test_student_form_preloads_every_classroom_in_admin_panel(): void
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

        foreach (range(0, 59) as $index) {
            AcademicClass::query()->create([
                'grade' => 1,
                'letter' => str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $this->actingAs($user)
            ->get('/admin/students/create')
            ->assertOk()
            ->assertSee('159');
    }
}
