<?php

namespace Tests\Feature\Web;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentNameValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_update_reject_missing_or_empty_names(): void
    {
        $this->actingAs(User::factory()->create());
        $student = Student::query()->create(['first_name' => 'First', 'last_name' => 'Last', 'school_year' => '2026-2027']);
        foreach ([[], ['first_name' => ' ', 'last_name' => null]] as $names) {
            $data = $names + ['school_year' => '2026-2027'];
            $this->post(route('students.store'), $data)->assertSessionHasErrors(['first_name', 'last_name']);
            $this->put(route('students.update', $student), $data)->assertSessionHasErrors(['first_name', 'last_name']);
        }
        $this->assertDatabaseCount('students', 1);
        $this->assertSame('First', $student->fresh()->first_name);
        $this->post(route('students.store'), ['first_name' => 'New', 'last_name' => 'Student', 'school_year' => '2026-2027'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('students', 2);
    }
}
