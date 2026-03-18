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
            color: inherit;
            text-decoration: none;
        }

        .classes-list-row:first-child {
            border-top: none;
        }

        .classes-list-row.clickable {
            cursor: pointer;
            transition: background-color 0.18s ease;
        }

        .classes-list-row.clickable:hover,
        .classes-list-row.clickable:focus-visible {
            background: #f8fbff;
        }

        .classes-list-main {
            display: grid;
            gap: 4px;
        }

        .classes-list-title {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.1;
            color: #1d3151;
        }

        .classes-list-meta {
            color: #71829a;
            font-size: 14px;
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

        @media (max-width: 900px) {
            .classes-filters {
                grid-template-columns: 1fr;
            }

            .classes-list-row {
                align-items: flex-start;
                flex-direction: column;
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
                    @if ($canOpenStudents)
                        <a class="classes-list-row clickable" href="{{ route('classes.show', $classroom) }}">
                            <div class="classes-list-main">
                                <div class="classes-list-title">{{ $classroom->full_name }}</div>
                                <div class="classes-list-meta">{{ $classroom->grade }} {{ __('ui.menu.classes') }}</div>
                            </div>
                            <div class="classes-list-count">{{ __('ui.orders.students_count', ['count' => $classroom->students_count]) }}</div>
                        </a>
                    @else
                        <div class="classes-list-row">
                            <div class="classes-list-main">
                                <div class="classes-list-title">{{ $classroom->full_name }}</div>
                                <div class="classes-list-meta">{{ $classroom->grade }} {{ __('ui.menu.classes') }}</div>
                            </div>
                            <div class="classes-list-count">{{ __('ui.orders.students_count', ['count' => $classroom->students_count]) }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </section>
    </div>
@endsection
