@extends('layouts.app')

@php
    /** @var \Illuminate\Contracts\Auth\Authenticatable|\App\Models\User|null $authUser */
    $authUser = auth()->user();
    $user = $user ?? $authUser?->loadMissing('roles', 'scopes');
@endphp

@section('content')
    <style>
        .class-show-page {
            padding: 24px 0;
            display: grid;
            gap: 18px;
        }

        .class-show-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
        }

        .class-show-list {
            overflow: hidden;
        }

        .class-show-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 24px;
            border-top: 1px solid #e4e9f1;
        }

        .class-show-row:first-child {
            border-top: none;
        }

        .class-show-main {
            display: grid;
            gap: 4px;
        }

        .class-show-name {
            font-size: 18px;
            font-weight: 700;
            color: #1d3151;
        }

        .class-show-meta {
            color: #71829a;
            font-size: 14px;
        }

        .class-show-badge {
            flex-shrink: 0;
            padding: 8px 12px;
            border-radius: 999px;
            background: #eef3fb;
            color: #234067;
            font-size: 13px;
            font-weight: 700;
        }

        @media (max-width: 900px) {
            .class-show-row {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <div class="class-show-page">
        <section>
            <div class="class-show-card" style="padding:24px;">
                <div class="muted" style="margin-bottom:8px;">
                    <a href="{{ route('classes.index') }}" style="color:inherit;text-decoration:none;">{{ __('ui.menu.classes') }}</a>
                </div>
                <h1 style="margin:0;font-size:28px;line-height:1.2;">{{ $classroom->full_name }}</h1>
                <div class="muted" style="margin-top:10px;">
                    {{ __('ui.orders.students_count', ['count' => $students->count()]) }}
                </div>
            </div>
        </section>

        <section>
            @if ($students->isEmpty())
                <div class="class-show-card" style="padding:24px;">
                    <div class="muted">{{ __('ui.orders.no_students') }}</div>
                </div>
            @else
                <div class="class-show-card class-show-list">
                    @foreach ($students as $student)
                        <div class="class-show-row">
                            <div class="class-show-main">
                                <div class="class-show-name">{{ $student->full_name ?: __('ui.dashboard.user_fallback') }}</div>
                                <div class="class-show-meta">
                                    {{ $student->iin ?: __('ui.common.not_specified') }}
                                </div>
                            </div>
                            <div class="class-show-badge">{{ $classroom->full_name }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
