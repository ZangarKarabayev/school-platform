<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRedirectAfterLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_opening_admin_is_returned_to_admin_after_phone_login(): void
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

        $user = User::factory()->create([
            'phone' => '+77019991122',
            'password' => 'secret123',
        ]);

        $user->roles()->attach($role->id);

        $this->get('/admin')->assertRedirect('/login');

        $this->post('/login/phone', [
            'phone' => '+77019991122',
            'password' => 'secret123',
        ])->assertRedirect('/admin');
    }
}
