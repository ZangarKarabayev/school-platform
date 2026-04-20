<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Modules\Access\Enums\RoleCode;
use App\Modules\Access\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolRoleBindingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_without_school_is_redirected_to_school_binding_notice(): void
    {
        $role = Role::query()->create([
            'code' => RoleCode::Teacher->value,
            'name' => 'Teacher',
        ]);

        $user = User::factory()->create([
            'school_id' => null,
            'status' => 'active',
        ]);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->get('/reports')
            ->assertRedirect(route('auth.pending', ['reason' => 'school']));
    }

    public function test_school_binding_notice_does_not_block_profile(): void
    {
        $role = Role::query()->create([
            'code' => RoleCode::Director->value,
            'name' => 'Director',
        ]);

        $user = User::factory()->create([
            'school_id' => null,
            'status' => 'active',
        ]);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk();
    }
}
