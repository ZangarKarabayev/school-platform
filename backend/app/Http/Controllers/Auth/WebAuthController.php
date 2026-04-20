<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Access\Enums\RoleCode;
use App\Modules\Access\Models\Role;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
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
use Illuminate\Support\Facades\Hash;
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

    public function showPendingActivation(Request $request): View
    {
        return view('auth.pending-activation', [
            'reason' => $request->string('reason')->toString(),
        ]);
    }

    public function showPhoneRegister(): View
    {
        return view('auth.register-phone', $this->registrationFormData());
    }

    public function showEdsRegister(Request $request, StartEdsLoginAction $action): View
    {
        return view('auth.register-eds', [
            'edsChallenge' => $this->resolveEdsChallenge($request, $action),
            ...$this->registrationFormData(),
        ]);
    }

    public function loginByPhone(Request $request): RedirectResponse
    {
        $this->normalizePhoneInput($request);

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{11,15}$/'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('phone', $data['phone'])->first();

        if ($user && Hash::check($data['password'], (string) $user->password) && $user->status !== 'active') {
            return redirect()->route('auth.pending');
        }

        if (! Auth::attempt(
            ['phone' => $data['phone'], 'password' => $data['password'], 'status' => 'active'],
            (bool) $request->boolean('remember'),
        )) {
            return back()
                ->withErrors(['phone_login' => __('ui.auth.login_phone_error')])
                ->withInput($request->except('password'));
        }

        $request->session()->regenerate();
        $user = $request->user();
        $user?->forceFill(['last_login_at' => now()])->save();
        $this->syncKitchenGuard($user, (bool) $request->boolean('remember'));

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
            'role' => ['required', 'string', Rule::in($this->allowedRegistrationRoleCodes())],
            'school_id' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), [
                    RoleCode::Teacher->value,
                    RoleCode::Director->value,
                ], true)),
                'nullable',
                'integer',
                'exists:schools,id',
            ],
            'district_id' => [
                Rule::requiredIf(fn () => $request->input('role') === RoleCode::DistrictOperator->value),
                'nullable',
                'integer',
                'exists:districts,id',
            ],
            'region_id' => [
                Rule::requiredIf(fn () => $request->input('role') === RoleCode::RegionOperator->value),
                'nullable',
                'integer',
                'exists:regions,id',
            ],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $role = Role::query()
                ->where('code', $data['role'])
                ->firstOrFail();

            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'middle_name' => $data['middle_name'] ?: null,
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => 'inactive',
                'school_id' => $this->resolveRegistrationSchoolId($data),
                'district_id' => $this->resolveRegistrationDistrictId($data),
                'region_id' => $this->resolveRegistrationRegionId($data),
                'preferred_locale' => $this->preferredLocale(),
            ]);

            $user->roles()->sync([$role->id]);

            AuthIdentity::query()->create([
                'user_id' => $user->id,
                'type' => AuthIdentityType::Phone->value,
                'phone' => $data['phone'],
                'last_verified_at' => now(),
            ]);

            return $user;
        });

        return redirect()
            ->route('auth.pending');
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

        if ($user->status !== 'active') {
            return redirect()
                ->route('auth.pending');
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
        $this->syncKitchenGuard($user);

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
            'role' => ['required', 'string', Rule::in($this->allowedRegistrationRoleCodes())],
            'school_id' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), [
                    RoleCode::Teacher->value,
                    RoleCode::Director->value,
                ], true)),
                'nullable',
                'integer',
                'exists:schools,id',
            ],
            'district_id' => [
                Rule::requiredIf(fn () => $request->input('role') === RoleCode::DistrictOperator->value),
                'nullable',
                'integer',
                'exists:districts,id',
            ],
            'region_id' => [
                Rule::requiredIf(fn () => $request->input('role') === RoleCode::RegionOperator->value),
                'nullable',
                'integer',
                'exists:regions,id',
            ],
        ]);

        if ($this->findEdsIdentity($verified->certificateThumbprint, $verified->certificateSerial) !== null) {
            return back()->withErrors([
                'eds_register' => __('ui.auth.eds_already_bound'),
            ])->withInput();
        }

        $user = DB::transaction(function () use ($challenge, $verified, $data): User {
            $role = Role::query()
                ->where('code', $data['role'])
                ->firstOrFail();

            $user = User::query()->create([
                'first_name' => $verified->firstName,
                'last_name' => $verified->lastName,
                'middle_name' => $verified->middleName,
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => 'inactive',
                'school_id' => $this->resolveRegistrationSchoolId($data),
                'district_id' => $this->resolveRegistrationDistrictId($data),
                'region_id' => $this->resolveRegistrationRegionId($data),
                'preferred_locale' => $this->preferredLocale(),
            ]);

            $user->roles()->sync([$role->id]);

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

        return redirect()
            ->route('auth.pending');
    }

    public function dashboard(Request $request): View
    {
        return view('dashboard', [
            'user' => $request->user()->loadMissing('roles', 'scopes'),
        ]);
    }

    public function editProfile(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user()->loadMissing('roles', 'scopes'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $this->normalizePhoneInput($request);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{11,15}$/', Rule::unique('users', 'phone')->ignore($request->user()?->id)],
            'preferred_locale' => ['required', 'string', Rule::in(['ru', 'kk'])],
        ]);

        $request->user()?->forceFill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'middle_name' => $data['middle_name'] ?: null,
            'phone' => $data['phone'],
            'preferred_locale' => $data['preferred_locale'],
        ])->save();

        return redirect()
            ->route('profile.edit')
            ->with('profile_status', __('ui.profile_page.profile_saved'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! $user || ! Hash::check($data['current_password'], (string) $user->password)) {
            return back()
                ->withErrors([
                    'current_password' => __('ui.profile_page.current_password_invalid'),
                ])
                ->withInput()
                ->withFragment('password');
        }

        $user->forceFill([
            'password' => $data['password'],
        ])->save();

        return redirect()
            ->to(route('profile.edit').'#password')
            ->with('password_status', __('ui.profile_page.password_saved'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('kitchen')->logout();
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

    /**
     * @return array{roles:\Illuminate\Support\Collection<int, Role>,schools:\Illuminate\Support\Collection<int, School>,districts:\Illuminate\Support\Collection<int, District>,regions:\Illuminate\Support\Collection<int, Region>}
     */
    private function registrationFormData(): array
    {
        return [
            'roles' => Role::query()
                ->whereIn('code', $this->allowedRegistrationRoleCodes())
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'schools' => School::query()
                ->orderBy('name_ru')
                ->orderBy('name_kk')
                ->get(['id', 'district_id', 'name', 'name_ru', 'name_kk']),
            'districts' => District::query()
                ->orderBy('name_ru')
                ->orderBy('name_kk')
                ->get(['id', 'region_id', 'name', 'name_ru', 'name_kk']),
            'regions' => Region::query()
                ->orderBy('name_ru')
                ->orderBy('name_kk')
                ->get(['id', 'name', 'name_ru', 'name_kk']),
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedRegistrationRoleCodes(): array
    {
        return array_values(array_filter(
            array_column(RoleCode::cases(), 'value'),
            fn (string $code): bool => $code !== RoleCode::SuperAdmin->value,
        ));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveRegistrationSchoolId(array $data): ?int
    {
        return in_array($data['role'] ?? null, [RoleCode::Teacher->value, RoleCode::Director->value], true)
            ? (int) ($data['school_id'] ?? 0) ?: null
            : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveRegistrationDistrictId(array $data): ?int
    {
        return ($data['role'] ?? null) === RoleCode::DistrictOperator->value
            ? (int) ($data['district_id'] ?? 0) ?: null
            : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveRegistrationRegionId(array $data): ?int
    {
        return ($data['role'] ?? null) === RoleCode::RegionOperator->value
            ? (int) ($data['region_id'] ?? 0) ?: null
            : null;
    }

    private function syncKitchenGuard(?User $user, bool $remember = false): void
    {
        if (! $user) {
            return;
        }

        $user->loadMissing('roles');

        $hasKitchenAccess = $user->hasRole(RoleCode::Kitchen->value)
            || $user->hasRole(RoleCode::SuperAdmin->value)
            || $user->hasRole(RoleCode::SupportAdmin->value);

        if ($hasKitchenAccess) {
            Auth::guard('kitchen')->login($user, $remember);

            return;
        }

        Auth::guard('kitchen')->logout();
    }
}
