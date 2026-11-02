<?php

namespace Tests\Feature\Web;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentParentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_forms_display_parents_section(): void
    {
        $user = User::factory()->create();
        $role = \App\Modules\Access\Models\Role::query()->firstOrCreate(
            ['code' => 'super_admin'], ['name' => 'Super Admin'],
        );
        $user->roles()->attach($role);
        $this->actingAs($user);
        $student = Student::query()->create(['first_name' => 'First', 'last_name' => 'Last']);
        $student->parents()->create(['full_name' => 'Parent Name', 'phone' => '+77001234567']);
        $this->get('/admin/students/'.$student->id.'/edit')
            ->assertOk()->assertSee(__('admin.labels.parents'))->assertSee('+77001234567');
        $this->get('/admin/students/create')
            ->assertOk()->assertSee(__('admin.labels.parents'))->assertSee(__('admin.labels.add_parent'));
    }

    public function test_parents_can_be_created_edited_and_removed_without_iin(): void
    {
        $this->actingAs(User::factory()->create());
        $data = ['first_name' => 'First', 'last_name' => 'Last', 'school_year' => '2026-2027'];
        $this->post(route('students.store'), $data + ['parents' => [
            ['full_name' => 'Parent Name', 'phone' => '+77001234567'],
            ['iin' => '800101300001', 'phone' => '+77001234568', 'gender' => 'male'],
        ]])->assertSessionHasNoErrors()->assertRedirect();
        $student = Student::query()->sole();
        $this->assertCount(2, $student->parents);
        $this->assertNull($student->parents->first()->iin);
        $this->assertSame('Parent Name', $student->parents->first()->full_name);
        $this->get(route('students.edit', $student))->assertOk()->assertSee('+77001234567');
        $this->put(route('students.update', $student), $data + ['parents' => [
            ['phone' => '+7 (700) 999-99-99', 'gender' => 'female'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame('+77009999999', $student->parents()->sole()->phone);
        $this->put(route('students.update', $student), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, $student->parents()->count());
        $this->put(route('students.update', $student), $data + ['parents_submitted' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(0, $student->parents()->count());
    }

    public function test_invalid_parent_data_does_not_create_a_student(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('students.store'), [
            'first_name' => 'First', 'last_name' => 'Last', 'school_year' => '2026-2027',
            'parents' => [['iin' => 'invalid', 'phone' => 'letters', 'gender' => 'invalid']],
        ])->assertSessionHasErrors(['parents.0.iin', 'parents.0.phone', 'parents.0.gender']);
        $this->assertDatabaseCount('students', 0);
    }
}
