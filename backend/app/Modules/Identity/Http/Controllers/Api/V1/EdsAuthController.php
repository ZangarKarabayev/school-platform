<?php

namespace App\Modules\Identity\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Actions\StartEdsLoginAction;
use App\Modules\Identity\Application\Actions\VerifyEdsLoginAction;
use App\Modules\Identity\Application\DTO\EdsChallengeData;
use App\Modules\Identity\Application\DTO\VerifyEdsData;
use App\Modules\Identity\Http\Requests\StartEdsLoginRequest;
use App\Modules\Identity\Http\Requests\VerifyEdsLoginRequest;
use Illuminate\Http\JsonResponse;

class EdsAuthController extends Controller
{
    public function start(
        StartEdsLoginRequest $request,
        StartEdsLoginAction $action,
    ): JsonResponse {
        $payload = new EdsChallengeData(
            deviceName: $request->string('device_name')->toString() ?: 'web',
        );

        return response()->json($action->execute($payload), 201);
    }

    public function verify(
        VerifyEdsLoginRequest $request,
        VerifyEdsLoginAction $action,
    ): JsonResponse {
        $payload = new VerifyEdsData(
            challengeId: $request->integer('challenge_id'),
            signature: $request->string('signature')->toString(),
            lastName: $request->string('last_name')->toString(),
            firstName: $request->string('first_name')->toString(),
            middleName: $request->string('middle_name')->toString() ?: null,
            deviceName: $request->string('device_name')->toString() ?: 'web',
        );

        $result = $action->execute($payload);

        return response()->json([
            'token' => $result['token'],
            'user' => [
                'id' => $result['user']->id,
                'full_name' => $result['user']->full_name,
                'phone' => $result['user']->phone,
                'roles' => $result['user']->roles->pluck('code')->values()->all(),
                'scopes' => $result['user']->scopes->map(fn ($scope): array => [
                    'type' => $scope->scope_type,
                    'id' => $scope->scope_id,
                ])->values()->all(),
            ],
        ]);
    }
}
