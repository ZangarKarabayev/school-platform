<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_profile_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee(__('ui.common.profile'));
    }

    public function test_user_can_update_profile_data(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'middle_name' => 'Middle',
            'phone' => '+77010000001',
            'preferred_locale' => 'ru',
        ]);

        $this->actingAs($user)
            ->put('/profile', [
                'first_name' => 'New',
                'last_name' => 'Person',
                'middle_name' => 'Updated',
                'phone' => '8 701 555 44 33',
                'preferred_locale' => 'kk',
            ])
            ->assertRedirect('/profile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'New',
            'last_name' => 'Person',
            'middle_name' => 'Updated',
            'phone' => '+77015554433',
            'preferred_locale' => 'kk',
        ]);
    }

    public function test_user_can_change_password_from_profile_page(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-123',
        ]);

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'old-password-123',
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ])
            ->assertRedirect('/profile#password');

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-456', (string) $user->password));
    }

    public function test_user_cannot_change_password_with_invalid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-123',
        ]);

        $this->actingAs($user)
            ->from('/profile#password')
            ->put('/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ])
            ->assertRedirect('/profile#password')
            ->assertSessionHasErrors('current_password');

        $user->refresh();

        $this->assertTrue(Hash::check('old-password-123', (string) $user->password));
    }
}
