<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\SocialWallet\SocialWalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSocialWalletTransactionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $orderId,
    ) {
    }

    public function handle(SocialWalletService $socialWalletService): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        $socialWalletService->sendMealTransaction($order);
    }
}
