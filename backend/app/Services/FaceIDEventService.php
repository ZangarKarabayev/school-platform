<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Student;
use App\Models\Terminal;
use App\Models\VerifyEvent;
use App\Modules\Organizations\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FaceIDEventService
{
    public static function handle(array $data): void
    {
        $operator = $data['operator'] ?? null;

        if (!$operator) {
            throw new \InvalidArgumentException('Missing operator field.');
        }

        Log::info('FaceID event handling', [
            'operator' => $operator,
        ]);

        match ($operator) {
            'VerifyPush' => self::handleVerify($data),
            'HeartBeat' => self::handleHeartBeat($data),
            default => null,
        };
    }

    protected static function handleVerify(array $data): void
    {
        $info = self::normalizeInfo($data);
        $deviceId = self::nullableInt($info['DeviceID'] ?? null);
        $studentId = self::nullableInt($info['Notes'] ?? null);

        if ($studentId === null) {
            Log::warning('FaceID verify skipped: invalid student_id in Notes', [
                'device_id' => $deviceId,
                'payload' => $info,
            ]);
            return;
        }

        $createTime = self::parseTimestamp($info['CreateTime'] ?? null);
        if ($createTime->isBefore(Carbon::parse('2026-01-01'))) {
            Log::warning('FaceID verify skipped: create_time too old', [
                'device_id' => $deviceId,
                'student_id' => $studentId,
                'create_time' => $createTime->toDateTimeString(),
            ]);
            return;
        }

        $school = self::resolveSchoolByAddress($info['Address'] ?? null);

        $verifyEvent = VerifyEvent::query()->updateOrCreate(
            [
                'device_id' => $deviceId,
                'unique_qr' => (string) $studentId,
                'create_time' => $createTime,
            ],
            [
                'person_id' => self::nullableInt($info['PersonID'] ?? null),
                'name' => self::nullableString($info['Name'] ?? null),
                'verify_status' => self::nullableInt($info['VerifyStatus'] ?? null),
                'bin' => self::resolveBin($info['Address'] ?? null) ?? 'no data',
            ],
        );

        Log::info('FaceID verify event saved', [
            'verify_event_id' => $verifyEvent->id,
            'device_id' => $verifyEvent->device_id,
            'student_id' => $studentId,
            'create_time' => optional($verifyEvent->create_time)?->toDateTimeString(),
            'verify_status' => $verifyEvent->verify_status,
        ]);

        self::upsertTerminal($info, $school?->id, $createTime);

        $student = Student::query()
            ->with(['classroom', 'latestMealBenefit'])
            ->find($studentId);

        if (!$student || !$student->classroom_id || !$student->school_id) {
            Log::warning('FaceID verify saved without matched student', [
                'verify_event_id' => $verifyEvent->id,
                'student_id' => $studentId,
                'student_found' => (bool) $student,
                'classroom_id' => $student?->classroom_id,
                'school_id' => $student?->school_id,
            ]);
            return;
        }

        $grade = (int) ($student->classroom?->grade ?? 0);
        $benefitType = $student->latestMealBenefit?->type;
        $eligibleByBenefit = $student->canCreateOrder();

        if (($grade < 1 || $grade > 4) && !$eligibleByBenefit) {
            Log::info('FaceID verify order skipped by eligibility', [
                'verify_event_id' => $verifyEvent->id,
                'student_id' => $student->id,
                'grade' => $grade,
                'benefit_type' => $benefitType,
            ]);
            return;
        }

        $order = Order::query()->firstOrCreate(
            [
                'student_id' => $student->id,
                'order_date' => $createTime->toDateString(),
            ],
            [
                'order_time' => $createTime->format('H:i:s'),
                'status' => 'created',
                'transaction_status' => true,
            ],
        );

        Log::info('FaceID order ensured', [
            'verify_event_id' => $verifyEvent->id,
            'student_id' => $student->id,
            'order_id' => $order->id,
            'order_date' => $order->order_date,
        ]);
    }

    protected static function handleHeartBeat(array $data): void
    {
        $info = self::normalizeInfo($data);
        $school = self::resolveSchoolByAddress($info['Address'] ?? null);
        $time = self::parseTimestamp($info['Time'] ?? null);

        self::upsertTerminal($info, $school?->id, $time);

        Log::info('FaceID heartbeat handled', [
            'device_id' => self::nullableInt($info['DeviceID'] ?? null),
            'school_id' => $school?->id,
            'time' => $time->toDateTimeString(),
        ]);
    }

    protected static function normalizeInfo(array $data): array
    {
        $info = $data['info'] ?? $data;

        if (is_string($info)) {
            $decoded = json_decode($info, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($info) ? $info : [];
    }

    protected static function upsertTerminal(array $info, ?int $schoolId, Carbon $time): void
    {
        $deviceId = self::nullableInt($info['DeviceID'] ?? null);
        if ($deviceId === null) {
            return;
        }

        $attributes = [
            'ip' => self::nullableString($info['Ip'] ?? null),
            'mac_addr' => self::nullableString($info['MacAddr'] ?? null),
            'time' => $time,
        ];

        if ($schoolId !== null) {
            $attributes['school_id'] = $schoolId;
        }

        Terminal::query()->updateOrCreate(
            ['device_id' => $deviceId],
            $attributes,
        );
    }

    protected static function resolveSchoolByAddress(mixed $address): ?School
    {
        $bin = self::resolveBin($address);
        if ($bin === null) {
            return null;
        }

        return School::query()->where('bin', $bin)->first();
    }

    protected static function resolveBin(mixed $address): ?string
    {
        $value = trim((string) $address);
        if ($value === '') {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $value);
        return $normalized !== '' ? $normalized : $value;
    }

    protected static function parseTimestamp(mixed $value): Carbon
    {
        return $value ? Carbon::parse($value) : now();
    }

    protected static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected static function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }
}
