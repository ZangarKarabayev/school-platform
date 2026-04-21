<?php

namespace App\Console\Commands;

use App\Jobs\SendSocialWalletTransactionJob;
use App\Models\Order;
use Illuminate\Console\Command;

class ResendFailedSocialWalletTransactions extends Command
{
    protected $signature = 'social-wallet:resend-failed-transactions
        {--order_id= : Resend only one order by ID}
        {--school_id= : Resend only orders from one school}
        {--limit= : Maximum number of failed orders to resend}
        {--sync : Send transactions immediately instead of dispatching queue jobs}';

    protected $description = 'Resend failed order transactions to Social Wallet';

    public function handle(): int
    {
        $query = Order::query()
            ->where('transaction_status', false)
            ->whereNotNull('transaction_error')
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
