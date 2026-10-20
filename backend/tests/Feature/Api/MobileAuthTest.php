<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Identity\Application\Actions\IssueApiTokenAction;
use App\Modules\Identity\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_users_cannot_log_in_with_password(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);

        $this->postJson('/api/mobile/v1/auth/phone/login', [
            'phone' => $user->phone, 'password' => 'password',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_expired_invalid_and_inactive_user_tokens_are_rejected_as_json(): void
    {
        $user = User::factory()->create();
        $token = app(IssueApiTokenAction::class)->execute($user)['token'];
        ApiToken::query()->update(['expires_at' => now()->subMinute()]);
        $this->withToken($token)->get('/api/mobile/v1/auth/me')->assertUnauthorized();

        ApiToken::query()->update(['expires_at' => now()->addDay()]);
        $user->update(['status' => 'inactive']);
        $this->withToken($token)->getJson('/api/mobile/v1/auth/me')->assertUnauthorized();
        $this->withToken('invalid')->getJson('/api/mobile/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_revokes_only_current_device_and_logout_all_revokes_remaining_devices(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issue = app(IssueApiTokenAction::class);
        $first = $issue->execute($user, 'iphone')['token'];
        $second = $issue->execute($user, 'android')['token'];
        $issue->execute($other);

        $this->withToken($first)->postJson('/api/mobile/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($first)->getJson('/api/mobile/v1/auth/me')->assertUnauthorized();
        $this->withToken($second)->getJson('/api/mobile/v1/auth/me')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($second)->postJson('/api/mobile/v1/auth/logout-all')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($second)->getJson('/api/mobile/v1/auth/me')->assertUnauthorized();
        $this->assertDatabaseCount('api_tokens', 1);
        $this->assertDatabaseHas('api_tokens', ['user_id' => $other->id]);
    }

    public function test_otp_is_normalized_single_use_and_does_not_assign_roles(): void
    {
        $response = $this->postJson('/api/mobile/v1/auth/phone/request-otp', ['phone' => '8 (701) 555-44-33']);
        $response->assertAccepted()->assertJsonPath('phone', '+77015554433');
        $payload = ['phone' => '+77015554433', 'code' => $response->json('debug_code')];

        $this->postJson('/api/mobile/v1/auth/phone/verify-otp', $payload)
            ->assertOk()->assertJsonPath('user.roles', []);
        $this->postJson('/api/mobile/v1/auth/phone/verify-otp', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_otp_does_not_activate_an_inactive_account(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        $response = $this->postJson('/api/mobile/v1/auth/phone/request-otp', ['phone' => $user->phone]);
        $this->postJson('/api/mobile/v1/auth/phone/verify-otp', [
            'phone' => $user->phone, 'code' => $response->json('debug_code'),
        ])->assertForbidden();
        $this->assertDatabaseCount('api_tokens', 0);
        $this->assertSame('inactive', $user->fresh()->status);
    }

    public function test_login_attempts_are_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/mobile/v1/auth/phone/login', [
                'phone' => '+77010000000', 'password' => 'incorrect',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/mobile/v1/auth/phone/login', [
            'phone' => '8 (701) 000-00-00', 'password' => 'incorrect',
        ])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_otp_delivery_is_not_reported_as_success_without_a_provider(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->postJson('/api/mobile/v1/auth/phone/request-otp', ['phone' => '+77010000000'])
            ->assertStatus(503)->assertJsonMissingPath('debug_code');
        $this->assertDatabaseCount('otp_codes', 0);
    }
}
