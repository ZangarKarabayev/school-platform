<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 34px 42px 28px 42px;
            size: A4 landscape;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #000;
            font-weight: 500;
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
            margin-left: 8px;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
            font-weight: 500;
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

        .col-number {
            width: 34px;
        }

        .col-name {
            width: 560px;
        }

        .col-class {
            width: 34px;
        }

        .col-day {
            width: 26px;
        }

        .col-total-days {
            width: 78px;
        }

        .col-price {
            width: 112px;
        }

        .col-amount {
            width: 112px;
        }

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
        }

        .tfoot-label {
            font-weight: 700;
            text-align: center;
        }

        .student-name {
            text-transform: uppercase;
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
        <thead>
            <tr>
                <th class="col-number">№</th>
                <th class="col-name left">{{ __('ui.dashboard_page.pdf_student_name') }}</th>
                <th class="col-class vertical-wrap"><div class="vertical-text">{{ __('ui.dashboard_page.classroom') }}</div></th>
                @foreach ($days as $day)
                    <th class="col-day vertical-wrap"><div class="vertical-text">{{ $day['title'] }}</div></th>
                @endforeach
                <th class="col-total-days vertical-wrap"><div class="vertical-text">{{ __('ui.dashboard_page.pdf_total_days') }}</div></th>
                <th class="col-price vertical-wrap"><div class="vertical-text">{{ __('ui.dashboard_page.pdf_meal_price') }}</div></th>
                <th class="col-amount vertical-wrap"><div class="vertical-text">{{ __('ui.dashboard_page.pdf_total_amount') }}</div></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['number'] }}</td>
                    <td class="left student-name">{{ $row['full_name'] }}</td>
                    <td>{{ $row['class_label'] }}</td>
                    @foreach ($days as $day)
                        <td>{{ ($row['values'][$day['key']] ?? 0) ? '+' : '' }}</td>
                    @endforeach
                    <td>{{ $row['days_total'] }}</td>
                    <td>{{ number_format((float) $row['meal_price'], 2, '.', '') }}</td>
                    <td>{{ number_format((float) $row['amount_total'], 2, '.', '') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ 3 + count($days) }}" class="tfoot-label">{{ __('ui.dashboard_page.total') }}</td>
                <td>{{ $grandTotal }}</td>
                <td></td>
                <td>{{ number_format((float) $grandAmount, 2, '.', '') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
