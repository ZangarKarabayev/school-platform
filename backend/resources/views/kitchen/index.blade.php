@extends('layouts.auth-minimal')

@section('content')
    <style>
        .wrap {
            display: block;
            padding: 18px;
        }

        .card {
            width: min(100%, 1600px);
            min-height: calc(100vh - 36px);
            margin: 0 auto;
        }

        .body {
            min-height: calc(100vh - 120px);
        }

        .kitchen-page {
            height: 100%;
            padding: 0;
        }

        .kitchen-shell {
            height: 100%;
            display: grid;
            gap: 16px;
        }

        .kitchen-toolbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .kitchen-toolbar-school {
            max-width: min(820px, 100%);
            color: #5d7697;
            font-size: 14px;
            line-height: 1.5;
            font-weight: 700;
        }

        .kitchen-toolbar-actions {
            display: flex;
            justify-content: flex-end;
        }

        .kitchen-logout-form {
            margin: 0;
        }

        .kitchen-layout {
            min-height: 100%;
            display: grid;
            grid-template-columns: minmax(520px, 680px) minmax(0, 1fr);
            gap: 16px;
        }

        .kitchen-panel {
            border: 1px solid #dbe4f2;
            border-radius: 20px;
            background: #fff;
            overflow: hidden;
        }

        .kitchen-panel-header {
            padding: 16px 18px;
            border-bottom: 1px solid #e6edf7;
            background: #f7faff;
            display: grid;
            gap: 10px;
        }

        .kitchen-panel-title {
            margin: 0;
            color: #18365f;
            font-size: 20px;
            font-weight: 800;
        }

        .kitchen-panel-subtitle {
            color: #67809f;
            font-size: 14px;
            line-height: 1.5;
        }

        .kitchen-panel-body {
            padding: 16px 18px 18px;
        }

        .kitchen-filters {
            display: grid;
            grid-template-columns: minmax(140px, 0.9fr) minmax(140px, 0.8fr) minmax(180px, 1.35fr);
            gap: 10px;
            align-items: end;
        }

        .kitchen-date-form {
            display: grid;
            gap: 8px;
        }

        .kitchen-date-form label,
        .kitchen-filter-field label {
            font-size: 13px;
            font-weight: 700;
            color: #4d6584;
        }

        .kitchen-date-form input,
        .kitchen-filter-field input {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d0ddee;
            border-radius: 12px;
            font-size: 14px;
        }

        .kitchen-filter-field {
            display: grid;
            gap: 8px;
        }

        .kitchen-orders {
            display: grid;
            gap: 8px;
            max-height: calc(100vh - 340px);
            overflow-y: auto;
            padding-right: 6px;
        }

        .kitchen-orders::-webkit-scrollbar {
            width: 10px;
        }

        .kitchen-orders::-webkit-scrollbar-thumb {
            background: #c8d8ef;
            border-radius: 999px;
            border: 2px solid #fff;
        }

        .kitchen-orders::-webkit-scrollbar-track {
            background: transparent;
        }

        .kitchen-order-card {
            position: relative;
            display: grid;
            gap: 6px;
            padding: 10px 12px;
            border: 1px solid #dbe4f2;
            border-radius: 16px;
            background: #fff;
        }

        .kitchen-order-card.active {
            border-color: #2876dd;
            background: #eef5ff;
            box-shadow: inset 0 0 0 1px rgba(40, 118, 221, 0.08);
        }

        .kitchen-order-link {
            display: grid;
            grid-template-columns: 52px minmax(0, 1fr);
            gap: 10px;
            color: #18365f;
            text-decoration: none;
        }

        .kitchen-order-photo,
        .kitchen-order-placeholder {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            object-fit: cover;
            background: #dce8f8;
            border: 2px solid #edf4ff;
        }

        .kitchen-order-placeholder {
            display: grid;
            place-items: center;
            color: #24487b;
            font-size: 20px;
            font-weight: 800;
        }

        .kitchen-order-content {
            display: grid;
            gap: 6px;
            min-width: 0;
        }

        .kitchen-order-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .kitchen-order-name {
            font-size: 15px;
            font-weight: 800;
            line-height: 1.2;
        }

        .kitchen-order-class {
            color: #67809f;
            font-size: 13px;
            font-weight: 600;
        }

        .kitchen-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            min-width: 30px;
            padding: 0 10px;
            border-radius: 999px;
            font-size: 16px;
            font-weight: 800;
            white-space: nowrap;
            line-height: 1;
        }

        .kitchen-badge.pending {
            background: #fff2e2;
            color: #a85d00;
        }

        .kitchen-badge.done {
            background: #16a34a;
            color: #fff;
        }

        .kitchen-order-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            color: #6f83a0;
            font-size: 12px;
        }

        .kitchen-order-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 0;
        }

        .kitchen-order-actions {
            display: flex;
            justify-content: flex-end;
        }

        .kitchen-order-check {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4f6d96;
            font-size: 13px;
            font-weight: 700;
            user-select: none;
        }

        .kitchen-order-check input {
            width: 18px;
            height: 18px;
            accent-color: #2876dd;
            cursor: pointer;
        }

        .kitchen-order-check input:disabled {
            cursor: not-allowed;
            opacity: 0.55;
        }

        .kitchen-selection-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #dbe4f2;
            border-radius: 14px;
            background: #f7faff;
            margin-bottom: 16px;
        }

        .kitchen-selection-count {
            color: #35557f;
            font-size: 14px;
            font-weight: 800;
        }

        .kitchen-inline-form {
            margin: 0;
        }

        .kitchen-btn.small {
            min-height: 32px;
            min-width: 40px;
            padding: 0 10px;
            font-size: 18px;
        }

        .kitchen-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .kitchen-empty {
            padding: 24px 10px;
            color: #6f83a0;
            text-align: center;
            line-height: 1.6;
        }

        .kitchen-detail {
            display: grid;
            gap: 18px;
        }

        .kitchen-student {
            display: grid;
            justify-items: center;
            gap: 14px;
            padding: 8px 0 4px;
            text-align: center;
        }

        .kitchen-student-photo,
        .kitchen-student-placeholder {
            width: 180px;
            height: 180px;
            border-radius: 999px;
            object-fit: cover;
            border: 6px solid #edf4ff;
            background: #dce8f8;
        }

        .kitchen-student-placeholder {
            display: grid;
            place-items: center;
            color: #24487b;
            font-size: 56px;
            font-weight: 800;
        }

        .kitchen-student-name {
            margin: 0;
            color: #18365f;
            font-size: 30px;
            font-weight: 900;
            line-height: 1.1;
        }

        .kitchen-student-class {
            color: #67809f;
            font-size: 17px;
            font-weight: 700;
        }

        .kitchen-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .kitchen-detail-card {
            padding: 14px 16px;
            border: 1px solid #dbe4f2;
            border-radius: 16px;
            background: #f8fbff;
        }

        .kitchen-detail-label {
            color: #6f83a0;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .kitchen-detail-value {
            margin-top: 8px;
            color: #18365f;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.35;
        }

        .kitchen-detail-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .kitchen-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 16px;
            border: 0;
            border-radius: 12px;
            background: #2876dd;
            color: #fff;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
        }

        .kitchen-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .kitchen-icon.status {
            font-size: 15px;
        }

        .kitchen-icon.action {
            font-size: 18px;
        }

        .kitchen-btn.secondary {
            background: #edf4ff;
            color: #1e4f91;
            border: 1px solid #cfe0fb;
        }

        .kitchen-btn.done {
            background: #d9e5f4;
            color: #38557e;
            cursor: default;
        }

        .kitchen-note,
        .kitchen-success {
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
        }

        .kitchen-note {
            background: #fff4e6;
            color: #9f5e0f;
        }

        .kitchen-success {
            background: #e7f7ee;
            color: #1f8a54;
        }

        @media (max-width: 980px) {
            .wrap {
                padding: 12px;
            }

            .card {
                min-height: calc(100vh - 24px);
            }

            .body {
                min-height: calc(100vh - 108px);
            }

            .kitchen-layout {
                grid-template-columns: 1fr;
            }

            .kitchen-filters {
                grid-template-columns: 1fr;
            }

            .kitchen-orders {
                max-height: none;
                overflow: visible;
                padding-right: 0;
            }
        }

        @media (max-width: 640px) {
            .kitchen-detail-grid {
                grid-template-columns: 1fr;
            }

            .kitchen-student-photo,
            .kitchen-student-placeholder {
                width: 132px;
                height: 132px;
            }

            .kitchen-student-name {
                font-size: 24px;
            }
        }
    </style>

    <section class="kitchen-page">
        <div class="kitchen-shell">
            <div class="kitchen-toolbar">
                <div class="kitchen-toolbar-school">
                    @if ($school)
                        {{ $school->display_name ?? $school->name }}
                    @endif
                </div>
                <div class="kitchen-toolbar-actions">
                    <form class="kitchen-logout-form" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="kitchen-btn secondary" type="submit">{{ __('ui.common.logout') }}</button>
                    </form>
                </div>
            </div>

            @if (session('kitchen_status'))
                <div class="kitchen-success">{{ session('kitchen_status') }}</div>
            @endif

            @if (! $school)
                <div class="kitchen-note">{{ __('ui.kitchen_page.access_denied') }}</div>
            @else
                <div class="kitchen-layout">
                    <section class="kitchen-panel">
                        <div class="kitchen-panel-header">
                            <h2 class="kitchen-panel-title">{{ __('ui.kitchen_page.orders_title') }}</h2>
                        </div>
                        <div class="kitchen-panel-body">
                            <form id="kitchen-filters-form" class="kitchen-filters" method="GET" action="{{ route('kitchen.index') }}">
                                <div class="kitchen-date-form">
                                    <label for="kitchen_date">{{ __('ui.kitchen_page.date') }}</label>
                                    <input id="kitchen_date" type="date" name="date" value="{{ $selectedDate }}">
                                </div>
                                <div class="kitchen-filter-field">
                                    <label for="kitchen_class">{{ __('ui.kitchen_page.search_class') }}</label>
                                    <input id="kitchen_class" type="text" name="class" value="{{ $classQuery }}"
                                        placeholder="{{ __('ui.kitchen_page.search_class_placeholder') }}">
                                </div>
                                <div class="kitchen-filter-field">
                                    <label for="kitchen_student">{{ __('ui.kitchen_page.search_student') }}</label>
                                    <input id="kitchen_student" type="text" name="q" value="{{ $studentQuery }}"
                                        placeholder="{{ __('ui.kitchen_page.search_student_placeholder') }}">
                                </div>
                            </form>

                            <div style="height: 16px;"></div>

                            @if ($orders->isEmpty())
                                <div class="kitchen-empty">{{ __('ui.kitchen_page.empty_for_date') }}</div>
                            @else
                                <form id="kitchen-bulk-complete-form" method="POST" action="{{ route('kitchen.orders.complete-selected') }}">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ $selectedDate }}">
                                    <div class="kitchen-selection-bar">
                                        <div class="kitchen-selection-count" id="kitchen-selection-count">{{ __('ui.kitchen_page.selected_count', ['count' => 0]) }}</div>
                                        <div id="kitchen-bulk-order-ids"></div>
                                        <button class="kitchen-btn small" id="kitchen-bulk-submit" type="submit" disabled>{{ __('ui.kitchen_page.done_selected') }}</button>
                                    </div>
                                </form>

                                <div class="kitchen-orders">
                                    @foreach ($orders as $order)
                                        @php
                                            $isCompleted = in_array($order->status, [\App\Models\Order::STATUS_ISSUED, \App\Models\Order::STATUS_COMPLETED], true);
                                            $isActive = (int) optional($selectedOrder)->id === (int) $order->id;
                                            $studentName = $order->student?->full_name ?: __('ui.kitchen_page.student_fallback');
                                            $studentInitial = mb_strtoupper(mb_substr($studentName, 0, 1));
                                        @endphp
                                        <article @class(['kitchen-order-card', 'active' => $isActive])>
                                            <a class="kitchen-order-link"
                                                href="{{ route('kitchen.index', ['date' => $selectedDate, 'order_id' => $order->id]) }}">
                                                @if ($order->student?->photo_url)
                                                    <img class="kitchen-order-photo" src="{{ $order->student->photo_url }}" alt="{{ $studentName }}">
                                                @else
                                                    <div class="kitchen-order-placeholder">{{ $studentInitial }}</div>
                                                @endif

                                                <div class="kitchen-order-content">
                                                    <div class="kitchen-order-top">
                                                        <div class="kitchen-order-name">{{ $studentName }}</div>
                                                        <span @class(['kitchen-badge', 'done' => $isCompleted, 'pending' => ! $isCompleted])>
                                                            <span class="kitchen-icon status" aria-label="{{ $isCompleted ? __('ui.kitchen_page.status_issued') : __('ui.kitchen_page.status_not_issued') }}" title="{{ $isCompleted ? __('ui.kitchen_page.status_issued') : __('ui.kitchen_page.status_not_issued') }}">
                                                                {{ $isCompleted ? '✓' : '◷' }}
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <div class="kitchen-order-class">{{ $order->student?->classroom?->full_name ?: __('ui.kitchen_page.class_not_set') }}</div>
                                                    <div class="kitchen-order-meta">
                                                        <span>{{ __('ui.kitchen_page.created_at') }}: {{ $order->created_at?->format('d.m.Y H:i') ?: '-' }}</span>
                                                        <span>{{ __('ui.kitchen_page.issued_at') }}: {{ $order->completed_at?->format('d.m.Y H:i') ?: '-' }}</span>
                                                    </div>
                                                </div>
                                            </a>

                                            <div class="kitchen-order-footer">
                                                <label class="kitchen-order-check">
                                                    <input type="checkbox" value="{{ $order->id }}" data-order-checkbox @disabled($isCompleted)>
                                                </label>

                                                @if (! $isCompleted)
                                                    <div class="kitchen-order-actions">
                                                        <form class="kitchen-inline-form" method="POST" action="{{ route('kitchen.orders.complete', $order) }}">
                                                            @csrf
                                                            <button class="kitchen-btn small" type="submit" aria-label="{{ __('ui.kitchen_page.done') }}" title="{{ __('ui.kitchen_page.done') }}">
                                                                <span class="kitchen-icon action">✓</span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>

                    <section class="kitchen-panel">
                        <div class="kitchen-panel-header">
                            <h2 class="kitchen-panel-title">{{ __('ui.kitchen_page.details_title') }}</h2>
                            <div class="kitchen-panel-subtitle">{{ __('ui.kitchen_page.details_subtitle') }}</div>
                        </div>
                        <div class="kitchen-panel-body">
                            @if (! $selectedOrder)
                                <div class="kitchen-empty">{{ __('ui.kitchen_page.choose_order') }}</div>
                            @else
                                @php
                                    $student = $selectedOrder->student;
                                    $isCompleted = in_array($selectedOrder->status, [\App\Models\Order::STATUS_ISSUED, \App\Models\Order::STATUS_COMPLETED], true);
                                    $studentName = $student?->full_name ?: __('ui.kitchen_page.student_fallback');
                                    $studentClass = $student?->classroom?->full_name ?: __('ui.kitchen_page.class_not_set');
                                    $initial = mb_strtoupper(mb_substr($studentName, 0, 1));
                                @endphp

                                <div class="kitchen-detail">
                                    <div class="kitchen-student">
                                        @if ($student?->photo_url)
                                            <img class="kitchen-student-photo" src="{{ $student->photo_url }}" alt="{{ $studentName }}">
                                        @else
                                            <div class="kitchen-student-placeholder">{{ $initial }}</div>
                                        @endif

                                        <div>
                                            <h3 class="kitchen-student-name">{{ $studentName }}</h3>
                                            <div class="kitchen-student-class">{{ $studentClass }}</div>
                                        </div>
                                        <span @class(['kitchen-badge', 'done' => $isCompleted, 'pending' => ! $isCompleted])>
                                            <span class="kitchen-icon status" aria-label="{{ $isCompleted ? __('ui.kitchen_page.status_issued') : __('ui.kitchen_page.status_not_issued') }}" title="{{ $isCompleted ? __('ui.kitchen_page.status_issued') : __('ui.kitchen_page.status_not_issued') }}">
                                                {{ $isCompleted ? '✓' : '◷' }}
                                            </span>
                                        </span>
                                    </div>

                                    <div class="kitchen-detail-grid">
                                        <div class="kitchen-detail-card">
                                            <div class="kitchen-detail-label">{{ __('ui.kitchen_page.order_created_when') }}</div>
                                            <div class="kitchen-detail-value">{{ $selectedOrder->created_at?->format('d.m.Y H:i') ?: '-' }}</div>
                                        </div>
                                        <div class="kitchen-detail-card">
                                            <div class="kitchen-detail-label">{{ __('ui.kitchen_page.order_issued_when') }}</div>
                                            <div class="kitchen-detail-value">{{ $selectedOrder->completed_at?->format('d.m.Y H:i') ?: __('ui.kitchen_page.not_issued') }}</div>
                                        </div>
                                    </div>

                                    <div class="kitchen-detail-actions">
                                        @if (! $isCompleted)
                                            <form method="POST" action="{{ route('kitchen.orders.complete', $selectedOrder) }}">
                                                @csrf
                                                <button class="kitchen-btn" type="submit" aria-label="{{ __('ui.kitchen_page.done') }}" title="{{ __('ui.kitchen_page.done') }}">
                                                    <span class="kitchen-icon action">✓</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            @endif
        </div>
    </section>

    @if ($school && $orders->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const filtersForm = document.getElementById('kitchen-filters-form');
                const filterInputs = filtersForm ? Array.from(filtersForm.querySelectorAll('input')) : [];
                let filterTimer = null;

                const submitFilters = () => {
                    if (!filtersForm) {
                        return;
                    }

                    filtersForm.requestSubmit();
                };

                const scheduleFiltersSubmit = () => {
                    if (filterTimer) {
                        clearTimeout(filterTimer);
                    }

                    filterTimer = setTimeout(submitFilters, 350);
                };

                filterInputs.forEach((input) => {
                    if (input.name === 'date') {
                        input.addEventListener('change', submitFilters);
                        return;
                    }

                    input.addEventListener('input', scheduleFiltersSubmit);
                    input.addEventListener('search', scheduleFiltersSubmit);
                });

                const storageKey = ['kitchen-selected-orders', @json((int) $school->id), @json($selectedDate)].join(':');
                const checkboxes = Array.from(document.querySelectorAll('[data-order-checkbox]'));
                const countNode = document.getElementById('kitchen-selection-count');
                const submitButton = document.getElementById('kitchen-bulk-submit');
                const idsContainer = document.getElementById('kitchen-bulk-order-ids');

                const readSelection = () => {
                    try {
                        const parsed = JSON.parse(sessionStorage.getItem(storageKey) || '[]');

                        return Array.isArray(parsed) ? parsed.map((value) => String(value)) : [];
                    } catch (error) {
                        return [];
                    }
                };

                const writeSelection = (values) => {
                    sessionStorage.setItem(storageKey, JSON.stringify(values));
                };

                const renderSelectionState = () => {
                    const selectedIds = checkboxes
                        .filter((checkbox) => checkbox.checked && !checkbox.disabled)
                        .map((checkbox) => checkbox.value);

                    writeSelection(selectedIds);
                    idsContainer.innerHTML = '';

                    selectedIds.forEach((id) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'order_ids[]';
                        input.value = id;
                        idsContainer.appendChild(input);
                    });

                    countNode.textContent = @json(__('ui.kitchen_page.selected_count', ['count' => ':count'])).replace(':count', selectedIds.length);
                    submitButton.disabled = selectedIds.length === 0;
                };

                const selectedFromStorage = new Set(readSelection());

                checkboxes.forEach((checkbox) => {
                    if (!checkbox.disabled && selectedFromStorage.has(checkbox.value)) {
                        checkbox.checked = true;
                    }

                    checkbox.addEventListener('change', renderSelectionState);
                });

                renderSelectionState();
            });
        </script>
    @endif
@endsection
