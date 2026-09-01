<?php

namespace Tests\Feature\Web;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentEditPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_photo_is_shown_on_the_edit_page(): void
    {
        $user = User::factory()->create();
        $student = Student::query()->create([
            'first_name' => 'Azim',
            'photo' => 'user/photos/azim.jpg',
            'school_year' => '2026-2027',
        ]);

        $this->actingAs($user)
            ->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee('class="student-edit-photo"', false)
            ->assertSee('src="'.route('students.photo.show', $student).'"', false);
    }

    public function test_student_initial_is_shown_when_the_photo_is_missing(): void
    {
        $user = User::factory()->create();
        $student = Student::query()->create([
            'last_name' => 'Айдаров',
            'school_year' => '2026-2027',
        ]);

        $this->actingAs($user)
            ->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee('class="student-edit-photo-placeholder"', false)
            ->assertSee('А');
    }
}
