@extends('layouts.app')

@section('content')
    @php
        $studentRoleCodes = $user?->roles?->pluck('code')->all() ?? [];
        $showSchoolFilter =
            !in_array('teacher', $studentRoleCodes, true) && !in_array('director', $studentRoleCodes, true);
    @endphp

    <style>
        .students-page {
            padding: 24px 0;
            display: grid;
            gap: 18px;
        }

        .students-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
        }

        .students-header {
            padding: 24px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .students-title {
            margin: 0;
            font-size: 30px;
            line-height: 1.1;
        }

        .students-subtitle {
            margin-top: 8px;
            color: #71829a;
            font-size: 14px;
        }

        .students-header-actions {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .students-count {
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


        .students-notice {
            margin: 0 24px 24px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #eaf6ea;
            color: #22653a;
            font-weight: 700;
        }

        .students-imports {
            margin: 0 24px 24px;
            padding: 18px;
            border: 1px solid #e1e8f2;
            border-radius: 18px;
            background: #f8fbff;
            display: grid;
            gap: 14px;
        }

        .students-imports-title {
            margin: 0;
            font-size: 16px;
            color: #1d3151;
        }

        .students-imports-list {
            display: grid;
            gap: 12px;
        }

        .students-import-item {
            padding: 14px;
            border-radius: 14px;
            background: #fff;
            border: 1px solid #dde6f3;
            display: grid;
            gap: 10px;
        }

        .students-import-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .students-import-name {
            font-weight: 700;
            color: #1d3151;
            word-break: break-word;
        }

        .students-import-meta {
            color: #71829a;
            font-size: 13px;
        }

        .students-import-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eef5ff;
            color: #1f5cb8;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .students-import-status.status-processing {
            background: #fff4dd;
            color: #9a6400;
        }

        .students-import-status.status-completed {
            background: #eaf6ea;
            color: #22653a;
        }

        .students-import-status.status-failed {
            background: #fdecee;
            color: #c43b52;
        }

        .students-import-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .students-import-stat {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eef3fb;
            color: #446389;
            font-size: 12px;
            font-weight: 700;
        }

        .students-import-errors {
            display: grid;
            gap: 6px;
        }

        .students-import-error {
            padding: 8px 10px;
            border-radius: 10px;
            background: #fff3f1;
            color: #b43e2a;
            font-size: 13px;
        }

        .students-filters {
            padding: 0 24px 24px;
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field label {
            font-size: 13px;
            font-weight: 700;
            color: #4e607d;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d1d8e5;
            border-radius: 12px;
            background: #fff;
            color: #16253d;
        }

        .students-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .students-table-wrap {
            overflow: auto;
            border-top: 1px solid #e4e9f1;
        }

        .students-mobile-list {
            display: none;
            padding: 16px;
            gap: 14px;
            border-top: 1px solid #e4e9f1;
        }

        .students-mobile-card {
            border: 1px solid #e1e8f2;
            border-radius: 18px;
            background: #fff;
            padding: 16px;
            display: grid;
            gap: 14px;
            cursor: pointer;
        }

        .students-mobile-card:hover {
            background: #f8fbff;
        }

        .students-mobile-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .students-mobile-identity {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .students-mobile-summary {
            min-width: 0;
        }

        .students-mobile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .students-mobile-item {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .students-mobile-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #71829a;
        }

        .students-mobile-value {
            color: #16253d;
            word-break: break-word;
        }

        .students-mobile-actions {
            display: flex;
            justify-content: flex-end;
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1180px;
        }

        .students-table th,
        .students-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #e8edf5;
            text-align: left;
            vertical-align: top;
        }

        .students-table tbody tr {
            cursor: pointer;
        }

        .students-table tbody tr:hover {
            background: #f8fbff;
        }

        .students-table th {
            background: #f7f9fc;
            color: #4e607d;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .student-photo-cell {
            width: 196px;
        }

        .student-actions-cell {
            width: 72px;
            text-align: center;
        }

        .student-delete-form {
            display: inline-flex;
        }

        .student-delete-btn {
            width: 40px;
            min-width: 40px;
            height: 40px;
            border: 0;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fdecee;
            color: #c43b52;
            cursor: pointer;
        }

        .student-delete-btn:hover {
            background: #f9d6dc;
        }

        .student-delete-btn svg {
            width: 18px;
            height: 18px;
            display: block;
        }

        .student-photo-wrap {
            display: grid;
            gap: 10px;
        }

        .student-photo {
            width: 50px;
            height: 50px;
            border-radius: 16px;
            object-fit: cover;
            background: linear-gradient(135deg, #d7e4f8 0%, #eef3fb 100%);
            border: 1px solid #d1d8e5;
            cursor: pointer;
        }

        .student-photo-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #d7e4f8 0%, #eef3fb 100%);
            border: 1px solid #d1d8e5;
            color: #446389;
            font-weight: 700;
            font-size: 24px;
            cursor: pointer;
        }

        .student-hidden-input {
            display: none;
        }

        .students-name {
            font-weight: 700;
            color: #1d3151;
        }

        .students-meta {
            color: #71829a;
            font-size: 13px;
            margin-top: 4px;
        }

        .students-status {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eef5ff;
            color: #1f5cb8;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .students-status.muted-state {
            background: #f0f2f6;
            color: #697991;
        }

        .students-empty {
            padding: 28px 24px 32px;
            color: #71829a;
        }

        .students-pagination {
            padding: 18px 24px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .students-pagination-info {
            color: #71829a;
            font-size: 14px;
        }

        .students-pagination-links {
            display: flex;
            gap: 10px;
        }

        .student-create-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(10, 21, 39, 0.72);
            z-index: 1000;
        }

        .student-create-modal[data-open="true"] {
            display: flex;
        }

        .student-create-panel {
            width: min(100%, 760px);
            max-height: calc(100vh - 40px);
            background: #fff;
            border-radius: 24px;
            overflow: auto;
            box-shadow: 0 24px 60px rgba(8, 19, 38, 0.28);
        }

        .student-create-header,
        .student-create-body {
            padding: 20px;
        }

        .student-create-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid #e8edf5;
        }

        .student-create-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .student-create-grid .field.full {
            grid-column: 1 / -1;
        }

        .student-create-error {
            margin-bottom: 14px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fff3f1;
            color: #b43e2a;
            font-size: 14px;
        }

        .camera-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(10, 21, 39, 0.72);
            z-index: 1000;
        }

        .camera-modal[data-open="true"] {
            display: flex;
        }

        .camera-panel {
            width: min(100%, 720px);
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(8, 19, 38, 0.28);
        }

        .photo-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(10, 21, 39, 0.72);
            z-index: 1000;
        }

        .photo-modal[data-open="true"] {
            display: flex;
        }

        .photo-panel {
            width: min(100%, 420px);
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(8, 19, 38, 0.28);
        }

        .photo-panel-header,
        .photo-panel-body {
            padding: 20px;
        }

        .photo-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid #e8edf5;
        }

        .photo-panel-actions {
            display: grid;
            gap: 10px;
        }

        .photo-panel-actions .btn {
            justify-content: center;
        }

        .photo-panel-preview {
            padding: 0 20px 20px;
            display: flex;
            justify-content: center;
        }

        .photo-panel-preview-image,
        .photo-panel-preview-placeholder {
            width: 140px;
            height: 140px;
            border-radius: 24px;
            border: 1px solid #d1d8e5;
            background: linear-gradient(135deg, #d7e4f8 0%, #eef3fb 100%);
        }

        .photo-panel-preview-image {
            object-fit: cover;
            display: none;
        }

        .photo-panel-preview-placeholder {
            display: grid;
            place-items: center;
            color: #446389;
            font-weight: 700;
            font-size: 44px;
        }

        .camera-panel-header,
        .camera-panel-footer {
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .camera-panel-body {
            padding: 0 20px 20px;
            display: grid;
            gap: 16px;
        }

        .camera-video,
        .camera-canvas {
            width: 100%;
            max-height: 420px;
            border-radius: 18px;
            background: #0f1a2d;
            object-fit: cover;
        }

        .camera-canvas {
            display: none;
        }

        @media (max-width: 1080px) {
            .students-filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .students-header {
                flex-direction: column;
            }

            .students-filters {
                grid-template-columns: 1fr;
            }

            .students-count {
                min-width: 0;
                width: 100%;
            }

            .student-create-grid {
                grid-template-columns: 1fr;
            }

            .students-table-wrap {
                display: none;
            }

            .students-mobile-list {
                display: grid;
            }

            .students-pagination {
                padding-top: 0;
            }
        }

        @media (max-width: 520px) {
            .students-mobile-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <section class="students-page">
        <div class="students-card">
            <div class="students-header">
                <div>
                    <div class="muted">{{ __('ui.common.home') }}</div>
                    <h1 class="students-title">{{ __('ui.menu.students') }}</h1>
                    <div class="students-subtitle">{{ __('admin.labels.students') }}</div>
                </div>
                <div class="students-header-actions">
                    <button class="btn secondary" type="button"
                        id="student-import-open">{{ __('ui.students.import_button') }}</button>
                    <button class="btn" type="button" id="student-create-open">{{ __('ui.common.add') }}</button>
                    <div class="students-count">
                        {{ $students->total() }}
                    </div>
                </div>
            </div>

            @if (session('student_status'))
                <div class="students-notice">{{ session('student_status') }}</div>
            @endif

            @if ($studentImports->isNotEmpty())
                <div class="students-imports">
                    <h2 class="students-imports-title">{{ __('ui.students.import_history') }}</h2>

                    <div class="students-imports-list">
                        @foreach ($studentImports as $studentImport)
                            @php
                                $statusClass = 'status-' . $studentImport->status;
                                $statusLabel = __('ui.students.import_statuses.' . $studentImport->status);
                            @endphp

                            <article class="students-import-item">
                                <div class="students-import-top">
                                    <div>
                                        <div class="students-import-name">{{ $studentImport->original_name }}</div>
                                        <div class="students-import-meta">
                                            {{ optional($studentImport->created_at)->format('Y-m-d H:i') }}
                                        </div>
                                    </div>

                                    <span class="students-import-status {{ $statusClass }}">
                                        {{ $statusLabel !== 'ui.students.import_statuses.' . $studentImport->status ? $statusLabel : ucfirst($studentImport->status) }}
                                    </span>
                                </div>

                                <div class="students-import-stats">
                                    <span class="students-import-stat">{{ __('ui.students.import_total', ['count' => $studentImport->total_rows]) }}</span>
                                    <span class="students-import-stat">{{ __('ui.students.import_added', ['count' => $studentImport->imported_count]) }}</span>
                                    <span class="students-import-stat">{{ __('ui.students.import_updated', ['count' => $studentImport->updated_count]) }}</span>
                                    <span class="students-import-stat">{{ __('ui.students.import_skipped', ['count' => $studentImport->skipped_count]) }}</span>
                                </div>

                                @if ($studentImport->error_message)
                                    <div class="students-import-errors">
                                        <div class="students-import-error">{{ $studentImport->error_message }}</div>
                                    </div>
                                @endif

                                @if (! empty($studentImport->error_rows))
                                    <div class="students-import-errors">
                                        @foreach ($studentImport->error_rows as $errorRow)
                                            <div class="students-import-error">
                                                {{ $errorRow['message'] ?? '-' }}
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="GET" action="{{ route('students.index') }}" class="students-filters">
                <div class="field">
                    <label for="search">{{ __('admin.labels.full_name') }} / {{ __('admin.labels.iin') }}</label>
                    <input id="search" type="text" name="search" value="{{ $filters['search'] }}"
                        placeholder="{{ __('admin.labels.full_name') }}, {{ __('admin.labels.iin') }}">
                </div>

                <div class="field">
                    <label for="classroom_id">{{ __('admin.labels.class_full_name') }}</label>
                    <select id="classroom_id" name="classroom_id">
                        <option value="">-</option>
                        @foreach ($classrooms as $classroom)
                            <option value="{{ $classroom->id }}" @selected((string) $filters['classroom_id'] === (string) $classroom->id)>
                                {{ $classroom->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($showSchoolFilter)
                    <div class="field">
                        <label for="school_id">{{ __('admin.labels.organization') }}</label>
                        <select id="school_id" name="school_id">
                            <option value="">-</option>
                            @foreach ($schools as $school)
                                <option value="{{ $school->id }}" @selected((string) $filters['school_id'] === (string) $school->id)>
                                    {{ $school->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="field">
                    <label for="status">{{ __('admin.labels.status') }}</label>
                    <select id="status" name="status">
                        <option value="">-</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>
                                {{ __('admin.meal_benefit_types.' . $status) !== 'admin.meal_benefit_types.' . $status ? __('admin.meal_benefit_types.' . $status) : str_replace('_', ' ', ucfirst($status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="photo_filter">{{ __('ui.common.photo') }}</label>
                    <select id="photo_filter" name="photo">
                        <option value="">-</option>
                        <option value="with" @selected($filters['photo'] === 'with')>{{ __('ui.students.with_photo') }}</option>
                        <option value="without" @selected($filters['photo'] === 'without')>{{ __('ui.students.without_photo') }}</option>
                    </select>
                </div>

                <div class="students-actions">
                    <button class="btn" type="submit">{{ __('ui.common.filter') }}</button>
                    <a class="btn secondary" href="{{ route('students.index') }}">{{ __('ui.common.reset') }}</a>
                </div>
            </form>

            @if ($students->isEmpty())
                <div class="students-empty">
                    {{ __('admin.labels.students') }}: 0
                </div>
            @else
                <div class="students-mobile-list">
                    @foreach ($students as $student)
                        <article class="students-mobile-card" data-student-edit-url="{{ route('students.edit', $student) }}">
                            <div class="students-mobile-top">
                                <div class="students-mobile-identity">
                                    @if ($student->photo)
                                        <img class="student-photo"
                                            src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) }}"
                                            alt="{{ $student->full_name }}" data-photo-open="{{ $student->id }}"
                                            data-camera-name="{{ $student->full_name ?: __('ui.dashboard.user_fallback') }}"
                                            data-photo-url="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) }}"
                                            data-photo-initial="{{ mb_substr($student->last_name ?: $student->first_name ?: 'S', 0, 1) }}">
                                    @else
                                        <div class="student-photo-placeholder"
                                            data-photo-open="{{ $student->id }}"
                                            data-camera-name="{{ $student->full_name ?: __('ui.dashboard.user_fallback') }}"
                                            data-photo-url=""
                                            data-photo-initial="{{ mb_substr($student->last_name ?: $student->first_name ?: 'S', 0, 1) }}">
                                            {{ mb_substr($student->last_name ?: $student->first_name ?: 'S', 0, 1) }}
                                        </div>
                                    @endif

                                    <div class="students-mobile-summary">
                                        <div class="students-name">
                                            {{ $student->full_name ?: __('ui.dashboard.user_fallback') }}
                                        </div>
                                        <div class="students-meta">{{ $student->phone ?: __('ui.common.not_set') }}</div>
                                    </div>
                                </div>

                                <form
                                    class="student-delete-form"
                                    method="POST"
                                    action="{{ route('students.destroy', $student) }}"
                                    onsubmit="return confirm(@js(__('ui.students.delete_confirm')));"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button class="student-delete-btn" type="submit" title="{{ __('ui.students.delete') }}" aria-label="{{ __('ui.students.delete') }}">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm1 7h2v8h-2v-8Zm4 0h2v8h-2v-8ZM7 10h2v8H7v-8Zm-1 11a2 2 0 0 1-2-2V8h16v11a2 2 0 0 1-2 2H6Z" fill="currentColor"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>

                            @php
                                $mealStatus = $student->latestMealBenefit?->type;
                                $mealStatusLabel = $mealStatus
                                    ? __('admin.meal_benefit_types.' . $mealStatus)
                                    : '-';
                            @endphp

                            <div class="students-mobile-grid">
                                <div class="students-mobile-item">
                                    <div class="students-mobile-label">{{ __('admin.labels.iin') }}</div>
                                    <div class="students-mobile-value">{{ $student->iin ?: '-' }}</div>
                                </div>
                                <div class="students-mobile-item">
                                    <div class="students-mobile-label">{{ __('admin.labels.class_full_name') }}</div>
                                    <div class="students-mobile-value">{{ $student->classroom?->full_name ?: '-' }}</div>
                                </div>
                                @if ($showSchoolFilter)
                                    <div class="students-mobile-item">
                                        <div class="students-mobile-label">{{ __('admin.labels.organization') }}</div>
                                        <div class="students-mobile-value">{{ $student->school?->display_name ?: '-' }}</div>
                                    </div>
                                @endif
                                <div class="students-mobile-item">
                                    <div class="students-mobile-label">{{ __('ui.students.photo_synced_at') }}</div>
                                    <div class="students-mobile-value">{{ $student->photo_synced_at?->format('Y-m-d H:i:s') ?: '-' }}</div>
                                </div>
                                <div class="students-mobile-item">
                                    <div class="students-mobile-label">{{ __('admin.labels.status') }}</div>
                                    <div class="students-mobile-value">
                                        <span class="students-status {{ $mealStatus ? '' : 'muted-state' }}">
                                            {{ $mealStatus && $mealStatusLabel !== 'admin.meal_benefit_types.' . $mealStatus ? $mealStatusLabel : ($mealStatus ? str_replace('_', ' ', ucfirst($mealStatus)) : '-') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="students-table-wrap">
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>{{ __('ui.common.photo') }}</th>
                                <th>{{ __('admin.labels.full_name') }}</th>
                                <th>{{ __('admin.labels.iin') }}</th>
                                <th>{{ __('admin.labels.class_full_name') }}</th>
                                @if ($showSchoolFilter)
                                    <th>{{ __('admin.labels.organization') }}</th>
                                @endif
                                <th>{{ __('ui.students.photo_synced_at') }}</th>
                                <th>{{ __('admin.labels.status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                <tr data-student-edit-url="{{ route('students.edit', $student) }}">
                                    <td class="student-photo-cell">
                                        <div class="student-photo-wrap">
                                            <form method="POST" action="{{ route('students.photo.update', $student) }}"
                                                enctype="multipart/form-data">
                                                @csrf
                                                <input class="student-hidden-input js-student-photo-file" type="file"
                                                    name="photo_file" accept="image/*"
                                                    data-student-id="{{ $student->id }}">
                                            </form>

                                            @if ($student->photo)
                                                <img class="student-photo"
                                                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) }}"
                                                    alt="{{ $student->full_name }}" data-photo-open="{{ $student->id }}"
                                                    data-camera-name="{{ $student->full_name ?: __('ui.dashboard.user_fallback') }}"
                                                    data-photo-url="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) }}"
                                                    data-photo-initial="{{ mb_substr($student->last_name ?: $student->first_name ?: 'S', 0, 1) }}">
                                            @else
                                                <div class="student-photo-placeholder"
                                                    data-photo-open="{{ $student->id }}"
                                                    data-camera-name="{{ $student->full_name ?: __('ui.dashboard.user_fallback') }}"
                                                    data-photo-url=""
                                                    data-photo-initial="{{ mb_substr($student->last_name ?: $student->first_name ?: 'S', 0, 1) }}">
                                                    {{ mb_substr($student->last_name ?: $student->first_name ?: 'S', 0, 1) }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="students-name">
                                            {{ $student->full_name ?: __('ui.dashboard.user_fallback') }}</div>
                                        <div class="students-meta">{{ $student->phone ?: __('ui.common.not_set') }}</div>
                                    </td>
                                    <td>{{ $student->iin ?: '-' }}</td>
                                    <td>{{ $student->classroom?->full_name ?: '-' }}</td>
                                    @if ($showSchoolFilter)
                                        <td>{{ $student->school?->display_name ?: '-' }}</td>
                                    @endif
                                    <td>{{ $student->photo_synced_at?->format('Y-m-d H:i:s') ?: '-' }}</td>
                                    <td>
                                        @php
                                            $mealStatus = $student->latestMealBenefit?->type;
                                            $mealStatusLabel = $mealStatus
                                                ? __('admin.meal_benefit_types.' . $mealStatus)
                                                : '-';
                                        @endphp
                                        <span class="students-status {{ $mealStatus ? '' : 'muted-state' }}">
                                            {{ $mealStatus && $mealStatusLabel !== 'admin.meal_benefit_types.' . $mealStatus ? $mealStatusLabel : ($mealStatus ? str_replace('_', ' ', ucfirst($mealStatus)) : '-') }}
                                        </span>
                                    </td>
                                    <td class="student-actions-cell">
                                        <form class="student-delete-form" method="POST"
                                            action="{{ route('students.destroy', $student) }}"
                                            onsubmit="return confirm(@js(__('ui.students.delete_confirm')));">
                                            @csrf
                                            @method('DELETE')
                                            <button class="student-delete-btn" type="submit" title="{{ __('ui.students.delete') }}"
                                                aria-label="{{ __('ui.students.delete') }}">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path
                                                        d="M9 3h6l1 2h4v2H4V5h4l1-2Zm1 7h2v8h-2v-8Zm4 0h2v8h-2v-8ZM7 10h2v8H7v-8Zm-1 11a2 2 0 0 1-2-2V8h16v11a2 2 0 0 1-2 2H6Z"
                                                        fill="currentColor" />
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="students-pagination">
                    <div class="students-pagination-info">
                        {{ $students->firstItem() }}-{{ $students->lastItem() }} / {{ $students->total() }}
                    </div>
                    <div class="students-pagination-links">
                        @if ($students->onFirstPage())
                            <span class="btn secondary" aria-disabled="true">{{ __('ui.common.previous') }}</span>
                        @else
                            <a class="btn secondary"
                                href="{{ $students->previousPageUrl() }}">{{ __('ui.common.previous') }}</a>
                        @endif

                        @if ($students->hasMorePages())
                            <a class="btn" href="{{ $students->nextPageUrl() }}">{{ __('ui.common.next') }}</a>
                        @else
                            <span class="btn secondary" aria-disabled="true">{{ __('ui.common.next') }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </section>

    <div class="photo-modal" id="photo-modal" data-open="false">
        <div class="photo-panel">
            <div class="photo-panel-header">
                <div>
                    <div class="muted">{{ __('ui.common.photo') }}</div>
                    <strong id="photo-student-name">{{ __('admin.labels.student') }}</strong>
                </div>
                <button class="btn secondary" type="button" id="photo-close">{{ __('ui.common.close') }}</button>
            </div>

            <div class="photo-panel-body">
                <div class="photo-panel-preview">
                    <img class="photo-panel-preview-image" id="photo-preview-image" alt="">
                    <div class="photo-panel-preview-placeholder" id="photo-preview-placeholder">S</div>
                </div>

                <div class="photo-panel-actions">
                    <button class="btn secondary" type="button"
                        id="photo-upload-trigger">{{ __('ui.common.upload') }}</button>
                    <button class="btn" type="button" id="photo-camera-trigger">{{ __('ui.common.photo') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="camera-modal" id="camera-modal" data-open="false">
        <div class="camera-panel">
            <div class="camera-panel-header">
                <div>
                    <div class="muted">{{ __('ui.common.camera') }}</div>
                    <strong id="camera-student-name">{{ __('admin.labels.student') }}</strong>
                </div>
                <button class="btn secondary" type="button" id="camera-close">{{ __('ui.common.close') }}</button>
            </div>

            <div class="camera-panel-body">
                <video class="camera-video" id="camera-video" autoplay playsinline></video>
                <canvas class="camera-canvas" id="camera-canvas"></canvas>
            </div>

            <div class="camera-panel-footer">
                <form method="POST" id="camera-photo-form">
                    @csrf
                    <input type="hidden" name="photo_data" id="camera-photo-data">
                </form>

                <div class="students-actions">
                    <button class="btn secondary" type="button" id="camera-retake"
                        style="display:none;">{{ __('ui.common.retake') }}</button>
                    <button class="btn" type="button" id="camera-capture">{{ __('ui.common.capture') }}</button>
                    <button class="btn" type="submit" form="camera-photo-form" id="camera-save"
                        style="display:none;">{{ __('ui.common.save') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="student-create-modal" id="student-import-modal"
        data-open="{{ $errors->has('students_file') ? 'true' : 'false' }}">
        <div class="student-create-panel" style="width: min(100%, 560px);">
            <div class="student-create-header">
                <div>
                    <div class="muted">{{ __('ui.menu.students') }}</div>
                    <strong>{{ __('ui.students.import_title') }}</strong>
                </div>
                <button class="btn secondary" type="button"
                    id="student-import-close">{{ __('ui.common.close') }}</button>
            </div>

            <div class="student-create-body">
                @if ($errors->has('students_file'))
                    <div class="student-create-error">{{ $errors->first('students_file') }}</div>
                @endif

                <form method="POST" action="{{ route('students.import') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="field">
                        <label for="students_file">{{ __('ui.students.import_file') }}</label>
                        <input id="students_file" name="students_file" type="file" accept=".xlsx,.csv,.txt" required>
                    </div>

                    <div class="students-subtitle" style="margin-top: 12px;">
                        {{ __('ui.students.import_hint') }}
                    </div>

                    <div class="students-actions" style="margin-top: 16px;">
                        <a class="btn secondary"
                            href="{{ route('students.import.template') }}">{{ __('ui.students.import_template') }}</a>
                        <button class="btn" type="submit">{{ __('ui.students.import_submit') }}</button>
                        <button class="btn secondary" type="button"
                            id="student-import-cancel">{{ __('ui.common.close') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="student-create-modal" id="student-create-modal"
        data-open="{{ $errors->any() && ! $errors->has('students_file') ? 'true' : 'false' }}">
        <div class="student-create-panel">
            <div class="student-create-header">
                <div>
                    <div class="muted">{{ __('ui.menu.students') }}</div>
                    <strong>{{ __('ui.common.add') }}</strong>
                </div>
                <button class="btn secondary" type="button"
                    id="student-create-close">{{ __('ui.common.close') }}</button>
            </div>

            <div class="student-create-body">
                @if ($errors->any())
                    <div class="student-create-error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('students.store') }}">
                    @csrf

                    <div class="student-create-grid">
                        <div class="field">
                            <label for="create_iin">{{ __('admin.labels.iin') }}</label>
                            <input id="create_iin" name="iin" type="text" maxlength="12"
                                value="{{ old('iin') }}">
                        </div>

                        <div class="field">
                            <label for="create_last_name">{{ __('admin.labels.last_name') }}</label>
                            <input id="create_last_name" name="last_name" type="text"
                                value="{{ old('last_name') }}">
                        </div>

                        <div class="field">
                            <label for="create_first_name">{{ __('admin.labels.first_name') }}</label>
                            <input id="create_first_name" name="first_name" type="text"
                                value="{{ old('first_name') }}">
                        </div>

                        <div class="field">
                            <label for="create_middle_name">{{ __('admin.labels.middle_name') }}</label>
                            <input id="create_middle_name" name="middle_name" type="text"
                                value="{{ old('middle_name') }}">
                        </div>

                        <div class="field">
                            <label for="create_classroom_id">{{ __('admin.labels.class_full_name') }}</label>
                            <select id="create_classroom_id" name="classroom_id">
                                <option value="">-</option>
                                @foreach ($classrooms as $classroom)
                                    <option value="{{ $classroom->id }}" @selected((string) old('classroom_id') === (string) $classroom->id)>
                                        {{ $classroom->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label for="create_language">{{ __('admin.labels.language') }}</label>
                            <select id="create_language" name="language">
                                <option value="">-</option>
                                <option value="ru" @selected(old('language') === 'ru')>RU</option>
                                <option value="kk" @selected(old('language') === 'kk')>KK</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="create_shift">{{ __('admin.labels.shift') }}</label>
                            <select id="create_shift" name="shift">
                                <option value="">-</option>
                                <option value="1" @selected((string) old('shift') === '1')>1</option>
                                <option value="2" @selected((string) old('shift') === '2')>2</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="create_meal_benefit_type">{{ __('admin.labels.status') }}</label>
                            <select id="create_meal_benefit_type" name="meal_benefit_type">
                                <option value="">-</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('meal_benefit_type') === $status)>
                                        {{ __('admin.meal_benefit_types.' . $status) !== 'admin.meal_benefit_types.' . $status ? __('admin.meal_benefit_types.' . $status) : str_replace('_', ' ', ucfirst($status)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="students-actions" style="margin-top: 16px;">
                        <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                        <button class="btn secondary" type="button"
                            id="student-create-cancel">{{ __('ui.common.close') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const studentImportModal = document.getElementById('student-import-modal');
            const studentImportOpen = document.getElementById('student-import-open');
            const studentImportClose = document.getElementById('student-import-close');
            const studentImportCancel = document.getElementById('student-import-cancel');
            const studentCreateModal = document.getElementById('student-create-modal');
            const studentCreateOpen = document.getElementById('student-create-open');
            const studentCreateClose = document.getElementById('student-create-close');
            const studentCreateCancel = document.getElementById('student-create-cancel');
            const fileInputs = document.querySelectorAll('.js-student-photo-file');
            const photoModal = document.getElementById('photo-modal');
            const photoStudentName = document.getElementById('photo-student-name');
            const photoClose = document.getElementById('photo-close');
            const photoUploadTrigger = document.getElementById('photo-upload-trigger');
            const photoCameraTrigger = document.getElementById('photo-camera-trigger');
            const photoPreviewImage = document.getElementById('photo-preview-image');
            const photoPreviewPlaceholder = document.getElementById('photo-preview-placeholder');
            let activeStudentId = null;
            let activeStudentName = '';

            fileInputs.forEach((input) => {
                input.addEventListener('change', () => {
                    if (input.files && input.files.length > 0) {
                        input.form.submit();
                    }
                });
            });

            const openStudentImportModal = () => {
                studentImportModal.dataset.open = 'true';
            };

            const closeStudentImportModal = () => {
                studentImportModal.dataset.open = 'false';
            };

            const openStudentCreateModal = () => {
                studentCreateModal.dataset.open = 'true';
            };

            const closeStudentCreateModal = () => {
                studentCreateModal.dataset.open = 'false';
            };

            studentImportOpen?.addEventListener('click', openStudentImportModal);
            studentImportClose?.addEventListener('click', closeStudentImportModal);
            studentImportCancel?.addEventListener('click', closeStudentImportModal);
            studentCreateOpen?.addEventListener('click', openStudentCreateModal);
            studentCreateClose?.addEventListener('click', closeStudentCreateModal);
            studentCreateCancel?.addEventListener('click', closeStudentCreateModal);

            const openPhotoModal = (studentId, studentNameText, photoUrl, photoInitial) => {
                activeStudentId = studentId;
                activeStudentName = studentNameText;
                photoStudentName.textContent = studentNameText;

                if (photoUrl) {
                    photoPreviewImage.src = photoUrl;
                    photoPreviewImage.alt = studentNameText;
                    photoPreviewImage.style.display = 'block';
                    photoPreviewPlaceholder.style.display = 'none';
                } else {
                    photoPreviewImage.removeAttribute('src');
                    photoPreviewImage.style.display = 'none';
                    photoPreviewPlaceholder.textContent = photoInitial || 'S';
                    photoPreviewPlaceholder.style.display = 'grid';
                }

                photoModal.dataset.open = 'true';
            };

            const closePhotoModal = () => {
                photoModal.dataset.open = 'false';
            };

            document.querySelectorAll('[data-photo-open]').forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    openPhotoModal(
                        trigger.dataset.photoOpen,
                        trigger.dataset.cameraName,
                        trigger.dataset.photoUrl,
                        trigger.dataset.photoInitial
                    );
                });
            });

            photoUploadTrigger.addEventListener('click', () => {
                if (!activeStudentId) {
                    return;
                }

                const input = document.querySelector(
                    `.js-student-photo-file[data-student-id="${activeStudentId}"]`);

                if (!input) {
                    return;
                }

                closePhotoModal();
                input.click();
            });

            const modal = document.getElementById('camera-modal');
            const video = document.getElementById('camera-video');
            const canvas = document.getElementById('camera-canvas');
            const capture = document.getElementById('camera-capture');
            const retake = document.getElementById('camera-retake');
            const save = document.getElementById('camera-save');
            const close = document.getElementById('camera-close');
            const form = document.getElementById('camera-photo-form');
            const dataField = document.getElementById('camera-photo-data');
            const studentName = document.getElementById('camera-student-name');
            const context = canvas.getContext('2d');
            let stream = null;

            const stopStream = () => {
                if (!stream) {
                    return;
                }

                stream.getTracks().forEach((track) => track.stop());
                stream = null;
            };

            const resetCaptureState = () => {
                canvas.style.display = 'none';
                video.style.display = 'block';
                retake.style.display = 'none';
                save.style.display = 'none';
                capture.style.display = 'inline-flex';
                dataField.value = '';
            };

            const closeModal = () => {
                stopStream();
                modal.dataset.open = 'false';
                resetCaptureState();
            };

            photoCameraTrigger.addEventListener('click', async () => {
                if (!activeStudentId) {
                    return;
                }

                form.action = `/students/${activeStudentId}/photo`;
                studentName.textContent = activeStudentName;
                closePhotoModal();
                modal.dataset.open = 'true';
                resetCaptureState();

                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'user'
                        },
                        audio: false,
                    });
                    video.srcObject = stream;
                } catch (error) {
                    closeModal();
                    window.alert(@json(__('ui.messages.camera_unavailable')));
                }
            });

            capture.addEventListener('click', () => {
                if (!stream) {
                    return;
                }

                const width = video.videoWidth || 640;
                const height = video.videoHeight || 480;
                canvas.width = width;
                canvas.height = height;
                context.drawImage(video, 0, 0, width, height);
                dataField.value = canvas.toDataURL('image/jpeg', 0.9);

                video.style.display = 'none';
                canvas.style.display = 'block';
                capture.style.display = 'none';
                retake.style.display = 'inline-flex';
                save.style.display = 'inline-flex';
            });

            retake.addEventListener('click', () => {
                resetCaptureState();
            });

            close.addEventListener('click', closeModal);
            photoClose.addEventListener('click', closePhotoModal);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });
            photoModal.addEventListener('click', (event) => {
                if (event.target === photoModal) {
                    closePhotoModal();
                }
            });
            studentImportModal?.addEventListener('click', (event) => {
                if (event.target === studentImportModal) {
                    closeStudentImportModal();
                }
            });
            studentCreateModal?.addEventListener('click', (event) => {
                if (event.target === studentCreateModal) {
                    closeStudentCreateModal();
                }
            });

            form.addEventListener('submit', () => {
                stopStream();
            });

            document.querySelectorAll('[data-student-edit-url]').forEach((row) => {
                row.addEventListener('click', (event) => {
                    if (event.target.closest('[data-photo-open], form, input, button, a')) {
                        return;
                    }

                    window.location.href = row.dataset.studentEditUrl;
                });
            });
        })();
    </script>
@endsection
