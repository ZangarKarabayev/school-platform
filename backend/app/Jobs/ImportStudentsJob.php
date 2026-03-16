<?php

namespace App\Jobs;

use App\Models\StudentImport;
use App\Services\Students\StudentImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportStudentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public StudentImport $studentImport)
    {
    }

    public function handle(StudentImportService $studentImportService): void
    {
        $studentImport = $this->studentImport->fresh();

        if ($studentImport === null) {
            return;
        }

        $studentImportService->process($studentImport);
    }
}