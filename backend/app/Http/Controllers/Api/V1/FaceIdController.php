<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\HandleFaceIdEventJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceIdController extends Controller
{
    public function storeVerify(Request $request): JsonResponse
    {
        return $this->queueResponse($request->getContent(), 'VerifyPush');
    }

    public function storeHeartbeat(Request $request): JsonResponse
    {
        return $this->queueResponse($request->getContent(), 'HeartBeat');
    }

    public function storeEventFaceID(Request $request): JsonResponse
    {
        return $this->queueResponse($request->getContent());
    }

    private function queueResponse(string $raw, ?string $fallbackOperator = null): JsonResponse
    {
        if ($fallbackOperator !== null) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded) && !isset($decoded['operator'])) {
                $decoded['operator'] = $fallbackOperator;
                $raw = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $raw;
            }
        }

        $response = response()->json([
            'code' => 200,
            'desc' => 'OK',
        ]);

        $response->send();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        HandleFaceIdEventJob::dispatch($raw);

        return $response;
    }
}
