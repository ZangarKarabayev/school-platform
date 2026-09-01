<?php

namespace Tests\Feature\Web;

use App\Models\AcademicClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_a_class_from_the_classes_list(): void
    {
        $user = User::factory()->create();
        $classroom = AcademicClass::query()->create([
            'grade' => 1,
            'letter' => 'А',
        ]);

        $this->actingAs($user)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertSee('href="'.route('classes.show', $classroom).'"', false)
            ->assertSee('data-class-url="'.route('classes.show', $classroom).'"', false)
            ->assertSee('class="classes-list-row is-clickable"', false);

        $this->actingAs($user)
            ->get(route('classes.show', $classroom))
            ->assertOk();
    }
}
