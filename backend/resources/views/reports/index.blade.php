@extends('layouts.app')

@php
    /** @var \Illuminate\Contracts\Auth\Authenticatable|\App\Models\User|null $authUser */
    $authUser = auth()->user();
    $user = $user ?? $authUser?->loadMissing('roles', 'scopes');
@endphp

@section('content')
    <style>
        .reports-page {
            padding: 24px 0;
        }

        .reports-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
        }

        .reports-header {
            padding: 24px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid #e4e9f1;
        }

        .reports-title {
            margin: 8px 0 0;
            font-size: 30px;
            line-height: 1.1;
            color: #16345f;
        }

        .reports-header-actions {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .reports-count {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f3f7fd;
            color: #234067;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .reports-notice {
            margin: 0 24px 24px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #eaf6ea;
            color: #22653a;
            font-weight: 700;
        }

        .reports-filters {
            padding: 24px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .reports-field {
            display: grid;
            gap: 6px;
        }

        .reports-field label {
            font-size: 13px;
            font-weight: 700;
            color: #4e607d;
        }

        .reports-field input,
        .reports-field select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d1d8e5;
            border-radius: 12px;
            background: #fff;
            color: #16253d;
        }

        .reports-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .reports-list {
            border-top: 1px solid #e4e9f1;
        }

        .reports-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 24px;
            border-bottom: 1px solid #e8edf5;
        }

        .reports-row:last-child {
            border-bottom: none;
        }

        .reports-main {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .reports-row-title {
            font-size: 18px;
            font-weight: 700;
            color: #1d3151;
            word-break: break-word;
        }

        .reports-meta {
            color: #71829a;
            font-size: 14px;
            word-break: break-word;
        }

        .reports-side {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            flex-shrink: 0;
        }

        .reports-status {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eef5ff;
            color: #1f5cb8;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1;
        }

        .reports-status.status-pending {
            background: #fff4dd;
            color: #9a6400;
        }

        .reports-status.status-completed {
            background: #eaf6ea;
            color: #22653a;
        }

        .reports-status.status-failed {
            background: #fdecee;
            color: #c43b52;
        }

        .reports-empty {
            padding: 28px 24px 32px;
            color: #71829a;
        }

        @media (max-width: 980px) {
            .reports-filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .reports-header {
                flex-direction: column;
            }

            .reports-filters {
                grid-template-columns: 1fr;
            }

            .reports-count {
                min-width: 0;
                width: 100%;
                border-radius: 12px;
            }

            .reports-row {
                align-items: flex-start;
                flex-direction: column;
            }

            .reports-side {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>

    <section class="reports-page">
        <div class="reports-card">
            <div class="reports-header">
                <div>
                    <div class="muted">{{ __('ui.common.home') }}</div>
                    <h1 class="reports-title">{{ __('ui.menu.reports') }}</h1>
                </div>
                <div class="reports-header-actions">
                    <div class="reports-count">{{ $reports->count() }}</div>
                </div>
            </div>

            @if (session('report_status'))
                <div class="reports-notice">{{ session('report_status') }}</div>
            @endif

            <form class="reports-filters" method="post" action="{{ route('reports.store') }}">
                @csrf

                <div class="reports-field">
                    <label for="report_type">{{ __('ui.reports_page.report_type') }}</label>
                    <select id="report_type" name="report_type" required>
                        <option value="">{{ __('ui.reports_page.select_report') }}</option>
                        @foreach ($reportTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('report_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="reports-field">
                    <label for="date_from">{{ __('ui.reports_page.date_from') }}</label>
                    <input id="date_from" type="date" name="date_from" value="{{ old('date_from') }}" required>
                </div>

                <div class="reports-field">
                    <label for="date_to">{{ __('ui.reports_page.date_to') }}</label>
                    <input id="date_to" type="date" name="date_to" value="{{ old('date_to') }}" required>
                </div>

                <div class="reports-actions">
                    <button class="btn" type="submit">{{ __('ui.reports_page.generate') }}</button>
                </div>
            </form>

            <div class="reports-list">
                @forelse ($reports as $report)
                    <div class="reports-row">
                        <div class="reports-main">
                            <div class="reports-row-title">{{ $report->type_label }}</div>
                            <div class="reports-meta">
                                {{ optional($report->date_from)->format('Y-m-d') }} - {{ optional($report->date_to)->format('Y-m-d') }}
                            </div>
                            @if ($report->error_message)
                                <div class="reports-meta">{{ $report->error_message }}</div>
                            @endif
                        </div>

                        <div class="reports-side">
                            <span class="reports-status status-{{ $report->status }}">{{ $report->status_label }}</span>
                            @if ($report->status === \App\Models\GeneratedReport::STATUS_COMPLETED && $report->file_path)
                                <a class="btn secondary" href="{{ route('reports.download', $report) }}">{{ __('ui.reports_page.download') }}</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="reports-empty">{{ __('ui.reports_page.empty') }}</div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
