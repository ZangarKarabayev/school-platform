<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Identity\Enums\AuthIdentityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdsAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_eds_challenge(): void
    {
        $response = $this->postJson('/api/v1/auth/eds/challenge', [
            'device_name' => 'web',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'challenge_id',
                'challenge',
                'expires_at',
            ]);

        $this->assertDatabaseCount('eds_challenges', 1);
    }

    public function test_it_verifies_eds_and_returns_token(): void
    {
        $challengeResponse = $this->postJson('/api/v1/auth/eds/challenge', [
            'device_name' => 'web',
        ]);

        $challengeId = $challengeResponse->json('challenge_id');
        $challenge = $challengeResponse->json('challenge');

        $response = $this->postJson('/api/v1/auth/eds/verify', [
            'challenge_id' => $challengeId,
            'signature' => hash('sha256', implode('|', [
                $challenge,
                'User',
                'Test',
                'Example',
            ])),
            'last_name' => 'User',
            'first_name' => 'Test',
            'middle_name' => 'Example',
            'device_name' => 'web',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('token.token_type', 'Bearer')
            ->assertJsonPath('user.full_name', 'User Test Example');

        $user = User::query()->where('phone', null)->firstOrFail();

        $this->assertDatabaseHas('auth_identities', [
            'user_id' => $user->id,
            'type' => AuthIdentityType::Eds->value,
            'certificate_thumbprint' => hash('sha256', 'User|Test|Example'),
        ]);
    }

    public function test_it_reuses_existing_user_by_certificate_thumbprint_on_eds_login(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Ainur',
            'last_name' => 'Teacher',
            'phone' => '+77012223344',
        ]);

        $thumbprint = hash('sha256', 'Teacher|Ainur|');

        $user->authIdentities()->create([
            'type' => AuthIdentityType::Eds->value,
            'certificate_thumbprint' => $thumbprint,
        ]);

        $challengeResponse = $this->postJson('/api/v1/auth/eds/challenge');
        $challenge = $challengeResponse->json('challenge');

        $this->postJson('/api/v1/auth/eds/verify', [
            'challenge_id' => $challengeResponse->json('challenge_id'),
            'signature' => hash('sha256', implode('|', [
                $challenge,
                'Teacher',
                'Ainur',
                '',
            ])),
            'last_name' => 'Teacher',
            'first_name' => 'Ainur',
        ])->assertOk();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('auth_identities', [
            'certificate_thumbprint' => $thumbprint,
        ]);
    }

    public function test_it_rejects_invalid_eds_signature(): void
    {
        $challengeResponse = $this->postJson('/api/v1/auth/eds/challenge');

        $this->postJson('/api/v1/auth/eds/verify', [
            'challenge_id' => $challengeResponse->json('challenge_id'),
            'signature' => 'invalid-signature',
            'last_name' => 'Bad',
            'first_name' => 'Sign',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid EDS signature.');
    }
}
