<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentFilterPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_filters_are_restored_from_the_session_and_can_be_reset(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('students.index', [
                'search' => 'Azim',
                'classroom_id' => '12',
                'school_id' => '7',
                'status' => 'paid',
                'photo' => 'with',
                'photo_sync' => 'synced',
            ]))
            ->assertOk()
            ->assertSessionHas('students.filters', [
                'search' => 'Azim',
                'classroom_id' => 12,
                'school_id' => 7,
                'status' => 'paid',
                'photo' => 'with',
                'photo_sync' => 'synced',
            ]);

        $this->actingAs($user)
            ->get(route('students.index'))
            ->assertOk()
            ->assertViewHas('filters', [
                'search' => 'Azim',
                'classroom_id' => 12,
                'school_id' => 7,
                'status' => 'paid',
                'photo' => 'with',
                'photo_sync' => 'synced',
            ]);

        $this->actingAs($user)
            ->get(route('students.index', ['reset_filters' => 1]))
            ->assertOk()
            ->assertSessionMissing('students.filters')
            ->assertViewHas('filters', [
                'search' => '',
                'classroom_id' => null,
                'school_id' => null,
                'status' => '',
                'photo' => '',
                'photo_sync' => '',
            ]);
    }
}
