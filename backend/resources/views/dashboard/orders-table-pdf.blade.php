<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @php
        $dayCount = count($days);
        $isDenseTable = $dayCount > 10;
    @endphp
    <style>
        @page {
            margin: 34px 42px 28px 42px;
            size: A4 landscape;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #000;
            font-weight: 400;
        }

        .header {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 28px;
            padding-left: 10px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.35;
            vertical-align: top;
            padding-right: 0;
            max-width: 780px;
        }

        .header-period {
            margin-top: 30px;
            font-size: 14px;
            font-weight: 700;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-left: 0;
        }

        .report-table th,
        .report-table td {
            border: 1.5px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
            font-weight: 400;
        }

        .compact-cell {
            padding-left: 1px !important;
            padding-right: 1px !important;
            font-size: {{ $isDenseTable ? '8px' : '10px' }};
        }

        .report-table thead th {
            text-align: center;
            font-weight: 400;
        }

        .report-table tbody td,
        .report-table tfoot td {
            text-align: center;
        }

        .report-table .left {
            text-align: left;
        }

        .col-number { width: {{ $isDenseTable ? '18px' : '24px' }}; }
        .col-name { width: {{ $isDenseTable ? '24%' : '32%' }}; }
        .col-class { width: {{ $isDenseTable ? '12px' : '16px' }}; }
        .col-day { width: {{ $isDenseTable ? '8px' : '12px' }}; }
        .col-total-days { width: {{ $isDenseTable ? '36px' : '44px' }}; }
        .col-price { width: {{ $isDenseTable ? '84px' : '82px' }}; }
        .col-amount { width: {{ $isDenseTable ? '92px' : '88px' }}; }

        .vertical-wrap {
            position: relative;
            height: 132px;
            padding: 0;
            overflow: hidden;
        }

        .vertical-text {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%) rotate(-90deg);
            transform-origin: center center;
            white-space: nowrap;
            line-height: 1;
            text-align: center;
            font-size: {{ $isDenseTable ? '8px' : '11px' }};
        }

        .tfoot-label {
            font-weight: 700;
            text-align: center;
        }

        .student-name {
            text-transform: uppercase;
            font-weight: 400;
            font-size: {{ $isDenseTable ? '9px' : '11px' }};
            white-space: nowrap;
            word-break: keep-all;
        }

        .money-cell {
            white-space: nowrap;
            font-size: {{ $isDenseTable ? '9px' : '11px' }};
            padding-left: 2px !important;
            padding-right: 2px !important;
        }

        .count-cell {
            white-space: nowrap;
            font-size: {{ $isDenseTable ? '9px' : '11px' }};
        }

        .report-table thead th,
        .report-table tfoot td,
        .report-table tfoot th,
        .report-table tbody td:last-child,
        .report-table tbody td:nth-last-child(2),
        .report-table tbody td:nth-last-child(3) {
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="header-title">
                        {{ __('ui.dashboard_page.pdf_title', ['scope' => $scopeTitle]) }}
                    </div>
                    <div class="header-period">
                        {{ __('ui.dashboard_page.pdf_period', ['date_from' => \Illuminate\Support\Carbon::parse($dateFrom)->format('d.m.Y'), 'date_to' => \Illuminate\Support\Carbon::parse($dateTo)->format('d.m.Y')]) }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="report-table">
        <colgroup>
            <col class="col-number">
            <col class="col-name">
            <col class="col-class">
            @foreach ($days as $day)
                <col class="col-day">
            @endforeach
            <col class="col-total-days">
            <col class="col-price">
            <col class="col-amount">
        </colgroup>
        <thead>
            <tr>
                <th class="col-number">№</th>
                <th class="col-name left">{{ __('ui.dashboard_page.pdf_student_name') }}</th>
                <th class="col-class vertical-wrap compact-cell"><div class="vertical-text">{{ __('ui.dashboard_page.classroom') }}</div></th>
                @foreach ($days as $day)
                    <th class="col-day vertical-wrap compact-cell"><div class="vertical-text">{{ $day['title'] }}</div></th>
                @endforeach
                <th class="col-total-days vertical-wrap compact-cell"><div class="vertical-text">{{ __('ui.dashboard_page.pdf_total_days') }}</div></th>
                <th class="col-price vertical-wrap compact-cell"><div class="vertical-text">{{ __('ui.dashboard_page.pdf_meal_price') }}</div></th>
                <th class="col-amount vertical-wrap compact-cell"><div class="vertical-text">{{ __('ui.dashboard_page.pdf_total_amount') }}</div></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['number'] }}</td>
                    <td class="left student-name">{{ $row['full_name'] }}</td>
                    <td class="compact-cell">{{ $row['class_label'] }}</td>
                    @foreach ($days as $day)
                        <td class="compact-cell">{{ ($row['values'][$day['key']] ?? 0) ? '+' : '' }}</td>
                    @endforeach
                    <td class="compact-cell count-cell">{{ $row['days_total'] }}</td>
                    <td class="money-cell">{{ number_format((float) $row['meal_price'], 2, '.', '') }}</td>
                    <td class="money-cell">{{ number_format((float) $row['amount_total'], 2, '.', '') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ 3 + count($days) }}" class="tfoot-label">{{ __('ui.dashboard_page.total') }}</td>
                <td class="count-cell">{{ $grandTotal }}</td>
                <td></td>
                <td class="money-cell">{{ number_format((float) $grandAmount, 2, '.', '') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
