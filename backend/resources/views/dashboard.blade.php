@extends('layouts.app')

@php
    $transactions = $charts['transactions'] ?? [];
    $ordersBySchool = $charts['orders_by_school'] ?? collect();
    $ordersByDistrict = $charts['orders_by_district'] ?? collect();
    $classGroups = $charts['class_groups'] ?? [];
    $benefits = $charts['benefits'] ?? [];
    $coverage = $charts['coverage'] ?? [];
    $ordersTableDays = $ordersTable['days'] ?? [];
    $ordersTableRows = $ordersTable['rows'] ?? [];
    $ordersTableColumnTotals = $ordersTable['column_totals'] ?? [];
    $ordersTableGrandTotal = (int) ($ordersTable['grand_total'] ?? 0);
    $totalStudents = (int) ($coverage[0]['value'] ?? 0);
    $voucherStudents = (int) ($benefits[1]['value'] ?? 0);
    $susnStudents = (int) ($benefits[0]['value'] ?? 0);
    $otherStudents = max($totalStudents - $voucherStudents - $susnStudents, 0);

    $transactionsTotal = max(collect($transactions)->sum('value'), 1);
    $processedTransactionsTotal = (int) collect($transactions)->sum('value');
    $transactionOffset = 0;
    $transactionGradient = collect($transactions)
        ->map(function ($item) use (&$transactionOffset, $transactionsTotal) {
            $start = round(($transactionOffset / $transactionsTotal) * 360, 2);
            $transactionOffset += $item['value'];
            $end = round(($transactionOffset / $transactionsTotal) * 360, 2);

            return $item['color'] . ' ' . $start . 'deg ' . $end . 'deg';
        })
        ->implode(', ');

    $studentBenefitItems = [
        ['label' => __('ui.dashboard_page.susn'), 'value' => $susnStudents, 'color' => '#f59f00'],
        ['label' => __('ui.dashboard_page.voucher'), 'value' => $voucherStudents, 'color' => '#3b82f6'],
        ['label' => __('ui.dashboard_page.other'), 'value' => $otherStudents, 'color' => '#94a3b8'],
    ];
    $studentBenefitsTotal = max(collect($studentBenefitItems)->sum('value'), 1);
    $studentBenefitOffset = 0;
    $studentBenefitGradient = collect($studentBenefitItems)
        ->map(function ($item) use (&$studentBenefitOffset, $studentBenefitsTotal) {
            $start = round(($studentBenefitOffset / $studentBenefitsTotal) * 360, 2);
            $studentBenefitOffset += $item['value'];
            $end = round(($studentBenefitOffset / $studentBenefitsTotal) * 360, 2);

            return $item['color'] . ' ' . $start . 'deg ' . $end . 'deg';
        })
        ->implode(', ');

    $classGroupItems = collect($classGroups)
        ->values()
        ->map(function ($item, $index) {
            $colors = ['#2876dd', '#f59f00', '#94a3b8'];

            return [
                'label' => $item['label'] ?? '',
                'value' => (int) ($item['value'] ?? 0),
                'color' => $colors[$index] ?? '#94a3b8',
            ];
        })
        ->all();
    $classGroupsTotal = max(collect($classGroupItems)->sum('value'), 1);
    $classGroupOffset = 0;
    $classGroupGradient = collect($classGroupItems)
        ->map(function ($item) use (&$classGroupOffset, $classGroupsTotal) {
            $start = round(($classGroupOffset / $classGroupsTotal) * 360, 2);
            $classGroupOffset += $item['value'];
            $end = round(($classGroupOffset / $classGroupsTotal) * 360, 2);

            return $item['color'] . ' ' . $start . 'deg ' . $end . 'deg';
        })
        ->implode(', ');
@endphp

