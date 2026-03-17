@extends('layouts.app')

@php
    $transactions = $charts['transactions'] ?? [];
    $ordersBySchool = $charts['orders_by_school'] ?? collect();
    $ordersByDistrict = $charts['orders_by_district'] ?? collect();
    $classGroups = $charts['class_groups'] ?? [];
    $benefits = $charts['benefits'] ?? [];
    $coverage = $charts['coverage'] ?? [];
    $totalStudents = (int) ($coverage[0]['value'] ?? 0);
    $voucherStudents = (int) ($benefits[1]['value'] ?? 0);
    $susnStudents = (int) ($benefits[0]['value'] ?? 0);
    $otherStudents = max($totalStudents - $voucherStudents - $susnStudents, 0);

    $transactionsTotal = max(collect($transactions)->sum('value'), 1);
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
        ['label' => 'СУСН', 'value' => $susnStudents, 'color' => '#f59f00'],
        ['label' => 'Voucher', 'value' => $voucherStudents, 'color' => '#3b82f6'],
        ['label' => 'Другие', 'value' => $otherStudents, 'color' => '#94a3b8'],
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
        }

        .dashboard-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
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
                    <label for="date_from">Дата от</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}">
                </div>

                <div class="dashboard-field">
                    <label for="date_to">Дата до</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}">
                </div>

                @if ($scopeConfig['mode'] === 'district')
                    <div class="dashboard-field">
                        <label for="scope_kind">Охват</label>
                        <select id="scope_kind" name="scope_kind">
                            <option value="district" @selected($filters['scope_kind'] === 'district')>Весь район</option>
                            <option value="school" @selected($filters['scope_kind'] === 'school')>Школа</option>
                        </select>
                    </div>

                    <div class="dashboard-field">
                        <label for="school_id">Школа</label>
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
                        <label for="scope_kind">Охват</label>
                        <select id="scope_kind" name="scope_kind">
                            <option value="region" @selected($filters['scope_kind'] === 'region')>Целая область</option>
                            <option value="district" @selected($filters['scope_kind'] === 'district')>Район</option>
                        </select>
                    </div>

                    <div class="dashboard-field">
                        <label for="district_id">Район</label>
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
                <div class="dashboard-stat-label">Всего заказов</div>
                <div class="dashboard-stat-value">{{ $stats['orders_count'] }}</div>
            </div>

            <div class="dashboard-card dashboard-stat">
                <div class="dashboard-stat-label">Успешные транзакции</div>
                <div class="dashboard-stat-value">{{ $stats['success_count'] }}</div>
            </div>

            <div class="dashboard-card dashboard-stat">
                <div class="dashboard-stat-label">Неуспешные транзакции</div>
                <div class="dashboard-stat-value">{{ $stats['failed_count'] }}</div>
            </div>

            <div class="dashboard-card dashboard-stat">
                <div class="dashboard-stat-label">Ошибки transaction_error</div>
                <div class="dashboard-stat-value">{{ $stats['error_count'] }}</div>
            </div>
        </section>

        <section class="dashboard-charts">
            <div class="dashboard-card dashboard-chart-card">
                <h2 class="dashboard-chart-title">Ученики</h2>
                <div class="dashboard-donut-wrap">
                    <div class="dashboard-donut"
                        style="background: conic-gradient({{ $studentBenefitGradient ?: '#eef3fb 0deg 360deg' }});"></div>
                    <div class="dashboard-legend">
                        <div class="dashboard-legend-item">
                            <div class="dashboard-legend-left">
                                <span>Общее кол-во учеников</span>
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

            <div class="dashboard-card dashboard-chart-card">
                <h2 class="dashboard-chart-title">Заказы</h2>
                <div class="dashboard-donut-wrap">
                    <div class="dashboard-donut"
                        style="background: conic-gradient({{ $classGroupGradient ?: '#eef3fb 0deg 360deg' }});"></div>
                    <div class="dashboard-legend">
                        <div class="dashboard-legend-item">
                            <div class="dashboard-legend-left">
                                <span>Всего заказов</span>
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
                <h2 class="dashboard-chart-title">Транзакции</h2>
                <div class="dashboard-donut-wrap">
                    <div class="dashboard-donut"
                        style="background: conic-gradient({{ $transactionGradient ?: '#eef3fb 0deg 360deg' }});"></div>
                    <div class="dashboard-legend">
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
        </section>
    </div>
@endsection
