<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportJob;
use App\Models\GeneratedReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()?->loadMissing('roles', 'scopes');

        return view('reports.index', [
            'user' => $user,
            'reportTypes' => GeneratedReport::typeOptions(),
            'reports' => GeneratedReport::query()
                ->where('user_id', $user?->id)
                ->latest()
                ->limit(10)
                ->get(),
            'title' => __('ui.menu.reports'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'report_type' => ['required', Rule::in(array_keys(GeneratedReport::typeOptions()))],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $user = $request->user()?->loadMissing('scopes');

        $report = GeneratedReport::query()->create([
            'user_id' => $user?->id,
            'school_id' => $this->resolveSchoolIdForUser($request),
            'report_type' => $data['report_type'],
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'status' => GeneratedReport::STATUS_PENDING,
        ]);

        GenerateReportJob::dispatch($report);

        return redirect()
            ->route('reports.index')
            ->with('report_status', 'Отчет поставлен в очередь на формирование.');
    }

    public function download(Request $request, GeneratedReport $report): BinaryFileResponse
    {
        abort_unless((int) $report->user_id === (int) $request->user()?->id, 403);
        abort_unless($report->status === GeneratedReport::STATUS_COMPLETED && $report->file_path, 404);

        $disk = $report->file_disk ?: 'local';

        if (Storage::disk($disk)->exists($report->file_path)) {
            return Storage::disk($disk)->download($report->file_path, basename($report->file_path));
        }

        $legacyPath = storage_path('app/' . ltrim($report->file_path, '/\\'));

        if (is_file($legacyPath)) {
            return response()->download($legacyPath, basename($legacyPath));
        }

        return Storage::disk($report->file_disk ?: 'local')->download($report->file_path, basename($report->file_path));
    }

    private function resolveSchoolIdForUser(Request $request): ?int
    {
        $user = $request->user()?->loadMissing('scopes');

        if ($user?->school_id) {
            return $user->school_id;
        }

        return $user?->scopes
            ->first(fn ($scope) => $scope->scope_type === 'school' && $scope->scope_id !== null)
            ?->scope_id;
    }
}
