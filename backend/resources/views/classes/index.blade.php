@extends('layouts.app')

@php
    /** @var \Illuminate\Contracts\Auth\Authenticatable|\App\Models\User|null $authUser */
    $authUser = auth()->user();
    $user = $user ?? $authUser?->loadMissing('roles', 'scopes');
@endphp

@section('content')
    <style>
        .classes-page {
            padding: 24px 0;
            display: grid;
            gap: 18px;
        }

        .classes-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
        }

        .classes-filters {
            padding: 24px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .classes-field {
            display: grid;
            gap: 6px;
        }

        .classes-field label {
            font-size: 13px;
            font-weight: 700;
            color: #4e607d;
        }

        .classes-field input,
        .classes-field select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d1d8e5;
            border-radius: 12px;
            background: #fff;
            color: #16253d;
        }

        .classes-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .classes-list {
            overflow: hidden;
        }

        .classes-list-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 24px;
            border-top: 1px solid #e4e9f1;
        }

        .classes-list-row:first-child {
            border-top: none;
        }

        .classes-list-main {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .classes-list-link {
            color: inherit;
            text-decoration: none;
            display: grid;
            gap: 4px;
            padding: 4px 0;
        }

        .classes-list-link:hover .classes-list-title,
        .classes-list-link:focus-visible .classes-list-title {
            color: #266ccc;
        }

        .classes-list-title {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.1;
            color: #1d3151;
            transition: color 0.18s ease;
        }

        .classes-list-meta {
            color: #71829a;
            font-size: 14px;
        }

        .classes-list-side {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .classes-list-count {
            flex-shrink: 0;
            padding: 8px 12px;
            border-radius: 999px;
            background: #eef3fb;
            color: #234067;
            font-size: 13px;
            font-weight: 700;
        }

        .classes-qr-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 10px;
            background: #edf4ff;
            color: #1f5cb8;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .classes-qr-link:hover,
        .classes-qr-link:focus-visible {
            background: #dceaff;
        }

        @media (max-width: 900px) {
            .classes-filters {
                grid-template-columns: 1fr;
            }

            .classes-list-row {
                align-items: flex-start;
                flex-direction: column;
            }

            .classes-list-side {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>

    <div class="classes-page">
    <section>
        <div class="classes-card" style="padding:24px;">
            <div class="muted" style="margin-bottom:8px;">{{ __('ui.common.home') }}</div>
            <h1 style="margin:0;font-size:28px;line-height:1.2;">{{ __('ui.menu.classes') }}</h1>
        </div>
    </section>

    <section>
        <form class="classes-card classes-filters" method="get" action="{{ route('classes.index') }}">
            <div class="classes-field">
                <label for="search">{{ __('ui.classes.search') }}</label>
                <input
                    id="search"
                    type="text"
                    name="search"
                    value="{{ $filters['search'] }}"
                    placeholder="{{ __('ui.classes.search_placeholder') }}"
                >
            </div>

            <div class="classes-field">
                <label for="grade">{{ __('ui.classes.grade') }}</label>
                <select id="grade" name="grade">
                    <option value="">{{ __('ui.common.all') }}</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade }}" @selected((string) $filters['grade'] === (string) $grade)>{{ $grade }}</option>
                    @endforeach
                </select>
            </div>

            <div class="classes-field">
                <label for="filled">{{ __('ui.classes.filled') }}</label>
                <select id="filled" name="filled">
                    <option value="">{{ __('ui.common.all') }}</option>
                    <option value="with" @selected($filters['filled'] === 'with')>{{ __('ui.classes.with_students') }}</option>
                    <option value="without" @selected($filters['filled'] === 'without')>{{ __('ui.classes.without_students') }}</option>
                </select>
            </div>

            <div class="classes-actions">
                <button class="btn" type="submit">{{ __('ui.common.filter') }}</button>
                <a class="btn secondary" href="{{ route('classes.index') }}">{{ __('ui.common.reset') }}</a>
            </div>
        </form>
    </section>

    <section>
        @if ($classes->isEmpty())
            <div class="classes-card" style="padding:24px;">
                <div class="muted">{{ __('ui.orders.no_classes') }}</div>
            </div>
        @else
            <div class="classes-card classes-list">
                @foreach ($classes as $classroom)
                    <div class="classes-list-row">
                        <div class="classes-list-main">
                            @if ($canOpenStudents)
                                <a class="classes-list-link" href="{{ route('classes.show', $classroom) }}">
                                    <div class="classes-list-title">{{ $classroom->full_name }}</div>
                                    <div class="classes-list-meta">{{ $classroom->grade }} {{ __('ui.menu.classes') }}</div>
                                </a>
                            @else
                                <div class="classes-list-title">{{ $classroom->full_name }}</div>
                                <div class="classes-list-meta">{{ $classroom->grade }} {{ __('ui.menu.classes') }}</div>
                            @endif
                        </div>
                        <div class="classes-list-side">
                            <div class="classes-list-count">{{ __('ui.orders.students_count', ['count' => $classroom->students_count]) }}</div>
                            @if ($classroom->students_count > 0)
                                <a class="classes-qr-link" href="{{ route('classes.qr.download', $classroom) }}">Скачать QR</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
    </div>
@endsection