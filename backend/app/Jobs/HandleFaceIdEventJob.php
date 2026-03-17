<?php

namespace App\Jobs;

use App\Services\FaceIDEventService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleFaceIdEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $raw)
    {
    }

    public function handle(): void
    {
        try {
            $payload = json_decode($this->raw, true, 512, JSON_THROW_ON_ERROR);
            FaceIDEventService::handle($payload);
        } catch (\Throwable $exception) {
            Log::error('FaceID job error', [
                'error' => $exception->getMessage(),
                'raw' => $this->raw,
            ]);
        }
    }
}
