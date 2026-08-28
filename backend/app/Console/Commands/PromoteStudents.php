<?php

namespace App\Console\Commands;

use App\Services\Students\StudentPromotionService;
use Illuminate\Console\Command;
use Throwable;

class PromoteStudents extends Command
{
    protected $signature = 'students:promote
        {--school= : School ID}
        {--grade= : Source grade from 1 to 11}
        {--all-grades : Promote grades 1 through 11 in one atomic operation}
        {--from= : Source school year, for example 2025-2026}
        {--to= : Target school year, for example 2026-2027}
        {--include-null-year : Include legacy students whose school year is NULL}
        {--dry-run : Preview the promotion without changing data}
        {--force : Run without interactive confirmation}';

    protected $description = 'Promote one grade or all school grades to the next school year.';

    public function handle(StudentPromotionService $promotionService): int
    {
        $schoolId = (int) $this->option('school');
        $grade = (int) $this->option('grade');
        $allGrades = (bool) $this->option('all-grades');
        $fromSchoolYear = trim((string) $this->option('from'));
        $toSchoolYear = trim((string) $this->option('to'));
        $includeNullSchoolYear = (bool) $this->option('include-null-year');

        if ($schoolId < 1 || $fromSchoolYear === '' || $toSchoolYear === '') {
            $this->error('Options --school, --from and --to are required.');

            return self::INVALID;
        }

        if ($allGrades && $this->option('grade') !== null) {
            $this->error('Options --grade and --all-grades cannot be used together.');

            return self::INVALID;
        }

        if (! $allGrades && ($grade < 1 || $grade > 11)) {
            $this->error('Option --grade must be between 1 and 11, or use --all-grades.');

            return self::INVALID;
        }

        try {
            $preview = $allGrades
                ? $promotionService->previewAll(
                    $schoolId,
                    $fromSchoolYear,
                    $toSchoolYear,
                    $includeNullSchoolYear,
                )
                : $promotionService->preview(
                    $schoolId,
                    $grade,
                    $fromSchoolYear,
                    $toSchoolYear,
                    $includeNullSchoolYear,
                );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s | %s | %s -> %s',
            $preview['school']->display_name,
            $allGrades ? 'all grades' : "grade {$grade}",
            $fromSchoolYear,
            $toSchoolYear,
        ));
        $this->table(
            $allGrades
                ? ['Grade', 'Current class', 'Target', 'Students']
                : ['Current class', 'Target', 'Students'],
            array_map(
                fn (array $group): array => $allGrades
                    ? [$group['grade'], $group['from'], $group['to'], $group['count']]
                    : [$group['from'], $group['to'], $group['count']],
                $preview['groups'],
            ),
        );
        $this->line("Total students: {$preview['total_count']}");
        if ($includeNullSchoolYear) {
            $this->line("NULL school year -> {$fromSchoolYear}: {$preview['normalized_year_count']}");
        }

        if ($preview['errors'] !== []) {
            foreach ($preview['errors'] as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ((bool) $this->option('dry-run')) {
            $this->info('Dry run completed. No data was changed.');

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force') && ! $this->confirm('Continue with this promotion?')) {
            $this->warn('Promotion cancelled.');

            return self::SUCCESS;
        }

        try {
            $batches = $allGrades
                ? $promotionService->promoteAll(
                    $schoolId,
                    $fromSchoolYear,
                    $toSchoolYear,
                    null,
                    $includeNullSchoolYear,
                )
                : collect([$promotionService->promote(
                    $schoolId,
                    $grade,
                    $fromSchoolYear,
                    $toSchoolYear,
                    null,
                    $includeNullSchoolYear,
                )]);
        } catch (Throwable $exception) {
            $this->error('Promotion failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Promotion batches completed: '.$batches->pluck('id')->implode(', '));
        $this->line('Promoted: '.$batches->sum('promoted_count'));
        $this->line('Graduated: '.$batches->sum('graduated_count'));
        $this->line('Normalized school years: '.$batches->sum('normalized_year_count'));

        return self::SUCCESS;
    }
}
