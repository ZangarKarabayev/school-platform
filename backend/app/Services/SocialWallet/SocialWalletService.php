<?php

namespace App\Services\SocialWallet;

use App\Models\MealBenefit;
use App\Models\Order;
use App\Modules\Organizations\Models\School;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SocialWalletService
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    /**
     * @return array{school_id:int,total:int,matched:int,created:int,updated:int,unmatched:int}
     */
    public function syncActiveVouchersForSchool(School $school, array $filters = []): array
    {
        $schoolBin = trim((string) $school->bin);

        if ($schoolBin === '') {
            throw new RuntimeException("School {$school->id} does not have a BIN.");
        }

        $voucherIins = $this->fetchAllActiveVoucherIins($schoolBin, $filters);
        $uniqueIins = collect($voucherIins)
            ->map(fn (mixed $iin): string => trim((string) $iin))
            ->filter()
            ->unique()
            ->values();

        $students = $school->students()
            ->with('latestMealBenefit')
            ->whereIn('iin', $uniqueIins->all())
            ->get()
            ->keyBy(fn ($student) => (string) $student->iin);

        $created = 0;
        $updated = 0;
        $timestamp = now();

        foreach ($uniqueIins as $iin) {
            $student = $students->get($iin);

            if (!$student) {
                continue;
            }

            $latestBenefit = $student->latestMealBenefit;

            if ($latestBenefit?->type === 'voucher') {
                $latestBenefit->forceFill([
                    'voucher_update_datetime' => $timestamp,
                ])->save();

                $updated++;

                continue;
            }

            MealBenefit::query()->create([
                'student_id' => $student->id,
                'type' => 'voucher',
                'voucher_update_datetime' => $timestamp,
            ]);

            $created++;
        }

        $result = [
            'school_id' => $school->id,
            'total' => $uniqueIins->count(),
            'matched' => $students->count(),
            'created' => $created,
            'updated' => $updated,
            'unmatched' => $uniqueIins->count() - $students->count(),
        ];

        Log::info('Social wallet vouchers synced', $result + ['school_bin' => $schoolBin]);

        return $result;
    }

    public function sendMealTransaction(Order $order): bool
    {
        $order->loadMissing('student.school');

        $student = $order->student;
        $school = $student?->school;
        $iin = trim((string) $student?->iin);
        $schoolBin = trim((string) $school?->bin);

        if ($student === null || $school === null || $iin === '' || $schoolBin === '') {
            $this->markOrderTransaction($order, false, 'Missing student IIN or school BIN for social wallet transaction.');

            return false;
        }

        try {
            $response = $this->client()
                ->post('/api/v1/sdu/meal/transaction', [
                    'iin' => $iin,
                    'date' => $this->formatOrderTimestampForSocialWallet($order),
                    'school_bin' => $schoolBin,
                ]);

            $payload = $response->json();

            if ($response->successful() && ($payload['success'] ?? false) === true) {
                $this->markOrderTransaction($order, true, null);

                return true;
            }

            $error = $this->extractTransactionError($response->status(), $payload);
            $this->markOrderTransaction($order, false, $error);

            Log::warning('Social wallet transaction rejected', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'payload' => $payload,
            ]);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->markOrderTransaction($order, false, $exception->getMessage());

            Log::error('Social wallet transaction request failed', [
                'order_id' => $order->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function fetchAllActiveVoucherIins(string $schoolBin, array $filters = []): array
    {
        $page = max(0, (int) ($filters['page'] ?? 0));
        $size = min(500, max(1, (int) ($filters['size'] ?? 500)));
        $allIins = [];

        do {
            $query = array_filter([
                'schoolBin' => $schoolBin,
                'page' => $page,
                'size' => $size,
                'gradeFrom' => $filters['gradeFrom'] ?? null,
                'gradeTo' => $filters['gradeTo'] ?? null,
                'fromPeriod' => $filters['fromPeriod'] ?? null,
                'toPeriod' => $filters['toPeriod'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== '');

            $response = $this->client()
                ->get('/api/v1/sdu/meal/voucher/list/active', $query);

            if (!$response->successful()) {
                throw new RuntimeException($this->extractVoucherError($response->status(), $response->json()));
            }

            $payload = $response->json();
            $content = collect($payload['content'] ?? [])
                ->filter(fn (mixed $value): bool => is_string($value) || is_numeric($value))
                ->map(fn (mixed $value): string => trim((string) $value))
                ->filter()
                ->all();

            $allIins = [...$allIins, ...$content];

            $last = (bool) ($payload['last'] ?? true);
            $page++;
        } while (!$last);

        return $allIins;
    }

    private function client()
    {
        $baseUrl = rtrim((string) config('services.social_wallet.base_url'), '/');
        $username = (string) config('services.social_wallet.username');
        $password = (string) config('services.social_wallet.password');

        if ($baseUrl === '' || $username === '' || $password === '') {
            throw new RuntimeException('Social wallet credentials are not configured.');
        }

        return $this->http
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.social_wallet.timeout', 15))
            ->withBasicAuth($username, $password);
    }

    private function formatOrderTimestampForSocialWallet(Order $order): string
    {
        $date = $order->order_date?->toDateString() ?? now()->toDateString();
        $time = trim((string) $order->order_time) !== '' ? (string) $order->order_time : '00:00:00';

        return Carbon::parse($date.' '.$time, config('app.timezone'))
            ->utc()
            ->format('Y-m-d H:i:s');
    }

    private function markOrderTransaction(Order $order, bool $success, ?string $error): void
    {
        $order->forceFill([
            'status' => $success ? Order::STATUS_COMPLETED : Order::STATUS_FAILED,
            'transaction_status' => $success,
            'transaction_error' => $error !== null ? mb_substr(trim($error), 0, 65535) : null,
        ])->save();
    }

    private function extractTransactionError(int $status, mixed $payload): string
    {
        $data = is_array($payload) ? $payload : [];
        $message = trim((string) ($data['error_msg'] ?? $data['error'] ?? ''));
        $code = $data['error_code'] ?? $data['code'] ?? $status;

        if ($message !== '') {
            return sprintf('[%s] %s', $code, $message);
        }

        return sprintf('Social wallet transaction failed with HTTP %d.', $status);
    }

    private function extractVoucherError(int $status, mixed $payload): string
    {
        $data = is_array($payload) ? $payload : [];
        $message = trim((string) ($data['error'] ?? ''));
        $code = trim((string) ($data['code'] ?? $status));

        if ($message !== '') {
            return sprintf('[%s] %s', $code, $message);
        }

        return sprintf('Social wallet voucher sync failed with HTTP %d.', $status);
    }
}
