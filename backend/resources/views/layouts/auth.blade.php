<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('ui.app_name') }}</title>
    <style>
        :root {
            --bg: #e7edf6;
            --surface: #ffffff;
            --surface-soft: #f5f8fd;
            --line: #d4ddea;
            --text: #17253c;
            --muted: #6e7f97;
            --primary: #2876dd;
            --primary-dark: #215fb3;
            --primary-soft: #edf4ff;
            --success: #1d9b62;
            --success-soft: #edf8f2;
            --danger: #d73d56;
            --danger-soft: #fff1f3;
            --shadow: 0 20px 44px rgba(20, 40, 77, 0.10);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(40, 118, 221, 0.10), transparent 26%),
                linear-gradient(180deg, #dfe7f3 0%, var(--bg) 100%);
            color: var(--text);
        }
        a { color: inherit; text-decoration: none; }
        .shell { min-height: 100vh; }
        .content { max-width: 1120px; margin: 0 auto; padding: 24px 22px 40px; }
        .topbar { display: flex; justify-content: flex-end; margin-bottom: 16px; }
        .lang-switcher { display: inline-flex; gap: 8px; padding: 6px; border: 1px solid var(--line); background: var(--surface); border-radius: 999px; box-shadow: var(--shadow); }
        .lang-switcher a { padding: 6px 10px; border-radius: 999px; color: var(--muted); font-size: 13px; font-weight: 700; }
        .lang-switcher a.active { background: var(--primary); color: #fff; }
        .page-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .panel { background: var(--surface); border: 1px solid var(--line); box-shadow: var(--shadow); border-radius: 10px; overflow: hidden; }
        .panel-header { padding: 16px 18px; background: #f5f8fd; border-bottom: 1px solid var(--line); }
        .panel-header h2, .panel-header h3 { margin: 0; color: #266ccc; font-size: 18px; font-weight: 600; }
        .panel-body { padding: 18px; }
        .panel p { margin: 0 0 16px; color: #4f617d; line-height: 1.55; }
        .field { margin-bottom: 14px; }
        .field label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 700; }
        .field input { width: 100%; height: 44px; padding: 0 14px; border: 1px solid #c7d4e6; border-radius: 14px; background: #fff; font-size: 15px; color: var(--text); }
        .field input[readonly] { background: #f5f8fd; color: #5d6f88; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 0 18px; border: 0; border-radius: 12px; background: var(--primary); color: #fff; font-size: 15px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 22px rgba(40, 118, 221, 0.24); }
        .btn.secondary { background: #dde7f4; color: #234067; box-shadow: none; }
        .btn:disabled { background: #b7c6dc; color: #f5f8fd; box-shadow: none; cursor: not-allowed; }
        .note, .error, .success { margin-bottom: 16px; padding: 12px 14px; border-radius: 12px; font-size: 14px; }
        .note { background: var(--primary-soft); color: #22518f; }
        .success { background: var(--success-soft); color: var(--success); }
        .error { background: var(--danger-soft); color: var(--danger); }
        .mini { margin-top: 10px; color: var(--muted); font-size: 12px; }
        .choice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .choice-card { display: block; padding: 18px; border-radius: 16px; background: var(--surface-soft); border: 1px solid var(--line); }
        .choice-card strong { display: block; margin-bottom: 8px; font-size: 16px; }
        .choice-card span { color: var(--muted); font-size: 14px; line-height: 1.45; }
        @media (max-width: 1180px) { .page-grid { grid-template-columns: 1fr; } }
        @media (max-width: 640px) { .content { padding: 14px; } .choice-grid { grid-template-columns: 1fr; } }
    </style>
</head>

<body>
    <div class="shell">
        <main class="content">
            <div class="topbar">
                <nav class="lang-switcher" aria-label="{{ __('ui.language') }}">
                    @foreach (['ru', 'kk'] as $locale)
                        <a href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}" @class(['active' => app()->getLocale() === $locale])>{{ __('ui.languages.'.$locale) }}</a>
                    @endforeach
                </nav>
            </div>
            @yield('content')
        </main>
    </div>
    @include('auth.partials.phone-mask-script')
</body>

</html>
