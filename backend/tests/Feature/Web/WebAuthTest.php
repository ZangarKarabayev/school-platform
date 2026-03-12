<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(__('ui.auth.login_phone_title'));

        $this->get('/login/eds')
            ->assertOk()
            ->assertSee(__('ui.auth.login_eds_title'));
    }

    public function test_register_selector_and_separate_pages_are_rendered(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee(__('ui.auth.register_methods_title'));

        $this->get('/register/phone')
            ->assertOk()
            ->assertSee(__('ui.auth.register_phone_page_title'));

        $this->get('/register/eds')
            ->assertOk()
            ->assertSee(__('ui.auth.register_eds_page_title'));
    }

    public function test_user_can_register_via_phone_and_password_on_web(): void
    {
        $this->post('/register/phone', [
            'first_name' => 'Aruzhan',
            'last_name' => 'Teacher',
            'middle_name' => 'N',
            'phone' => '+77017778899',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'phone' => '+77017778899',
            'first_name' => 'Aruzhan',
        ]);
    }

    public function test_existing_user_can_login_via_phone_and_password_on_web(): void
    {
        User::factory()->create([
            'first_name' => 'Dana',
            'last_name' => 'Director',
            'phone' => '+77016667788',
            'password' => 'secret123',
        ]);

        $this->post('/login/phone', [
            'phone' => '+77016667788',
            'password' => 'secret123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }
}
