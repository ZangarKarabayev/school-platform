<?php

namespace App\Jobs;

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
        public ?int $schoolId = null,
    ) {}

    public function handle(
        ?OrderCalendarService $orderCalendarService = null,
        ?OrderEligibilityService $orderEligibilityService = null,
    ): void {
        $schoolId = $this->schoolId ?? null;
        if ($this->createdByUserId !== null) {
            $creator = \App\Models\User::query()->with('roles', 'scopes')->find($this->createdByUserId);
            if (! $creator) {
                return;
            }
            if (($creator->hasRole('teacher') || $creator->hasRole('director'))) {
                $assignedSchoolId = $creator->assignedSchoolId();
                if ($assignedSchoolId === null || ($schoolId !== null && $schoolId !== $assignedSchoolId)) {
                    return;
                }
                $schoolId = $assignedSchoolId;
            }
        }
        $orders = new \App\Services\Orders\OrderCreationService(
            $orderEligibilityService ?? app(OrderEligibilityService::class),
            $orderCalendarService ?? app(OrderCalendarService::class),
        );
        $orders->create([
            'target_type' => 'students',
            'student_ids' => $this->studentIds,
            'order_date' => $this->orderDate,
            'order_time' => $this->orderTime,
            'school_year' => $this->schoolYear,
        ], $schoolId, $this->createdByUserId, requireAllStudentIds: false);
    }
}
