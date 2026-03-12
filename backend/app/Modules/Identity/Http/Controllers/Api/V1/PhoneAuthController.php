<?php

namespace App\Modules\Identity\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Access\Application\Services\AccessMap;
use App\Modules\Identity\Application\Actions\StartPhoneLoginAction;
use App\Modules\Identity\Application\Actions\VerifyPhoneOtpAction;
use App\Modules\Identity\Application\DTO\PhoneLoginData;
use App\Modules\Identity\Application\DTO\VerifyPhoneOtpData;
use App\Modules\Identity\Http\Requests\RequestPhoneOtpRequest;
use App\Modules\Identity\Http\Requests\VerifyPhoneOtpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneAuthController extends Controller
{
    public function requestOtp(
        RequestPhoneOtpRequest $request,
        StartPhoneLoginAction $action,
    ): JsonResponse {
        $payload = new PhoneLoginData(
            phone: $request->string('phone')->toString(),
            purpose: $request->string('purpose')->toString() ?: 'login',
        );

        return response()->json($action->execute($payload), 202);
    }

    public function verifyOtp(
        VerifyPhoneOtpRequest $request,
        VerifyPhoneOtpAction $action,
    ): JsonResponse {
        $payload = new VerifyPhoneOtpData(
            phone: $request->string('phone')->toString(),
            code: $request->string('code')->toString(),
            purpose: $request->string('purpose')->toString() ?: 'login',
            deviceName: $request->string('device_name')->toString() ?: 'mobile',
        );

        $result = $action->execute($payload);

        return response()->json([
            'token' => $result['token'],
            'user' => $this->userPayload($result['user']),
        ]);
    }

    public function me(Request $request, AccessMap $accessMap): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user->loadMissing('roles', 'scopes'), $accessMap),
        ]);
    }

    private function userPayload(User $user, ?AccessMap $accessMap = null): array
    {
        $accessMap ??= app(AccessMap::class);

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'phone' => $user->phone,
            'status' => $user->status,
            'preferred_locale' => $user->preferred_locale,
            'roles' => $user->roles->pluck('code')->values()->all(),
            'permissions' => $accessMap->permissionsFor($user),
            'scopes' => $user->scopes->map(fn ($scope): array => [
                'type' => $scope->scope_type,
                'id' => $scope->scope_id,
            ])->values()->all(),
        ];
    }
}
