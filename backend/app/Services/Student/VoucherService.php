<?php

namespace App\Services\Student;

use App\Contracts\Student\VoucherServiceContract;
use App\Models\MealBenefit;
use App\Models\Student;
use Exception;

class VoucherService implements VoucherServiceContract
{
    public function handleVoucherActivation(array $requestData): array
    {
        $validationError = $this->validateRequestData($requestData);

        if ($validationError !== null) {
            return $validationError;
        }

        try {
            $students = Student::query()
                ->with('latestMealBenefit')
                ->where('iin', $requestData['iin'])
                ->when(
                    !empty($requestData['school_bin']),
                    fn ($query) => $query->whereHas(
                        'school',
                        fn ($schoolQuery) => $schoolQuery->where('bin', $requestData['school_bin'])
                    )
                )
                ->get();

            if ($students->isEmpty()) {
                return [
                    'result' => 'error',
                    'code' => '10',
                    'error' => 'Student not found',
                    'exist' => '0',
                ];
            }

            if ($students->count() > 1) {
                return [
                    'result' => 'data',
                    'exist' => '2',
                    'warning' => '1',
                    'warn_comment' => 'Multiple records found for the provided IIN',
                ];
            }

            $student = $students->first();
            $socialPaymentsStatus = $this->updateSocialPaymentStatus($student, $requestData);

            return [
                'result' => 'data',
                'exist' => '1',
                'social_payments' => $socialPaymentsStatus ? '1' : '0',
                'card_number' => (string) $student->id,
            ];
        } catch (Exception) {
            return [
                'result' => 'error',
                'code' => '20',
                'error' => 'Request processing error',
            ];
        }
    }

    public function getVoucherHistory(array $requestData): array
    {
        $validationError = $this->validateRequestData($requestData);

        if ($validationError !== null) {
            return $validationError;
        }

        try {
            $students = Student::query()
                ->with([
                    'latestMealBenefit',
                    'mealBenefits' => fn ($query) => $query->orderByDesc('created_at'),
                ])
                ->where('iin', $requestData['iin'])
                ->when(
                    !empty($requestData['school_bin']),
                    fn ($query) => $query->whereHas(
                        'school',
                        fn ($schoolQuery) => $schoolQuery->where('bin', $requestData['school_bin'])
                    )
                )
                ->get();

            if ($students->isEmpty()) {
                return [
                    'result' => 'error',
                    'code' => '10',
                    'error' => 'Student not found',
                    'exist' => '0',
                ];
            }

            if ($students->count() > 1) {
                return [
                    'result' => 'data',
                    'exist' => '2',
                    'warning' => '1',
                    'warn_comment' => 'Multiple records found for the provided IIN',
                ];
            }

            $student = $students->first();

            return [
                'result' => 'data',
                'exist' => '1',
                'social_payments' => $this->hasActiveVoucher($student) ? '1' : '0',
                'card_number' => (string) $student->id,
                'current_type' => $student->latestMealBenefit?->type,
                'history' => $student->mealBenefits->map(fn ($benefit): array => [
                    'type' => (string) $benefit->type,
                    'voucher_update_datetime' => $benefit->voucher_update_datetime?->toDateTimeString(),
                    'start_date' => $benefit->start_date?->toDateString(),
                    'end_date' => $benefit->end_date?->toDateString(),
                    'created_at' => $benefit->created_at?->toDateTimeString(),
                ])->values()->all(),
            ];
        } catch (Exception) {
            return [
                'result' => 'error',
                'code' => '20',
                'error' => 'Request processing error',
            ];
        }
    }

    private function validateRequestData(array $requestData): ?array
    {
        if (empty($requestData['iin']) || empty($requestData['school_bin'])) {
            return [
                'result' => 'error',
                'code' => '10',
                'error' => 'Missing input parameters',
            ];
        }

        if (!preg_match('/^\d{12}$/', (string) $requestData['iin'])) {
            return [
                'result' => 'error',
                'code' => '30',
                'error' => 'Invalid IIN format',
            ];
        }

        return null;
    }

    private function updateSocialPaymentStatus(Student $student, array $requestData): int
    {
        if (($requestData['set_socpay'] ?? null) === '1') {
            if ($this->hasActiveVoucher($student)) {
                $student->latestMealBenefit->forceFill([
                    'voucher_update_datetime' => now(),
                ])->save();

                return 1;
            }

            MealBenefit::query()->create([
                'student_id' => $student->id,
                'type' => 'voucher',
                'voucher_update_datetime' => now(),
            ]);

            return 1;
        }

        if (($requestData['reset_socpay'] ?? null) === '1') {
            if ($this->hasActiveVoucher($student)) {
                MealBenefit::query()->create([
                    'student_id' => $student->id,
                    'type' => 'paid',
                ]);
            }

            return 0;
        }

        return $this->hasActiveVoucher($student) ? 1 : 0;
    }

    private function hasActiveVoucher(Student $student): bool
    {
        $student->loadMissing('latestMealBenefit');

        return $student->latestMealBenefit?->type === 'voucher';
    }
}