<?php

namespace App\Console\Commands;

use App\Jobs\SyncFaceIdStudentsJob;
use App\Modules\Organizations\Models\School;
use Illuminate\Console\Command;

class FaceIdSyncStudents extends Command
{
    protected $signature = 'faceid:sync-students
        {--school-id= : Sync only one school by id}
        {--terminal-id= : Force terminal id for selected organization(s)}
        {--delay-ms=500 : Delay between student sends in milliseconds}';

    protected $description = 'Queue student sync to FaceID terminals.';

    public function handle(): int
    {
        $delayMs = max(0, (int) $this->option('delay-ms'));
        $schoolId = $this->option('school-id');
        $forcedTerminalId = $this->option('terminal-id');

        $schoolsQuery = School::query();

        if ($schoolId) {
            $schoolsQuery->whereKey($schoolId);
        }

        $schools = $schoolsQuery->get(['id']);

        if ($schools->isEmpty()) {
            $this->info('No organizations found for sync.');
            return self::SUCCESS;
        }

        foreach ($schools as $school) {
            SyncFaceIdStudentsJob::dispatch($school->id, $forcedTerminalId ? (string) $forcedTerminalId : null, $delayMs);

            $this->info("Queued FaceID sync for school {$school->id}.");
        }

        $this->line('Run php artisan queue:work to execute queued sync jobs.');

        return self::SUCCESS;
    }
}
