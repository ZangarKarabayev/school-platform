<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Student\VoucherServiceContract;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function __construct(
        private readonly VoucherServiceContract $voucherService,
    ) {
    }

    public function activateVoucher(Request $request): JsonResponse
    {
        return response()->json(
            $this->voucherService->handleVoucherActivation($this->extractRequestData($request))
        );
    }

    public function getVoucherHistory(Request $request): JsonResponse
    {
        return response()->json(
            $this->voucherService->getVoucherHistory($this->extractRequestData($request))
        );
    }

    private function extractRequestData(Request $request): array
    {
        $json = $request->json()->all();

        return array_merge(
            is_array($json) ? $json : [],
            $request->query()
        );
    }
}
