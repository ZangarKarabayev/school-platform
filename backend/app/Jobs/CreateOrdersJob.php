<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateOrdersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  int[]  $studentIds
     */
    public function __construct(
        public array $studentIds,
        public string $orderDate,
        public ?string $orderTime,
    ) {
    }

    public function handle(): void
    {
        foreach ($this->studentIds as $studentId) {
            Order::query()->firstOrCreate(
                [
                    'student_id' => $studentId,
                    'order_date' => $this->orderDate,
                ],
                [
                    'dish_id' => null,
                    'order_time' => $this->orderTime,
                    'status' => 'created',
                    'transaction_status' => null,
                    'transaction_error' => null,
                ]
            );
        }
    }
}
