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
            display: grid;
            gap: 18px;
        }

        .reports-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
        }

        .reports-form {
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

        .reports-list {
            overflow: hidden;
        }

        .reports-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 24px;
            border-top: 1px solid #e4e9f1;
        }

        .reports-row:first-child {
            border-top: none;
        }

        .reports-main {
            display: grid;
            gap: 4px;
        }

        .reports-title {
            font-size: 18px;
            font-weight: 700;
            color: #1d3151;
        }

        .reports-meta {
            color: #71829a;
            font-size: 14px;
        }

        .reports-side {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .reports-status {
            padding: 8px 12px;
            border-radius: 999px;
            background: #eef3fb;
            color: #234067;
            font-size: 13px;
            font-weight: 700;
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

        .reports-notice {
            padding: 12px 14px;
            border-radius: 12px;
            background: #eaf6ea;
            color: #22653a;
            font-weight: 700;
        }

        @media (max-width: 900px) {
            .reports-form {
                grid-template-columns: 1fr;
            }

            .reports-row {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <div class="reports-page">
        <section>
            <div class="reports-card" style="padding:24px;">
                <div class="muted" style="margin-bottom:8px;">{{ __('ui.common.home') }}</div>
                <h1 style="margin:0;font-size:28px;line-height:1.2;">{{ __('ui.menu.reports') }}</h1>
            </div>
        </section>

        @if (session('report_status'))
            <div class="reports-notice">{{ session('report_status') }}</div>
        @endif

        <section>
            <form class="reports-card reports-form" method="post" action="{{ route('reports.store') }}">
                @csrf

                <div class="reports-field">
                    <label for="report_type">Вид отчета</label>
                    <select id="report_type" name="report_type" required>
                        <option value="">Выберите отчет</option>
                        @foreach ($reportTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('report_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="reports-field">
                    <label for="date_from">Дата от</label>
                    <input id="date_from" type="date" name="date_from" value="{{ old('date_from') }}" required>
                </div>

                <div class="reports-field">
                    <label for="date_to">Дата до</label>
                    <input id="date_to" type="date" name="date_to" value="{{ old('date_to') }}" required>
                </div>

                <div>
                    <button class="btn" type="submit">Сформировать</button>
                </div>
            </form>
        </section>

        <section>
            <div class="reports-card reports-list">
                @forelse ($reports as $report)
                    <div class="reports-row">
                        <div class="reports-main">
                            <div class="reports-title">{{ $report->type_label }}</div>
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
                                <a class="btn secondary" href="{{ route('reports.download', $report) }}">Скачать</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="reports-row">
                        <div class="reports-main">
                            <div class="reports-meta">Отчеты пока не формировались.</div>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
