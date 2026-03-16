<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('ui.common.login') }}</title>
    <style>
        :root {
            --bg: #e7edf6;
            --surface: #ffffff;
            --surface-soft: #f5f8fd;
            --line: #d4ddea;
            --text: #17253c;
            --muted: #6e7f97;
            --primary: #2876dd;
            --primary-soft: #edf4ff;
            --success: #1d9b62;
            --success-soft: #edf8f2;
            --danger: #d73d56;
            --danger-soft: #fff1f3;
            --shadow: 0 20px 44px rgba(20, 40, 77, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(40, 118, 221, 0.10), transparent 26%),
                linear-gradient(180deg, #dfe7f3 0%, var(--bg) 100%);
            color: var(--text);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .wrap {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: min(720px, 100%);
            background: var(--surface);
            border: 1px solid var(--line);
            box-shadow: var(--shadow);
            border-radius: 14px;
            overflow: hidden;
        }

        .hero {
            padding: 18px 22px;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .hero h1 {
            margin: 0;
            font-size: 22px;
        }

        .body {
            padding: 18px;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
        }

        .panel-header {
            padding: 14px 16px;
            background: var(--surface-soft);
            border-bottom: 1px solid var(--line);
        }

        .panel-header h2,
        .panel-header h3 {
            margin: 0;
            color: #266ccc;
            font-size: 17px;
            font-weight: 600;
        }

        .panel-body {
            padding: 16px;
        }

        .panel p {
            margin: 0 0 14px;
            color: #4f617d;
            line-height: 1.45;
        }

        .field {
            margin-bottom: 12px;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 700;
        }

        .field input { width: 100%; height: 42px; padding: 0 12px; border: 1px solid #c7d4e6; border-radius: 12px; background: #fff; font-size: 14px; color: var(--text); }
        .password-field { position: relative; }
        .password-field input { position: relative; z-index: 1; padding-right: 52px; }
        .password-toggle { position: absolute; top: 50%; right: 8px; z-index: 2; width: 36px; height: 36px; padding: 0; margin: 0; transform: translateY(-50%); border: 0; border-radius: 10px; background: rgba(255, 255, 255, 0.96); color: #5d6f88; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; appearance: none; -webkit-appearance: none; }
        .password-toggle:hover { background: #edf4ff; color: #22518f; box-shadow: 0 0 0 1px rgba(40, 118, 221, 0.12); }
        .password-toggle svg { width: 18px; height: 18px; display: block; }
        .password-field[data-visible="true"] .icon-eye { display: none; }
        .password-field[data-visible="false"] .icon-eye-off { display: none; }

        .field input[readonly] {
            background: #f5f8fd;
            color: #5d6f88;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 16px;
            border: 0;
            border-radius: 10px;
            background: var(--primary);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn.secondary {
            background: #dde7f4;
            color: #234067;
        }

        .btn:disabled {
            background: #b7c6dc;
            color: #f5f8fd;
            box-shadow: none;
            cursor: not-allowed;
        }

        .note,
        .error,
        .success {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 14px;
        }

        .note {
            background: var(--primary-soft);
            color: #22518f;
        }

        .success {
            background: var(--success-soft);
            color: var(--success);
        }

        .error {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .mini {
            margin-top: 10px;
            color: var(--muted);
            font-size: 12px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .lang-switcher {
            display: inline-flex;
            gap: 8px;
            padding: 6px;
            border: 1px solid rgba(255, 255, 255, 0.35);
            background: rgba(255, 255, 255, 0.12);
            border-radius: 999px;
        }

        .lang-switcher a {
            padding: 6px 10px;
            border-radius: 999px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .lang-switcher a.active {
            background: #fff;
            color: var(--primary);
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <div class="hero">
                <h1>{{ __('ui.app_name') }}</h1>
                @include('partials.lang-switcher')
            </div>
            <div class="body">
                @yield('content')
            </div>
        </div>
    </div>
    @include('auth.partials.phone-mask-script')
    @include('auth.partials.password-toggle-script')
</body>

</html>