@section('content')
    <style>
        .dashboard-page {
            padding: 24px 0;
            display: grid;
            gap: 18px;
            min-width: 0;
        }

        .dashboard-page section {
            min-width: 0;
        }

        .dashboard-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
            min-width: 0;
            max-width: 100%;
        }

        .dashboard-filters {
            padding: 24px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .dashboard-field {
            display: grid;
            gap: 6px;
        }

        .dashboard-field label {
            font-size: 13px;
            font-weight: 700;
            color: #4e607d;
        }

        .dashboard-field input,
        .dashboard-field select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d1d8e5;
            border-radius: 12px;
            background: #fff;
            color: #16253d;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            min-width: 0;
        }

        .dashboard-stat,
        .dashboard-chart-card {
            padding: 20px;
        }

        .dashboard-stat-label {
            color: #71829a;
            font-size: 14px;
        }

        .dashboard-stat-value {
            margin-top: 8px;
            font-size: 32px;
            font-weight: 700;
            color: #1d3151;
            line-height: 1;
        }

        .dashboard-charts {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            min-width: 0;
        }

        .dashboard-stats>*,
        .dashboard-charts>* {
            min-width: 0;
        }

        .dashboard-chart-title {
            margin: 0 0 16px;
            font-size: 18px;
            font-weight: 700;
            color: #1d3151;
        }

        .dashboard-donut-wrap {
            display: grid;
            grid-template-columns: 180px minmax(0, 1fr);
            gap: 20px;
            align-items: center;
        }

        .dashboard-donut {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            position: relative;
            background: #eef3fb;
        }

        .dashboard-donut::after {
            content: '';
            position: absolute;
            inset: 24px;
            border-radius: 50%;
            background: #fff;
        }

        .dashboard-legend {
            display: grid;
            gap: 10px;
        }

        .dashboard-legend-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 14px;
            color: #234067;
        }

        .dashboard-legend-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dashboard-legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .dashboard-bars-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .dashboard-bars {
            display: grid;
            gap: 12px;
        }

        .dashboard-bar-row {
            display: grid;
            gap: 6px;
        }

        .dashboard-bar-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 14px;
            color: #234067;
        }

        .dashboard-bar-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dashboard-bar-track {
            height: 10px;
            border-radius: 999px;
            background: #edf2fa;
            overflow: hidden;
        }

        .dashboard-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #2876dd 0%, #6ba6ff 100%);
        }

        .dashboard-empty {
            padding: 24px;
            color: #71829a;
        }

        .dashboard-table-card {
            overflow: hidden;
        }

        .dashboard-table-header {
            padding: 20px 24px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .dashboard-table-title {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #1d3151;
        }

        .dashboard-table-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dashboard-table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            padding: 20px 24px 24px;
        }

        .dashboard-table {
            width: max-content;
            min-width: 100%;
            border-collapse: collapse;
        }

        .dashboard-table col.dashboard-table-col-number {
            width: 38px;
        }

        .dashboard-table col.dashboard-table-col-name {
            width: 260px;
        }

        .dashboard-table col.dashboard-table-col-classroom {
            width: 72px;
        }

        .dashboard-table col.dashboard-table-col-day {
            width: 42px;
        }

        .dashboard-table col.dashboard-table-col-total {
            width: 58px;
        }

        .dashboard-table th,
        .dashboard-table td {
            border: 1px solid #dbe4f0;
            padding: 8px 8px;
            font-size: 13px;
            text-align: center;
            color: #1d3151;
            background: #fff;
        }

        .dashboard-table thead th {
            background: #edf4ff;
            font-weight: 700;
        }

        .dashboard-table th:nth-child(2),
        .dashboard-table td:nth-child(2),
        .dashboard-table tfoot th:nth-child(2) {
            text-align: left;
        }

        .dashboard-table th:nth-child(3),
        .dashboard-table td:nth-child(3),
        .dashboard-table tfoot th:nth-child(3) {
            text-align: left;
            white-space: nowrap;
        }

        .dashboard-table tfoot th,
        .dashboard-table tfoot td {
            background: #f6f9ff;
            font-weight: 700;
        }

        .dashboard-table-day {
            width: 42px;
            white-space: nowrap;
            text-align: center !important;
            padding-left: 0;
            padding-right: 0;
        }

        @media (max-width: 1200px) {
            .dashboard-filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-charts,
            .dashboard-bars-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {

            .dashboard-filters,
            .dashboard-stats,
            .dashboard-donut-wrap {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="dashboard-page">
        <section>
            <div class="dashboard-card" style="padding:24px;">
                <div class="muted" style="margin-bottom:8px;">{{ __('ui.common.home') }}</div>
                <h1 style="margin:0;font-size:28px;line-height:1.2;">{{ __('ui.menu.dashboard') }}</h1>
            </div>
        </section>

        <section>
            <form class="dashboard-card dashboard-filters" method="get" action="{{ route('dashboard') }}">
                <div class="dashboard-field">
                    <label for="date_from">{{ __('ui.dashboard_page.date_from') }}</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}">
                </div>

                <div class="dashboard-field">
                    <label for="date_to">{{ __('ui.dashboard_page.date_to') }}</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}">
                </div>

                @if ($scopeConfig['mode'] === 'district')
                    <div class="dashboard-field">
                        <label for="scope_kind">{{ __('ui.dashboard_page.scope') }}</label>
                        <select id="scope_kind" name="scope_kind">
                            <option value="district" @selected($filters['scope_kind'] === 'district')>{{ __('ui.dashboard_page.district') }}</option>
                            <option value="school" @selected($filters['scope_kind'] === 'school')>{{ __('ui.dashboard_page.school') }}</option>
                        </select>
                    </div>

                    <div class="dashboard-field">
                        <label for="school_id">{{ __('ui.dashboard_page.school') }}</label>
                        <select id="school_id" name="school_id">
                            <option value="">{{ __('ui.common.all') }}</option>
                            @foreach ($scopeConfig['schools'] as $school)
                                <option value="{{ $school->id }}" @selected((int) $filters['school_id'] === (int) $school->id)>{{ $school->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($scopeConfig['mode'] === 'region')
                    <div class="dashboard-field">
                        <label for="scope_kind">{{ __('ui.dashboard_page.scope') }}</label>
                        <select id="scope_kind" name="scope_kind">
                            <option value="region" @selected($filters['scope_kind'] === 'region')>{{ __('ui.dashboard_page.whole_region') }}</option>
                            <option value="district" @selected($filters['scope_kind'] === 'district')>{{ __('ui.dashboard_page.district') }}</option>
                        </select>
                    </div>

                    <div class="dashboard-field">
                        <label for="district_id">{{ __('ui.dashboard_page.district') }}</label>
                        <select id="district_id" name="district_id">
                            <option value="">{{ __('ui.common.all') }}</option>
                            @foreach ($scopeConfig['districts'] as $district)
                                <option value="{{ $district->id }}" @selected((int) $filters['district_id'] === (int) $district->id)>
                                    {{ $district->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="btn" type="submit">{{ __('ui.common.filter') }}</button>
                    <a class="btn secondary" href="{{ route('dashboard') }}">{{ __('ui.common.reset') }}</a>
                </div>
            </form>
        </section>

        <section class="dashboard-stats">
            <div class="dashboard-card dashboard-stat">
                <div class="dashboard-stat-label">{{ __('ui.dashboard_page.orders_total') }}</div>
                <div class="dashboard-stat-value">{{ $stats['orders_count'] }}</div>
            </div>

            <div class="dashboard-card dashboard-stat">
                <div class="dashboard-stat-label">{{ __('ui.dashboard_page.transactions_success') }}</div>
                <div class="dashboard-stat-value">{{ $stats['success_count'] }}</div>
            </div>

            <div class="dashboard-card dashboard-stat">
                <div class="dashboard-stat-label">{{ __('ui.dashboard_page.transactions_failed') }}</div>
                <div class="dashboard-stat-value">{{ $stats['failed_count'] }}</div>
            </div>

            <div class="dashboard-card dashboard-stat">
                <div class="dashboard-stat-label">{{ __('ui.dashboard_page.transaction_error') }}</div>
                <div class="dashboard-stat-value">{{ $stats['error_count'] }}</div>
            </div>
        </section>

        <section class="dashboard-charts">
            <div class="dashboard-card dashboard-chart-card">
                <h2 class="dashboard-chart-title">{{ __('ui.dashboard_page.transactions') }}</h2>
                <div class="dashboard-donut-wrap">
                    <div class="dashboard-donut"
                        style="background: conic-gradient({{ $transactionGradient ?: '#eef3fb 0deg 360deg' }});"></div>
                    <div class="dashboard-legend">
                        <div class="dashboard-legend-item">
                            <div class="dashboard-legend-left">
                                <span>{{ __('ui.dashboard_page.transactions') }}</span>
                            </div>
                            <strong>{{ $processedTransactionsTotal }}</strong>
                        </div>
                        @foreach ($transactions as $transaction)
                            <div class="dashboard-legend-item">
                                <div class="dashboard-legend-left">
                                    <span class="dashboard-legend-dot"
                                        style="background: {{ $transaction['color'] }};"></span>
                                    <span>{{ $transaction['label'] }}</span>
                                </div>
                                <strong>{{ $transaction['value'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="dashboard-card dashboard-chart-card">
                <h2 class="dashboard-chart-title">{{ __('ui.dashboard_page.orders') }}</h2>
                <div class="dashboard-donut-wrap">
                    <div class="dashboard-donut"
                        style="background: conic-gradient({{ $classGroupGradient ?: '#eef3fb 0deg 360deg' }});"></div>
                    <div class="dashboard-legend">
                        <div class="dashboard-legend-item">
                            <div class="dashboard-legend-left">
                                <span>{{ __('ui.dashboard_page.orders_total') }}</span>
                            </div>
                            <strong>{{ $stats['orders_count'] }}</strong>
                        </div>
                        @foreach ($classGroupItems as $item)
                            <div class="dashboard-legend-item">
                                <div class="dashboard-legend-left">
                                    <span class="dashboard-legend-dot" style="background: {{ $item['color'] }};"></span>
                                    <span>{{ $item['label'] }}</span>
                                </div>
                                <strong>{{ $item['value'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="dashboard-card dashboard-chart-card">
                <h2 class="dashboard-chart-title">{{ __('ui.dashboard_page.students') }}</h2>
                <div class="dashboard-donut-wrap">
                    <div class="dashboard-donut"
                        style="background: conic-gradient({{ $studentBenefitGradient ?: '#eef3fb 0deg 360deg' }});"></div>
                    <div class="dashboard-legend">
                        <div class="dashboard-legend-item">
                            <div class="dashboard-legend-left">
                                <span>{{ __('ui.dashboard_page.students_total') }}</span>
                            </div>
                            <strong>{{ $totalStudents }}</strong>
                        </div>
                        @foreach ($studentBenefitItems as $item)
                            <div class="dashboard-legend-item">
                                <div class="dashboard-legend-left">
                                    <span class="dashboard-legend-dot" style="background: {{ $item['color'] }};"></span>
                                    <span>{{ $item['label'] }}</span>
                                </div>
                                <strong>{{ $item['value'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        @if ($showOrdersTable)
            <section>
                <div class="dashboard-card dashboard-table-card">
                    <div class="dashboard-table-header">
                        <h2 class="dashboard-table-title">{{ __('ui.dashboard_page.orders_table') }}</h2>
                        <div class="dashboard-table-actions">
                            <a class="btn secondary" href="{{ route('dashboard.export-orders-table', request()->query()) }}">{{ __('ui.dashboard_page.download_excel') }}</a>
                        </div>
                    </div>

                    @if (count($ordersTableRows) > 0)
                        <div class="dashboard-table-wrap">
                            <table class="dashboard-table">
                                <colgroup>
                                    <col class="dashboard-table-col-number">
                                    <col class="dashboard-table-col-name">
                                    <col class="dashboard-table-col-classroom">
                                    @foreach ($ordersTableDays as $day)
                                        <col class="dashboard-table-col-day">
                                    @endforeach
                                    <col class="dashboard-table-col-total">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>№</th>
                                        <th>ФИО</th>
                                        <th>{{ __('ui.dashboard_page.classroom') }}</th>
                                        @foreach ($ordersTableDays as $day)
                                            <th class="dashboard-table-day" title="{{ $day['title'] }}">{{ $day['label'] }}</th>
                                        @endforeach
                                        <th>{{ __('ui.dashboard_page.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ordersTableRows as $row)
                                        <tr>
                                            <td>{{ $row['number'] }}</td>
                                            <td>{{ $row['full_name'] }}</td>
                                            <td>{{ $row['classroom_name'] }}</td>
                                            @foreach ($ordersTableDays as $day)
                                                <td class="dashboard-table-day">{{ $row['values'][$day['key']] ?: '' }}</td>
                                            @endforeach
                                            <td><strong>{{ $row['total'] }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3">{{ __('ui.dashboard_page.total') }}</th>
                                        @foreach ($ordersTableDays as $day)
                                            <td class="dashboard-table-day">{{ $ordersTableColumnTotals[$day['key']] ?? 0 }}</td>
                                        @endforeach
                                        <td>{{ $ordersTableGrandTotal }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="dashboard-empty">{{ __('ui.dashboard_page.no_data_selected_period') }}</div>
                    @endif
                </div>
            </section>
        @endif
    </div>
@endsection
