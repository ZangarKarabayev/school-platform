<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Identity\Enums\AuthIdentityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requests_phone_otp(): void
    {
        $response = $this->postJson('/api/v1/auth/phone/request-otp', [
            'phone' => '+77015554433',
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('status', 'pending_otp')
            ->assertJsonPath('phone', '+77015554433');

        $this->assertDatabaseHas('otp_codes', [
            'phone' => '+77015554433',
            'purpose' => 'login',
        ]);
    }

    public function test_it_verifies_phone_otp_and_returns_token(): void
    {
        $otpResponse = $this->postJson('/api/v1/auth/phone/request-otp', [
            'phone' => '+77015554433',
        ]);

        $code = $otpResponse->json('debug_code');

        $verifyResponse = $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => '+77015554433',
            'code' => $code,
            'device_name' => 'iphone',
        ]);

        $verifyResponse
            ->assertOk()
            ->assertJsonPath('token.token_type', 'Bearer')
            ->assertJsonPath('user.phone', '+77015554433');

        $user = User::query()->where('phone', '+77015554433')->firstOrFail();

        $this->assertDatabaseHas('auth_identities', [
            'user_id' => $user->id,
            'type' => AuthIdentityType::Phone->value,
            'phone' => '+77015554433',
        ]);

        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_it_returns_authenticated_user_profile(): void
    {
        $otpResponse = $this->postJson('/api/v1/auth/phone/request-otp', [
            'phone' => '+77019990011',
        ]);

        $verifyResponse = $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => '+77019990011',
            'code' => $otpResponse->json('debug_code'),
        ]);

        $token = $verifyResponse->json('token.token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.phone', '+77019990011')
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'full_name',
                    'roles',
                    'permissions',
                    'scopes',
                ],
            ]);
    }
}
