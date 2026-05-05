<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.dashboard_page.pdf_verify_title') }}</title>
    <style>
        body {
            margin: 0;
            padding: 32px 18px;
            font-family: system-ui, sans-serif;
            background: #f4f7fb;
            color: #14233b;
        }

        .card {
            max-width: 760px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #d8e1ef;
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(20, 35, 59, 0.08);
            padding: 24px;
        }

        h1 {
            margin: 0 0 18px;
            font-size: 24px;
        }

        .meta {
            display: grid;
            gap: 12px;
        }

        .row {
            padding: 12px 14px;
            border-radius: 12px;
            background: #f8fbff;
        }

        .label {
            display: block;
            margin-bottom: 4px;
            font-size: 13px;
            color: #62738f;
        }

        .value {
            font-size: 16px;
            font-weight: 600;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ __('ui.dashboard_page.pdf_verify_title') }}</h1>

        <div class="meta">
            <div class="row">
                <span class="label">{{ __('ui.dashboard_page.school') }}</span>
                <div class="value">{{ $scopeTitle }}</div>
            </div>
            <div class="row">
                <span class="label">{{ __('ui.dashboard_page.pdf_period_label') }}</span>
                <div class="value">{{ $dateFrom }} - {{ $dateTo }}</div>
            </div>
            <div class="row">
                <span class="label">{{ __('ui.dashboard_page.students') }}</span>
                <div class="value">{{ $studentsCount }}</div>
            </div>
            <div class="row">
                <span class="label">{{ __('ui.dashboard_page.pdf_total_days') }}</span>
                <div class="value">{{ $grandTotal }}</div>
            </div>
            <div class="row">
                <span class="label">{{ __('ui.dashboard_page.pdf_meal_price') }}</span>
                <div class="value">{{ number_format((float) $mealPrice, 2, '.', '') }}</div>
            </div>
            <div class="row">
                <span class="label">{{ __('ui.dashboard_page.pdf_document_hash') }}</span>
                <div class="value">{{ $documentHash }}</div>
            </div>
        </div>
    </div>
</body>
</html>
