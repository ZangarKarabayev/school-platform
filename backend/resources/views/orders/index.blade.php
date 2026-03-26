@extends('layouts.app')

@section('content')
    <style>
        .orders-page {
            padding: 24px 0;
        }

        .orders-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
        }

        .orders-header {
            padding: 24px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .orders-title {
            margin: 8px 0 0;
            font-size: 30px;
            line-height: 1.1;
        }

        .orders-header-actions {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .orders-count {
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

        .orders-notice {
            margin: 0 24px 24px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #eaf6ea;
            color: #22653a;
            font-weight: 700;
        }

        .orders-table-wrap {
            overflow: auto;
        }

        .orders-mobile-list {
            display: none;
            padding: 16px;
            gap: 14px;
        }

        .orders-mobile-card {
            border: 1px solid #e1e8f2;
            border-radius: 18px;
            background: #fff;
            padding: 16px;
            display: grid;
            gap: 14px;
        }

        .orders-mobile-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .orders-mobile-identity {
            min-width: 0;
        }

        .orders-mobile-name {
            color: #1d3151;
            font-weight: 700;
            word-break: break-word;
        }

        .orders-mobile-meta {
            margin-top: 4px;
            color: #71829a;
            font-size: 13px;
            word-break: break-word;
        }

        .orders-mobile-actions {
            display: flex;
            justify-content: flex-end;
            flex-shrink: 0;
        }

        .orders-mobile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .orders-mobile-item {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .orders-mobile-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #71829a;
        }

        .orders-mobile-value {
            color: #16253d;
            word-break: break-word;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }

        .orders-table th,
        .orders-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #e8edf5;
            text-align: left;
            vertical-align: top;
        }

        .orders-actions-cell {
            width: 72px;
            text-align: center;
        }

        .orders-delete-form {
            display: inline-flex;
        }

        .orders-delete-btn {
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

        .orders-delete-btn:hover {
            background: #f9d6dc;
        }

        .orders-delete-btn svg {
            width: 18px;
            height: 18px;
            display: block;
        }

        .orders-table th {
            background: #f7f9fc;
            color: #4e607d;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .orders-empty {
            padding: 28px 24px 32px;
            color: #71829a;
        }

        .orders-filters {
            padding: 0 24px 24px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .orders-pagination {
            padding: 18px 24px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .orders-pagination-info {
            color: #71829a;
            font-size: 14px;
        }

        .orders-pagination-links {
            display: flex;
            gap: 10px;
        }

        .orders-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, 0.58);
            z-index: 60;
        }

        .orders-modal[data-open="true"] {
            display: flex;
        }

        .orders-modal-panel {
            width: min(100%, 980px);
            max-height: min(88vh, 920px);
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(19, 41, 77, 0.28);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .orders-modal-header,
        .orders-modal-body {
            padding: 24px;
        }

        .orders-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid #e4e9f1;
        }

        .orders-modal-title {
            margin: 8px 0 0;
            font-size: 24px;
            line-height: 1.15;
        }

        .orders-modal-body {
            overflow: auto;
        }

        .orders-form {
            display: grid;
            gap: 14px;
        }

        .orders-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .orders-form-field {
            display: grid;
            gap: 6px;
        }

        .orders-form-field.full {
            grid-column: 1 / -1;
        }

        .orders-form-field label {
            font-size: 13px;
            font-weight: 700;
            color: #4e607d;
        }

        .orders-filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .orders-form-field input,
        .orders-form-field select,
        .orders-form-field textarea {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d1d8e5;
            border-radius: 12px;
            background: #fff;
            color: #16253d;
        }

        .orders-form-field textarea {
            min-height: 96px;
            resize: vertical;
        }

        .orders-target-switcher {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            padding: 6px;
            border-radius: 16px;
            background: #eef3fb;
        }

        .orders-target-option {
            position: relative;
        }

        .orders-target-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .orders-target-option span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 12px;
            border-radius: 12px;
            color: #31507d;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }

        .orders-target-option input:checked+span {
            background: #2876dd;
            color: #fff;
            box-shadow: 0 10px 20px rgba(40, 118, 221, 0.22);
        }

        .orders-target-panel {
            display: grid;
            gap: 12px;
            padding: 14px;
            border: 1px solid #dbe4f2;
            border-radius: 16px;
            background: #f8fbff;
        }

        .orders-target-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .orders-target-hint {
            color: #71829a;
            font-size: 13px;
        }

        .orders-link-btn {
            padding: 0;
            border: 0;
            background: none;
            color: #2876dd;
            font-weight: 700;
            cursor: pointer;
        }

        .orders-selection-count {
            color: #31507d;
            font-size: 13px;
            font-weight: 700;
        }

        .orders-class-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            max-height: 280px;
            overflow: auto;
            padding-right: 4px;
        }

        .orders-check-card {
            position: relative;
            display: block;
        }

        .orders-check-card input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .orders-check-card-body {
            display: grid;
            gap: 4px;
            padding: 14px;
            border: 1px solid #d1d8e5;
            border-radius: 14px;
            background: #fff;
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .orders-check-card input:checked+.orders-check-card-body {
            border-color: #1459b8;
            background: linear-gradient(135deg, #dcecff 0%, #eef4ff 100%);
            box-shadow: 0 14px 28px rgba(20, 89, 184, 0.22);
            transform: translateY(-1px);
        }

        .orders-check-card input:checked+.orders-check-card-body .orders-check-card-title {
            color: #0f4188;
        }

        .orders-check-card input:checked+.orders-check-card-body .orders-check-card-meta {
            color: #1f5cb8;
        }

        .orders-check-card-title {
            color: #1d3151;
            font-weight: 700;
        }

        .orders-check-card-meta {
            color: #71829a;
            font-size: 12px;
        }

        .orders-student-search {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d1d8e5;
            border-radius: 12px;
            background: #fff;
            color: #16253d;
        }

        .orders-student-list {
            max-height: 280px;
            overflow: auto;
            display: grid;
            gap: 8px;
            padding-right: 4px;
        }

        .orders-student-group {
            display: grid;
            gap: 8px;
        }

        .orders-student-group-title {
            position: sticky;
            top: 0;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 10px;
            background: #eaf1fb;
            color: #31507d;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .orders-student-group-title-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .orders-student-group-checkbox {
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: #2876dd;
            cursor: pointer;
        }

        .orders-student-group-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .orders-student-group-toggle {
            padding: 0;
            border: 0;
            background: none;
            color: #31507d;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .orders-student-group-body {
            display: grid;
            gap: 8px;
        }

        .orders-student-group[data-collapsed="true"] .orders-student-group-body {
            display: none;
        }

        .orders-student-row {
            position: relative;
            display: block;
        }

        .orders-student-row input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .orders-student-row-body {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #d1d8e5;
            border-radius: 14px;
            background: #fff;
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .orders-student-row input:checked+.orders-student-row-body {
            border-color: #1459b8;
            background: linear-gradient(135deg, #dcecff 0%, #eef4ff 100%);
            box-shadow: 0 14px 28px rgba(20, 89, 184, 0.22);
            transform: translateY(-1px);
        }

        .orders-student-row input:checked+.orders-student-row-body .orders-student-name,
        .orders-student-row input:checked+.orders-student-row-body .orders-student-class {
            color: #0f4188;
        }

        .orders-student-row input:checked+.orders-student-row-body .orders-student-meta {
            color: #1f5cb8;
        }

        .orders-student-name {
            color: #1d3151;
            font-weight: 700;
        }

        .orders-student-meta {
            margin-top: 4px;
            color: #71829a;
            font-size: 12px;
        }

        .orders-student-class {
            color: #31507d;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .orders-empty-state {
            padding: 18px 14px;
            border-radius: 14px;
            background: #fff;
            color: #71829a;
            text-align: center;
        }

        .orders-form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .orders-error {
            margin-bottom: 14px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fff3f1;
            color: #b43e2a;
            font-size: 14px;
        }

        .orders-bool {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eef5ff;
            color: #1f5cb8;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
        }

        .orders-bool.inactive {
            background: #f0f2f6;
            color: #697991;
        }

        .orders-status {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eef5ff;
            color: #1f5cb8;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            text-transform: uppercase;
        }

        .orders-status.muted-state {
            background: #f0f2f6;
            color: #697991;
        }

        @media (max-width: 780px) {
            .orders-modal {
                padding: 14px;
            }

            .orders-modal-panel {
                width: 100%;
                max-height: 92vh;
            }

            .orders-filters {
                grid-template-columns: 1fr;
            }

            .orders-header {
                flex-direction: column;
            }

            .orders-count {
                min-width: 0;
                width: 100%;
                border-radius: 12px;
            }

            .orders-form-grid {
                grid-template-columns: 1fr;
            }

            .orders-class-grid {
                grid-template-columns: 1fr;
            }

            .orders-target-switcher {
                grid-template-columns: 1fr;
            }

            .orders-table-wrap {
                display: none;
            }

            .orders-mobile-list {
                display: grid;
            }
        }

        @media (max-width: 520px) {
            .orders-mobile-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <section class="orders-page">
        <div class="orders-card">
            <div class="orders-header">
                <div>
                    <div class="muted">{{ __('ui.common.home') }}</div>
                    <h1 class="orders-title">{{ __('ui.menu.orders') }}</h1>
                </div>
                <div class="orders-header-actions">
                    <button class="btn" type="button" id="orders-create-open">{{ __('ui.orders.create_order') }}</button>
                    <div class="orders-count">
                        {{ $orders->total() }}
                    </div>
                </div>
            </div>

            @if (session('order_status'))
                <div class="orders-notice">{{ session('order_status') }}</div>
            @endif

            <form method="GET" action="{{ route('orders.index') }}" class="orders-filters">
                <div class="orders-form-field">
                    <label for="search">{{ __('admin.labels.full_name') }} / {{ __('admin.labels.iin') }}</label>
                    <input id="search" type="text" name="search" value="{{ $filters['search'] }}"
                        placeholder="{{ __('admin.labels.full_name') }}, {{ __('admin.labels.iin') }}">
                </div>

                <div class="orders-form-field">
                    <label for="filter_order_date">{{ __('ui.orders.date') }}</label>
                    <input id="filter_order_date" type="date" name="order_date" value="{{ $filters['order_date'] }}">
                </div>

                <div class="orders-form-field">
                    <label for="filter_transaction_status">{{ __('ui.orders.transaction_status') }}</label>
                    <select id="filter_transaction_status" name="transaction_status">
                        <option value="">-</option>
                        <option value="1" @selected($filters['transaction_status'] === '1')>
                            {{ __('ui.orders.transaction_result.success') }}</option>
                        <option value="0" @selected($filters['transaction_status'] === '0')>{{ __('ui.orders.transaction_result.failed') }}
                        </option>
                    </select>
                </div>

                <div class="orders-form-field">
                    <label for="filter_transaction_error">{{ __('ui.orders.transaction_error') }}</label>
                    <input id="filter_transaction_error" type="text" name="transaction_error"
                        value="{{ $filters['transaction_error'] }}">
                </div>

                <div class="orders-filter-actions">
                    <button class="btn" type="submit">{{ __('ui.common.filter') }}</button>
                    <a class="btn secondary" href="{{ route('orders.index') }}">{{ __('ui.common.reset') }}</a>
                </div>
            </form>

            @if ($orders->isEmpty())
                <div class="orders-empty">{{ __('ui.orders.empty') }}</div>
            @else
                <div class="orders-table-wrap">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>{{ __('admin.labels.student') }}</th>
                                <th>{{ __('admin.labels.dish') }}</th>
                                <th>{{ __('ui.orders.date') }}</th>
                                <th>{{ __('admin.labels.status') }}</th>
                                <th>{{ __('ui.orders.transaction_status') }}</th>
                                <th>{{ __('ui.orders.transaction_error') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td>
                                        <div>{{ $order->student?->full_name ?: '-' }}</div>
                                        <div class="muted">{{ $order->student?->iin ?: '-' }}</div>
                                    </td>
                                    <td>{{ $order->dish?->name ?: '-' }}</td>
                                    <td>
                                        <div>{{ optional($order->order_date)->format('Y-m-d') ?: '-' }}</div>
                                        <div class="muted">
                                            {{ $order->order_time ? substr($order->order_time, 0, 5) : '-' }}</div>
                                    </td>
                                    <td>
                                        @php
                                            $orderStatus = $order->status;
                                            $orderStatusLabel = $orderStatus
                                                ? __('ui.orders.statuses.' . $orderStatus)
                                                : '-';
                                        @endphp
                                        <span class="orders-status {{ $orderStatus ? '' : 'muted-state' }}">
                                            {{ $orderStatus && $orderStatusLabel !== 'ui.orders.statuses.' . $orderStatus ? $orderStatusLabel : ($orderStatus ?: '-') }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($order->transaction_status === null)
                                            -
                                        @else
                                            <span class="orders-bool {{ $order->transaction_status ? '' : 'inactive' }}">
                                                {{ $order->transaction_status ? __('ui.orders.transaction_result.success') : __('ui.orders.transaction_result.failed') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $order->transaction_error ?: '-' }}</td>
                                    <td class="orders-actions-cell">
                                        <form class="orders-delete-form" method="POST"
                                            action="{{ route('orders.destroy', $order) }}"
                                            onsubmit="return confirm(@js(__('ui.orders.delete_confirm')));">
                                            @csrf
                                            @method('DELETE')
                                            <button class="orders-delete-btn" type="submit"
                                                title="{{ __('ui.orders.delete') }}"
                                                aria-label="{{ __('ui.orders.delete') }}">
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

                <div class="orders-mobile-list">
                    @foreach ($orders as $order)
                        @php
                            $mobileOrderStatus = $order->status;
                            $mobileOrderStatusLabel = $mobileOrderStatus
                                ? __('ui.orders.statuses.' . $mobileOrderStatus)
                                : '-';
                        @endphp
                        <article class="orders-mobile-card">
                            <div class="orders-mobile-top">
                                <div class="orders-mobile-identity">
                                    <div class="orders-mobile-name">{{ $order->student?->full_name ?: '-' }}</div>
                                    <div class="orders-mobile-meta">{{ $order->student?->iin ?: '-' }}</div>
                                </div>
                                <div class="orders-mobile-actions">
                                    <form class="orders-delete-form" method="POST"
                                        action="{{ route('orders.destroy', $order) }}"
                                        onsubmit="return confirm(@js(__('ui.orders.delete_confirm')));">
                                        @csrf
                                        @method('DELETE')
                                        <button class="orders-delete-btn" type="submit"
                                            title="{{ __('ui.orders.delete') }}"
                                            aria-label="{{ __('ui.orders.delete') }}">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path
                                                    d="M9 3h6l1 2h4v2H4V5h4l1-2Zm1 7h2v8h-2v-8Zm4 0h2v8h-2v-8ZM7 10h2v8H7v-8Zm-1 11a2 2 0 0 1-2-2V8h16v11a2 2 0 0 1-2 2H6Z"
                                                    fill="currentColor" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="orders-mobile-grid">
                                <div class="orders-mobile-item">
                                    <div class="orders-mobile-label">{{ __('admin.labels.dish') }}</div>
                                    <div class="orders-mobile-value">{{ $order->dish?->name ?: '-' }}</div>
                                </div>
                                <div class="orders-mobile-item">
                                    <div class="orders-mobile-label">{{ __('ui.orders.date') }}</div>
                                    <div class="orders-mobile-value">
                                        <div>{{ optional($order->order_date)->format('Y-m-d') ?: '-' }}</div>
                                        <div class="muted">
                                            {{ $order->order_time ? substr($order->order_time, 0, 5) : '-' }}</div>
                                    </div>
                                </div>
                                <div class="orders-mobile-item">
                                    <div class="orders-mobile-label">{{ __('admin.labels.status') }}</div>
                                    <div class="orders-mobile-value">
                                        <span class="orders-status {{ $mobileOrderStatus ? '' : 'muted-state' }}">
                                            {{ $mobileOrderStatus && $mobileOrderStatusLabel !== 'ui.orders.statuses.' . $mobileOrderStatus ? $mobileOrderStatusLabel : ($mobileOrderStatus ?: '-') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="orders-mobile-item">
                                    <div class="orders-mobile-label">{{ __('ui.orders.transaction_status') }}</div>
                                    <div class="orders-mobile-value">
                                        @if ($order->transaction_status === null)
                                            -
                                        @else
                                            <span class="orders-bool {{ $order->transaction_status ? '' : 'inactive' }}">
                                                {{ $order->transaction_status ? __('ui.orders.transaction_result.success') : __('ui.orders.transaction_result.failed') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="orders-mobile-item" style="grid-column: 1 / -1;">
                                    <div class="orders-mobile-label">{{ __('ui.orders.transaction_error') }}</div>
                                    <div class="orders-mobile-value">{{ $order->transaction_error ?: '-' }}</div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="orders-pagination">
                    <div class="orders-pagination-info">
                        {{ $orders->firstItem() }}-{{ $orders->lastItem() }} / {{ $orders->total() }}
                    </div>
                    <div class="orders-pagination-links">
                        @if ($orders->onFirstPage())
                            <span class="btn secondary" aria-disabled="true">{{ __('ui.common.previous') }}</span>
                        @else
                            <a class="btn secondary"
                                href="{{ $orders->previousPageUrl() }}">{{ __('ui.common.previous') }}</a>
                        @endif

                        @if ($orders->hasMorePages())
                            <a class="btn" href="{{ $orders->nextPageUrl() }}">{{ __('ui.common.next') }}</a>
                        @else
                            <span class="btn secondary" aria-disabled="true">{{ __('ui.common.next') }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </section>

    <div class="orders-modal" id="orders-create-modal" data-open="{{ $errors->any() ? 'true' : 'false' }}">
        <div class="orders-modal-panel">
            <div class="orders-modal-header">
                <div>
                    <div class="muted">{{ __('ui.menu.orders') }}</div>
                    <h2 class="orders-modal-title">{{ __('ui.orders.create_order') }}</h2>
                </div>
                <button class="btn secondary" type="button"
                    id="orders-create-close">{{ __('ui.common.close') }}</button>
            </div>

            <div class="orders-modal-body">
                @if ($errors->any())
                    <div class="orders-error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('orders.store') }}" class="orders-form">
                    @csrf

                    <div class="orders-form-grid">
                        <div class="orders-form-field full">
                            <div class="orders-target-switcher">
                                <label class="orders-target-option">
                                    <input type="radio" name="target_type" value="all" @checked(old('target_type', 'all') === 'all')>
                                    <span>{{ __('ui.orders.recipient_all') }}</span>
                                </label>
                                <label class="orders-target-option">
                                    <input type="radio" name="target_type" value="classes"
                                        @checked(old('target_type') === 'classes')>
                                    <span>{{ __('ui.orders.recipient_classes') }}</span>
                                </label>
                                <label class="orders-target-option">
                                    <input type="radio" name="target_type" value="students"
                                        @checked(old('target_type') === 'students')>
                                    <span>{{ __('ui.orders.recipient_students') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="orders-form-field full" data-target-section="classes"
                            style="{{ old('target_type') === 'classes' ? '' : 'display:none;' }}">
                            <div class="orders-target-panel">
                                <div class="orders-target-toolbar">

                                    <div class="orders-selection-count" id="classroom-selection-count">0
                                        {{ __('ui.orders.selected') }}</div>
                                </div>
                                <div class="orders-target-toolbar">
                                    <button class="orders-link-btn" type="button"
                                        data-select-all="classes">{{ __('ui.orders.select_all') }}</button>
                                    <button class="orders-link-btn" type="button"
                                        data-clear-all="classes">{{ __('ui.orders.clear_all') }}</button>
                                </div>
                                @if ($classrooms->isEmpty())
                                    <div class="orders-empty-state">{{ __('ui.orders.no_classes') }}</div>
                                @else
                                    <div class="orders-class-grid">
                                        @foreach ($classrooms as $classroom)
                                            @php
                                                $classroomStudentsCount = $students
                                                    ->where('classroom_id', $classroom->id)
                                                    ->count();
                                                $isClassroomSelected =
                                                    collect(old('classroom_ids', []))->contains(
                                                        (string) $classroom->id,
                                                    ) || collect(old('classroom_ids', []))->contains($classroom->id);
                                            @endphp
                                            <label class="orders-check-card">
                                                <input type="checkbox" name="classroom_ids[]"
                                                    value="{{ $classroom->id }}" data-classroom-checkbox
                                                    @checked($isClassroomSelected)>
                                                <span class="orders-check-card-body">
                                                    <span
                                                        class="orders-check-card-title">{{ $classroom->full_name }}</span>
                                                    <span
                                                        class="orders-check-card-meta">{{ __('ui.orders.students_count', ['count' => $classroomStudentsCount]) }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="orders-form-field full" data-target-section="students"
                            style="{{ old('target_type') === 'students' ? '' : 'display:none;' }}">
                            <div class="orders-target-panel">
                                <div class="orders-target-toolbar">
                                    <input class="orders-student-search" type="search" id="student-search"
                                        placeholder="{{ __('ui.orders.student_search_placeholder') }}">
                                    <div class="orders-selection-count" id="student-selection-count">0
                                        {{ __('ui.orders.selected') }}</div>
                                </div>
                                <div class="orders-target-toolbar">
                                    <button class="orders-link-btn" type="button"
                                        data-select-all="students">{{ __('ui.orders.select_all') }}</button>
                                    <button class="orders-link-btn" type="button"
                                        data-clear-all="students">{{ __('ui.orders.clear_all') }}</button>
                                </div>
                                @if ($students->isEmpty())
                                    <div class="orders-empty-state">{{ __('ui.orders.no_students') }}</div>
                                @else
                                    <div class="orders-student-list" id="orders-student-list">
                                        @foreach ($students->groupBy(fn($student) => $student->classroom?->full_name ?: '-') as $classroomName => $groupStudents)
                                            <div class="orders-student-group" data-student-group data-collapsed="false">
                                                <div class="orders-student-group-title">
                                                    <div class="orders-student-group-title-left">
                                                        <input class="orders-student-group-checkbox" type="checkbox"
                                                            data-student-group-checkbox>
                                                        <span>{{ $classroomName }}</span>
                                                    </div>
                                                    <div class="orders-student-group-actions">
                                                        <button class="orders-student-group-toggle" type="button"
                                                            data-toggle-group>{{ __('ui.orders.collapse') }}</button>
                                                    </div>
                                                </div>

                                                <div class="orders-student-group-body">
                                                    @foreach ($groupStudents as $student)
                                                        @php
                                                            $isStudentSelected =
                                                                collect(old('student_ids', []))->contains(
                                                                    (string) $student->id,
                                                                ) ||
                                                                collect(old('student_ids', []))->contains($student->id);
                                                        @endphp
                                                        <label class="orders-student-row"
                                                            data-student-search="{{ mb_strtolower(trim(($student->full_name ?: '') . ' ' . ($student->iin ?: '') . ' ' . ($student->classroom?->full_name ?: ''))) }}">
                                                            <input type="checkbox" name="student_ids[]"
                                                                value="{{ $student->id }}" data-student-checkbox
                                                                @checked($isStudentSelected)>
                                                            <span class="orders-student-row-body">
                                                                <span>
                                                                    <span
                                                                        class="orders-student-name">{{ $student->full_name ?: '#' . $student->id }}</span>
                                                                    <span class="orders-student-meta">
                                                                        {{ $student->iin ?: '-' }}
                                                                    </span>
                                                                </span>
                                                                <span
                                                                    class="orders-student-class">{{ $student->classroom?->full_name ?: '-' }}</span>
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="orders-form-field">
                            <label for="order_date">{{ __('ui.orders.date') }}</label>
                            <input id="order_date" name="order_date" type="date"
                                value="{{ old('order_date', now()->format('Y-m-d')) }}" required>
                        </div>

                        <div class="orders-form-field">
                            <label for="order_time">{{ __('ui.common.time') }}</label>
                            <input id="order_time" name="order_time" type="time"
                                value="{{ old('order_time', now()->format('H:i')) }}">
                        </div>
                    </div>

                    <div class="orders-form-actions">
                        <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                        <button class="btn secondary" type="button"
                            id="orders-create-cancel">{{ __('ui.common.close') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const translations = {
                selected: @json(__('ui.orders.selected')),
                collapse: @json(__('ui.orders.collapse')),
                expand: @json(__('ui.orders.expand')),
            };
            const modal = document.getElementById('orders-create-modal');
            const openButton = document.getElementById('orders-create-open');
            const closeButton = document.getElementById('orders-create-close');
            const cancelButton = document.getElementById('orders-create-cancel');
            const targetTypeInputs = document.querySelectorAll('input[name="target_type"]');
            const targetSections = document.querySelectorAll('[data-target-section]');
            const classroomCheckboxes = document.querySelectorAll('[data-classroom-checkbox]');
            const studentCheckboxes = document.querySelectorAll('[data-student-checkbox]');
            const classroomSelectionCount = document.getElementById('classroom-selection-count');
            const studentSelectionCount = document.getElementById('student-selection-count');
            const studentSearch = document.getElementById('student-search');
            const studentRows = document.querySelectorAll('[data-student-search]');
            const studentGroups = document.querySelectorAll('[data-student-group]');

            const openModal = () => {
                modal.dataset.open = 'true';
            };

            const closeModal = () => {
                modal.dataset.open = 'false';
            };

            openButton?.addEventListener('click', openModal);
            closeButton?.addEventListener('click', closeModal);
            cancelButton?.addEventListener('click', closeModal);

            const getTargetType = () => {
                const selected = Array.from(targetTypeInputs).find((input) => input.checked);

                return selected ? selected.value : 'all';
            };

            const syncTargetSections = () => {
                const value = getTargetType();

                targetSections.forEach((section) => {
                    section.style.display = section.dataset.targetSection === value ? '' : 'none';
                });
            };

            const syncCounts = () => {
                if (classroomSelectionCount) {
                    classroomSelectionCount.textContent =
                        `${Array.from(classroomCheckboxes).filter((input) => input.checked).length} ${translations.selected}`;
                }

                if (studentSelectionCount) {
                    studentSelectionCount.textContent =
                        `${Array.from(studentCheckboxes).filter((input) => input.checked).length} ${translations.selected}`;
                }

                studentGroups.forEach((group) => {
                    const groupCheckbox = group.querySelector('[data-student-group-checkbox]');
                    const groupStudents = Array.from(group.querySelectorAll('[data-student-checkbox]'))
                        .filter((input) => {
                            const row = input.closest('[data-student-search]');
                            return row && row.style.display !== 'none';
                        });

                    if (!groupCheckbox) {
                        return;
                    }

                    if (groupStudents.length === 0) {
                        groupCheckbox.checked = false;
                        groupCheckbox.indeterminate = false;
                        return;
                    }

                    const checkedCount = groupStudents.filter((input) => input.checked).length;
                    groupCheckbox.checked = checkedCount === groupStudents.length;
                    groupCheckbox.indeterminate = checkedCount > 0 && checkedCount < groupStudents
                        .length;
                });
            };

            targetTypeInputs.forEach((input) => {
                input.addEventListener('change', syncTargetSections);
            });

            classroomCheckboxes.forEach((input) => {
                input.addEventListener('change', syncCounts);
            });

            studentCheckboxes.forEach((input) => {
                input.addEventListener('change', syncCounts);
            });

            studentGroups.forEach((group) => {
                const groupCheckbox = group.querySelector('[data-student-group-checkbox]');
                const groupToggle = group.querySelector('[data-toggle-group]');
                const selectGroupButton = group.querySelector('[data-select-group]');

                groupCheckbox?.addEventListener('change', () => {
                    Array.from(group.querySelectorAll('[data-student-checkbox]')).forEach((
                        input) => {
                        const row = input.closest('[data-student-search]');
                        if (row && row.style.display === 'none') {
                            return;
                        }

                        input.checked = groupCheckbox.checked;
                    });

                    syncCounts();
                });

                selectGroupButton?.addEventListener('click', () => {
                    Array.from(group.querySelectorAll('[data-student-checkbox]')).forEach((
                        input) => {
                        const row = input.closest('[data-student-search]');
                        if (row && row.style.display === 'none') {
                            return;
                        }

                        input.checked = true;
                    });

                    syncCounts();
                });

                groupToggle?.addEventListener('click', () => {
                    const collapsed = group.dataset.collapsed === 'true';
                    group.dataset.collapsed = collapsed ? 'false' : 'true';
                    groupToggle.textContent = collapsed ? translations.collapse : translations
                        .expand;
                });
            });

            document.querySelectorAll('[data-select-all]').forEach((button) => {
                button.addEventListener('click', () => {
                    const type = button.dataset.selectAll;
                    const inputs = type === 'classes' ? classroomCheckboxes : studentCheckboxes;

                    inputs.forEach((input) => {
                        if (type === 'students') {
                            const row = input.closest('[data-student-search]');
                            if (row && row.style.display === 'none') {
                                return;
                            }
                        }

                        input.checked = true;
                    });

                    syncCounts();
                });
            });

            document.querySelectorAll('[data-clear-all]').forEach((button) => {
                button.addEventListener('click', () => {
                    const type = button.dataset.clearAll;
                    const inputs = type === 'classes' ? classroomCheckboxes : studentCheckboxes;

                    inputs.forEach((input) => {
                        input.checked = false;
                    });

                    syncCounts();
                });
            });

            studentSearch?.addEventListener('input', () => {
                const query = studentSearch.value.trim().toLowerCase();

                studentRows.forEach((row) => {
                    const haystack = row.dataset.studentSearch || '';
                    row.style.display = haystack.includes(query) ? '' : 'none';
                });

                studentGroups.forEach((group) => {
                    const hasVisibleRows = Array.from(group.querySelectorAll(
                            '[data-student-search]'))
                        .some((row) => row.style.display !== 'none');

                    group.style.display = hasVisibleRows ? '' : 'none';
                });

                syncCounts();
            });

            syncTargetSections();
            syncCounts();

            modal?.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });
        });
    </script>
@endsection
