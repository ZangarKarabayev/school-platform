<?php

namespace App\Console\Commands;

use App\Modules\Organizations\Models\School;
use App\Services\SocialWallet\SocialWalletService;
use Illuminate\Console\Command;

class SyncSocialWalletVouchers extends Command
{
    protected $signature = 'social-wallet:sync-vouchers
        {--school_id= : Sync only one school by internal ID}
        {--school_bin= : Sync only one school by BIN}
        {--grade-from= : Optional start grade filter}
        {--grade-to= : Optional end grade filter}
        {--from= : Optional start date filter in YYYY-MM-DD}
        {--to= : Optional end date filter in YYYY-MM-DD}
        {--size=500 : Page size, maximum 500}';

    protected $description = 'Sync active meal vouchers from Social Wallet';

    public function handle(SocialWalletService $socialWalletService): int
    {
        $schools = School::query()
            ->when($this->option('school_id'), fn ($query, $schoolId) => $query->whereKey((int) $schoolId))
            ->when($this->option('school_bin'), fn ($query, $schoolBin) => $query->where('bin', (string) $schoolBin))
            ->whereNotNull('bin')
            ->where('bin', '<>', '')
            ->orderBy('id')
            ->get();

        if ($schools->isEmpty()) {
            $this->warn('Schools for voucher sync were not found.');

            return self::FAILURE;
        }

        $filters = [
            'gradeFrom' => $this->option('grade-from'),
            'gradeTo' => $this->option('grade-to'),
            'fromPeriod' => $this->option('from'),
            'toPeriod' => $this->option('to'),
            'size' => $this->option('size'),
        ];

        $hasErrors = false;

        foreach ($schools as $school) {
            try {
                $result = $socialWalletService->syncActiveVouchersForSchool($school, $filters);

                $this->info(sprintf(
                    'School #%d [%s]: total=%d, matched=%d, created=%d, updated=%d, unmatched=%d',
                    $school->id,
                    $school->bin,
                    $result['total'],
                    $result['matched'],
                    $result['created'],
                    $result['updated'],
                    $result['unmatched'],
                ));
            } catch (\Throwable $exception) {
                $hasErrors = true;

                $this->error(sprintf(
                    'School #%d [%s] sync failed: %s',
                    $school->id,
                    $school->bin,
                    $exception->getMessage(),
                ));
            }
        }

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }
}
