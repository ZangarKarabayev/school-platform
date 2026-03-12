<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Application\Actions\StartEdsLoginAction;
use App\Modules\Identity\Application\Actions\ValidateEdsChallengeAction;
use App\Modules\Identity\Application\DTO\EdsChallengeData;
use App\Modules\Identity\Application\DTO\VerifyEdsData;
use App\Modules\Identity\Enums\AuthIdentityType;
use App\Modules\Identity\Models\AuthIdentity;
use App\Modules\Identity\Models\EdsChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class WebAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showEdsLogin(Request $request, StartEdsLoginAction $action): View
    {
        return view('auth.login-eds', [
            'edsChallenge' => $this->resolveEdsChallenge($request, $action),
        ]);
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function showPhoneRegister(): View
    {
        return view('auth.register-phone');
    }

    public function showEdsRegister(Request $request, StartEdsLoginAction $action): View
    {
        return view('auth.register-eds', [
            'edsChallenge' => $this->resolveEdsChallenge($request, $action),
        ]);
    }

    public function loginByPhone(Request $request): RedirectResponse
    {
        $this->normalizePhoneInput($request);

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{11,15}$/'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(
            ['phone' => $data['phone'], 'password' => $data['password']],
            (bool) $request->boolean('remember'),
        )) {
            return back()
                ->withErrors(['phone_login' => __('ui.auth.login_phone_error')])
                ->withInput($request->except('password'));
        }

        $request->session()->regenerate();
        $request->user()?->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function registerByPhone(Request $request): RedirectResponse
    {
        $this->normalizePhoneInput($request);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{11,15}$/', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'middle_name' => $data['middle_name'] ?: null,
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => 'active',
                'preferred_locale' => $this->preferredLocale(),
            ]);

            AuthIdentity::query()->create([
                'user_id' => $user->id,
                'type' => AuthIdentityType::Phone->value,
                'phone' => $data['phone'],
                'last_verified_at' => now(),
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function createLoginEdsChallenge(StartEdsLoginAction $action): RedirectResponse
    {
        return back()->with('eds_login_challenge', $action->execute(new EdsChallengeData('web')));
    }

    public function loginByEds(Request $request, ValidateEdsChallengeAction $action): RedirectResponse
    {
        $payload = $this->makeEdsPayload($request);

        try {
            ['challenge' => $challenge, 'verified' => $verified] = $action->execute($payload);
        } catch (UnprocessableEntityHttpException $exception) {
            return back()->withErrors(['eds_login' => $exception->getMessage()])->withInput();
        }

        $identity = $this->findEdsIdentity($verified->certificateThumbprint, $verified->certificateSerial);
        $user = $identity?->user;

        if (! $user) {
            return redirect()->route('register')->withErrors([
                'eds_register' => __('ui.auth.eds_user_not_found'),
            ]);
        }

        DB::transaction(function () use ($challenge, $verified, $user): void {
            AuthIdentity::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => AuthIdentityType::Eds->value,
                ],
                [
                    'certificate_thumbprint' => $verified->certificateThumbprint,
                    'certificate_serial' => $verified->certificateSerial,
                    'subject_dn' => $verified->subjectDn,
                    'issuer_dn' => $verified->issuerDn,
                    'valid_from' => $verified->validFrom,
                    'valid_to' => $verified->validTo,
                    'last_verified_at' => now(),
                ],
            );

            $challenge->forceFill([
                'verified_at' => now(),
                'consumed_at' => now(),
            ])->save();

            $user->forceFill(['last_login_at' => now()])->save();
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function createRegisterEdsChallenge(StartEdsLoginAction $action): RedirectResponse
    {
        return back()->with('eds_register_challenge', $action->execute(new EdsChallengeData('web')));
    }

    public function previewEdsIdentity(Request $request, ValidateEdsChallengeAction $action): JsonResponse
    {
        $payload = $this->makeEdsPayload($request);

        try {
            ['verified' => $verified] = $action->execute($payload);
        } catch (UnprocessableEntityHttpException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'last_name' => $verified->lastName,
            'first_name' => $verified->firstName,
            'middle_name' => $verified->middleName,
        ]);
    }

    public function registerByEds(Request $request, ValidateEdsChallengeAction $action): RedirectResponse
    {
        $this->normalizePhoneInput($request);
        $payload = $this->makeEdsPayload($request);

        try {
            ['challenge' => $challenge, 'verified' => $verified] = $action->execute($payload);
        } catch (UnprocessableEntityHttpException $exception) {
            return back()->withErrors(['eds_register' => $exception->getMessage()])->withInput();
        }

        $data = $request->validate([
            'phone' => [
                'required',
                'string',
                'regex:/^\+?[0-9]{11,15}$/',
                Rule::unique('users', 'phone'),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($this->findEdsIdentity($verified->certificateThumbprint, $verified->certificateSerial) !== null) {
            return back()->withErrors([
                'eds_register' => __('ui.auth.eds_already_bound'),
            ])->withInput();
        }

        $user = DB::transaction(function () use ($challenge, $verified, $data): User {
            $user = User::query()->create([
                'first_name' => $verified->firstName,
                'last_name' => $verified->lastName,
                'middle_name' => $verified->middleName,
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => 'active',
                'preferred_locale' => $this->preferredLocale(),
            ]);

            AuthIdentity::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => AuthIdentityType::Eds->value,
                ],
                [
                    'certificate_thumbprint' => $verified->certificateThumbprint,
                    'certificate_serial' => $verified->certificateSerial,
                    'subject_dn' => $verified->subjectDn,
                    'issuer_dn' => $verified->issuerDn,
                    'valid_from' => $verified->validFrom,
                    'valid_to' => $verified->validTo,
                    'last_verified_at' => now(),
                ],
            );

            $challenge->forceFill([
                'verified_at' => now(),
                'consumed_at' => now(),
            ])->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function dashboard(Request $request): View
    {
        return view('dashboard', [
            'user' => $request->user()->loadMissing('roles', 'scopes'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function makeEdsPayload(Request $request): VerifyEdsData
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'integer'],
            'signature' => ['required', 'string'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
        ]);

        return new VerifyEdsData(
            challengeId: (int) $data['challenge_id'],
            signature: $data['signature'],
            lastName: $data['last_name'] ?? '',
            firstName: $data['first_name'] ?? '',
            middleName: $data['middle_name'] ?? null,
            deviceName: 'web',
        );
    }

    private function normalizePhoneInput(Request $request): void
    {
        $phone = $request->input('phone');

        if (! is_string($phone)) {
            return;
        }

        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if ($normalized === '') {
            return;
        }

        if (str_starts_with($normalized, '8') && strlen($normalized) === 11) {
            $normalized = '7'.substr($normalized, 1);
        }

        $request->merge([
            'phone' => '+'.$normalized,
        ]);
    }

    private function resolveEdsChallenge(Request $request, StartEdsLoginAction $action): array
    {
        $existingChallengeId = $request->old('challenge_id');

        if (is_numeric($existingChallengeId)) {
            $challenge = EdsChallenge::query()->find((int) $existingChallengeId);

            if ($challenge && $challenge->expires_at->isFuture() && $challenge->consumed_at === null) {
                return [
                    'challenge_id' => $challenge->id,
                    'challenge' => $challenge->challenge,
                    'expires_at' => $challenge->expires_at->toIso8601String(),
                ];
            }
        }

        return $action->execute(new EdsChallengeData('web'));
    }

    private function findEdsIdentity(string $thumbprint, ?string $serial): ?AuthIdentity
    {
        return AuthIdentity::query()
            ->where('type', AuthIdentityType::Eds->value)
            ->where(function ($query) use ($thumbprint, $serial): void {
                $query->where('certificate_thumbprint', $thumbprint);

                if ($serial !== null && $serial !== '') {
                    $query->orWhere('certificate_serial', $serial);
                }
            })
            ->first();
    }

    private function preferredLocale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, ['ru', 'kk'], true) ? $locale : 'ru';
    }
}
