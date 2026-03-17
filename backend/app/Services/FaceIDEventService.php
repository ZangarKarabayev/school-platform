<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Student;
use App\Models\Terminal;
use App\Models\VerifyEvent;
use App\Modules\Organizations\Models\School;
use Carbon\Carbon;

class FaceIDEventService
{
    public static function handle(array $data): void
    {
        $operator = $data['operator'] ?? null;

        if (!$operator) {
            throw new \InvalidArgumentException('Missing operator field.');
        }

        match ($operator) {
            'VerifyPush' => self::handleVerify($data),
            'HeartBeat' => self::handleHeartBeat($data),
            default => null,
        };
    }

    protected static function handleVerify(array $data): void
    {
        $info = self::normalizeInfo($data);

        $uniqueQr = trim((string) ($info['Notes'] ?? ''));
        if ($uniqueQr === '') {
            return;
        }

        $createTime = self::parseTimestamp($info['CreateTime'] ?? null);
        if ($createTime->isBefore(Carbon::parse('2026-01-01'))) {
            return;
        }

        $school = self::resolveSchoolByAddress($info['Address'] ?? null);

        VerifyEvent::query()->updateOrCreate(
            [
                'device_id' => self::nullableInt($info['DeviceID'] ?? null),
                'unique_qr' => $uniqueQr,
                'create_time' => $createTime,
            ],
            [
                'person_id' => self::nullableInt($info['PersonID'] ?? null),
                'name' => self::nullableString($info['Name'] ?? null),
                'verify_status' => self::nullableInt($info['VerifyStatus'] ?? null),
                'bin' => self::resolveBin($info['Address'] ?? null) ?? 'нет данных',
            ],
        );

        self::upsertTerminal($info, $school?->id, $createTime);

        $student = Student::query()
            ->with(['classroom', 'latestMealBenefit'])
            ->where('student_number', $uniqueQr)
            ->orWhere('iin', $uniqueQr)
            ->first();

        if (!$student || !$student->classroom_id || !$student->school_id) {
            return;
        }

        $grade = (int) ($student->classroom?->grade ?? 0);
        $benefitType = $student->latestMealBenefit?->type;
        $eligibleByBenefit = in_array($benefitType, ['susn', 'voucher'], true);

        if (($grade < 1 || $grade > 4) && !$eligibleByBenefit) {
            return;
        }

        Order::query()->firstOrCreate(
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
    }

    protected static function handleHeartBeat(array $data): void
    {
        $info = self::normalizeInfo($data);
        $school = self::resolveSchoolByAddress($info['Address'] ?? null);
        $time = self::parseTimestamp($info['Time'] ?? null);

        self::upsertTerminal($info, $school?->id, $time);
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

        Terminal::query()->updateOrCreate(
            ['device_id' => $deviceId],
            [
                'school_id' => $schoolId,
                'ip' => self::nullableString($info['Ip'] ?? null),
                'mac_addr' => self::nullableString($info['MacAddr'] ?? null),
                'time' => $time,
            ],
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

