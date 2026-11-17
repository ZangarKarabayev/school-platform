<?php

namespace App\Console\Commands;

use App\Jobs\SendSocialWalletTransactionJob;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class ResendFailedSocialWalletTransactions extends Command
{
    protected $signature = 'social-wallet:resend-failed-transactions
        {--order_id= : Resend only one order by ID}
        {--school_id= : Resend only orders from one school}
        {--from_date= : Resend orders on or after this order date (YYYY-MM-DD)}
        {--network-only : Resend only cURL DNS, connection, timeout and interrupted transfer errors}
        {--limit= : Maximum number of failed orders to resend}
        {--sync : Send transactions immediately instead of dispatching queue jobs}';

    protected $description = 'Resend failed order transactions to Social Wallet';

    public function handle(): int
    {
        $fromDate = $this->option('from_date');
        $validator = Validator::make(['from_date' => $fromDate], [
            'from_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            $this->error('The --from_date option must be a valid date in YYYY-MM-DD format.');

            return self::FAILURE;
        }

        $query = Order::query()
            ->where('transaction_status', false)
            ->whereNotNull('transaction_error')
            ->when($this->option('network-only'), function ($query): void {
                $query->where(function ($errors): void {
                    foreach ([5, 6, 7, 18, 28, 52, 55, 56] as $code) {
                        $errors->orWhere('transaction_error', 'like', 'cURL error '.$code.':%');
                    }
                });
            })
            ->when($fromDate, fn ($query, $date) => $query->whereDate('order_date', '>=', $date))
            ->when($this->option('order_id'), fn ($query, $orderId) => $query->whereKey((int) $orderId))
            ->when($this->option('school_id'), function ($query, $schoolId): void {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('school_id', (int) $schoolId));
            })
            ->orderBy('id');

        $limit = $this->parseLimit();

        if ($limit !== null) {
            $query->limit($limit);
        }

        $sync = (bool) $this->option('sync');
        $count = 0;

        $query->pluck('id')->each(function (int $orderId) use ($sync, &$count): void {
            if ($sync) {
                SendSocialWalletTransactionJob::dispatchSync($orderId);
            } else {
                SendSocialWalletTransactionJob::dispatch($orderId);
            }

            $count++;
        });

        if ($count === 0) {
            $this->info('Failed Social Wallet transactions were not found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d failed Social Wallet transaction(s) %s.',
            $count,
            $sync ? 'resent' : 'queued for resend',
        ));

        return self::SUCCESS;
    }

    private function parseLimit(): ?int
    {
        $limit = $this->option('limit');

        if ($limit === null || $limit === '') {
            return null;
        }

        return max(1, (int) $limit);
    }
}
