<?php

namespace App\Services\Orders;

use App\Jobs\SendSocialWalletTransactionJob;
use App\Models\Order;
use App\Models\Student;
use App\Services\OrderCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCreationService
{
    public function __construct(
        private readonly OrderEligibilityService $eligibility,
        private readonly OrderCalendarService $calendar,
    ) {}

    public function validateRequestDate(array $data): void
    {
        if (! $this->eligibility->dateBelongsToSchoolYear($data['order_date'], $data['school_year'])) {
            throw ValidationException::withMessages(['order_date' => __('validation.school_year_date')]);
        }
        $now = CarbonImmutable::now(config('app.timezone'));
        $date = CarbonImmutable::parse($data['order_date'], config('app.timezone'));
        if ($date->startOfDay()->gt($now->startOfDay())
            || (! empty($data['order_time']) && CarbonImmutable::parse($data['order_date'].' '.$data['order_time'], config('app.timezone'))->gt($now))) {
            throw ValidationException::withMessages(['order_date' => __('ui.orders.future_date_not_allowed')]);
        }
        if ($this->calendar->isBlockedOrderDate($date)) {
            throw ValidationException::withMessages(['order_date' => $this->calendar->blockedOrderDateMessage($date)]);
        }
    }

    private function targetQuery(array $data, ?int $schoolId): Builder
    {
        $query = Student::query()->when($schoolId !== null, fn ($query) => $query->where('school_id', $schoolId));
        if (($data['target_type'] ?? 'students') === 'students') {
            return $query->whereIn('id', $data['student_ids'] ?? []);
        }

        return $query->where('status', '!=', 'graduated')
            ->whereHas('enrollments', function ($query) use ($data, $schoolId): void {
                $query->where('school_year', $data['school_year'])
                    ->when($schoolId !== null, fn ($query) => $query->where('school_id', $schoolId));
                if ($data['target_type'] === 'classes') {
                    $query->whereIn('classroom_id', $data['classroom_ids'] ?? []);
                } elseif ($data['target_type'] !== 'all') {
                    $query->whereRaw('1 = 0');
                }
            });
    }

    public function eligibleTargetIds(array $data, ?int $schoolId): Collection
    {
        return $this->targetQuery($data, $schoolId)
            ->with(['classroom', 'latestMealBenefit', 'enrollments.classroom'])->get()
            ->filter(fn (Student $student) => $this->eligibility->isEligible($student, $data['school_year'], $data['order_date'], $schoolId))
            ->pluck('id');
    }

    public function create(array $data, ?int $schoolId = null, ?int $createdByUserId = null, bool $requireAllStudentIds = true): array
    {
        // Request date/time checks happen before queuing. Workers still enforce the school calendar.
        if ($this->calendar->isBlockedOrderDate($data['order_date'])) {
            return ['order_date' => $data['order_date'], 'created_count' => 0, 'already_exists_count' => 0, 'skipped_count' => 0, 'results' => []];
        }
        $results = DB::transaction(function () use ($data, $schoolId, $createdByUserId, $requireAllStudentIds): array {
            $query = $this->targetQuery($data, $schoolId);
            $students = $query->orderBy('id')->lockForUpdate()
                ->with(['classroom', 'latestMealBenefit', 'enrollments.classroom'])->get();
            if ($requireAllStudentIds && ($data['target_type'] ?? 'students') === 'students') {
                abort_unless($students->count() === count($data['student_ids']), 403, 'All students must be active records in the assigned school.');
            }
            $results = [];
            foreach ($students as $student) {
                $existing = Order::query()->where('student_id', $student->id)->whereDate('order_date', $data['order_date'])->first();
                if ($existing) {
                    $results[] = ['student_id' => $student->id, 'result' => 'already_exists', 'order_id' => $existing->id, 'reason' => null];

                    continue;
                }
                $schoolYear = ($data['school_year'] ?? null) ?: $student->school_year ?: $this->schoolYearForDate($data['order_date']);
                $allowed = $this->eligibility->evaluate($student, $schoolYear, $data['order_date'], $schoolId);
                if (! $allowed['eligible'] || $this->calendar->isBlockedOrderDate($data['order_date'], $allowed['grade'])) {
                    $results[] = ['student_id' => $student->id, 'result' => 'skipped', 'order_id' => null,
                        'reason' => $allowed['eligible'] ? 'blocked_school_day' : 'not_eligible'];

                    continue;
                }
                $order = Order::query()->firstOrCreate(
                    ['student_id' => $student->id, 'order_date' => $data['order_date']],
                    ['school_year' => $schoolYear, 'classroom_id' => $allowed['classroom_id'],
                        'created_by_user_id' => $createdByUserId, 'order_time' => $data['order_time'] ?? null,
                        'status' => Order::STATUS_CREATED, 'transaction_status' => null],
                );
                $results[] = ['student_id' => $student->id, 'result' => $order->wasRecentlyCreated ? 'created' : 'already_exists',
                    'order_id' => $order->id, 'reason' => null];
            }

            return $results;
        }, 3);
        foreach ($results as $result) {
            if ($result['result'] === 'created') {
                SendSocialWalletTransactionJob::dispatch($result['order_id'])->afterCommit();
            }
        }

        return [
            'order_date' => $data['order_date'],
            'created_count' => count(array_filter($results, fn ($item) => $item['result'] === 'created')),
            'already_exists_count' => count(array_filter($results, fn ($item) => $item['result'] === 'already_exists')),
            'skipped_count' => count(array_filter($results, fn ($item) => $item['result'] === 'skipped')),
            'results' => $results,
        ];
    }

    public function schoolYearForDate(string $orderDate): string
    {
        $date = CarbonImmutable::parse($orderDate);
        $startYear = $date->month >= 9 ? $date->year : $date->year - 1;

        return $startYear.'-'.($startYear + 1);
    }
}
