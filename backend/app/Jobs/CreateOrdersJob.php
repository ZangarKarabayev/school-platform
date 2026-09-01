<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Student;
use App\Services\OrderCalendarService;
use App\Services\Orders\OrderEligibilityService;
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
        public ?int $createdByUserId = null,
        public ?string $schoolYear = null,
    ) {}

    private function resolveDefaultSchoolYear(string $orderDate): string
    {
        $date = \Carbon\Carbon::parse($orderDate);
        $startYear = $date->month >= 9 ? $date->year : $date->year - 1;

        return $startYear.'-'.($startYear + 1);
    }

    public function handle(
        ?OrderCalendarService $orderCalendarService = null,
        ?OrderEligibilityService $orderEligibilityService = null,
    ): void {
        $orderCalendarService ??= app(OrderCalendarService::class);
        $orderEligibilityService ??= app(OrderEligibilityService::class);

        if ($orderCalendarService->isBlockedOrderDate($this->orderDate)) {
            return;
        }

        $students = Student::query()
            ->with(['classroom', 'latestMealBenefit', 'enrollments.classroom'])
            ->whereIn('id', $this->studentIds)
            ->get();

        foreach ($students as $student) {
            $schoolYear = $this->schoolYear ?: $student->school_year ?: $this->resolveDefaultSchoolYear($this->orderDate);
            $eligibility = $orderEligibilityService->evaluate($student, $schoolYear, $this->orderDate);

            if (! $eligibility['eligible']
                || $orderCalendarService->isBlockedOrderDate($this->orderDate, $eligibility['grade'])) {
                continue;
            }

            $classroomId = $eligibility['classroom_id'] ?? $student->classroom_id;
            $resolvedSchoolYear = $schoolYear ?: $this->resolveDefaultSchoolYear($this->orderDate);

            $order = Order::query()->firstOrCreate(
                [
                    'student_id' => $student->id,
                    'order_date' => $this->orderDate,
                ],
                [
                    'school_year' => $resolvedSchoolYear,
                    'classroom_id' => $classroomId,
                    'dish_id' => null,
                    'created_by_user_id' => $this->createdByUserId,
                    'created_by_terminal_id' => null,
                    'order_time' => $this->orderTime,
                    'status' => 'created',
                    'transaction_status' => null,
                    'transaction_error' => null,
                ]
            );

            if ($order->wasRecentlyCreated) {
                SendSocialWalletTransactionJob::dispatch($order->id);
            }
        }
    }
}
