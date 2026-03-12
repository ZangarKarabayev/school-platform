<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.dashboard.title') }}</title>
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: #dfe5ef;
            color: #16253d;
        }

        .topbar {
            min-height: 68px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 0 24px;
            background: #2876dd;
            color: #fff;
        }

        .topbar-left,
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .lang-switcher {
            display: inline-flex;
            gap: 8px;
            padding: 6px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            background: rgba(255, 255, 255, 0.12);
            border-radius: 999px;
        }

        .lang-switcher a {
            padding: 6px 10px;
            border-radius: 999px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .lang-switcher a.active {
            background: #fff;
            color: #2876dd;
        }

        .workspace {
            display: grid;
            grid-template-columns: 250px minmax(0, 1fr);
            min-height: calc(100vh - 68px);
        }

        .sidebar {
            background: #fff;
            border-right: 1px solid #d1d8e5;
        }

        .profile {
            padding: 18px;
            border-bottom: 1px solid #d1d8e5;
            background: #f6f8fc;
        }

        .avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #173d74 0%, #3f91ff 100%);
            color: #fff;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .nav a {
            display: flex;
            padding: 14px 22px;
            color: #2a3953;
            text-decoration: none;
            border-left: 3px solid transparent;
        }

        .nav a.active,
        .nav a:hover {
            background: #2876dd;
            color: #fff;
            border-left-color: #b9d8ff;
        }

        .content {
            padding: 18px 22px 22px;
        }

        .hero {
            margin: -18px -22px 16px;
            padding: 16px 22px 24px;
            background: #2876dd;
            color: #fff;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 14px;
        }

        .stat {
            background: rgba(27, 102, 202, 0.74);
            border: 1px solid rgba(0, 0, 0, 0.08);
            padding: 16px;
        }

        .stat .v {
            margin-top: 14px;
            font-size: 20px;
            font-weight: 700;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .card {
            background: #fff;
            border: 1px solid #d1d8e5;
            box-shadow: 0 16px 38px rgba(23, 35, 61, 0.08);
        }

        .card h2 {
            margin: 0;
            padding: 16px 18px;
            background: #f3f6fb;
            color: #246bcb;
            font-size: 18px;
        }

        .card .body {
            padding: 18px;
        }

        .pill {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #edf4ff;
            color: #246bcb;
            margin: 4px 6px 0 0;
        }

        .btn {
            border: 0;
            border-radius: 12px;
            padding: 10px 14px;
            background: #2876dd;
            color: #fff;
            cursor: pointer;
            font-weight: 700;
        }

        .muted {
            color: #71829a;
        }

        @media (max-width: 1000px) {
            .stats,
            .grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 820px) {
            .topbar {
                padding: 14px;
                align-items: flex-start;
                flex-direction: column;
            }

            .topbar-left,
            .topbar-right {
                width: 100%;
                justify-content: space-between;
            }

            .workspace {
                grid-template-columns: 1fr;
            }

            .grid,
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <header class="topbar">
        <div class="topbar-left">
            <div style="font-weight:700;">{{ __('ui.app_name') }}</div>
            <nav class="lang-switcher" aria-label="{{ __('ui.language') }}">
                @foreach (['ru', 'kk'] as $locale)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}" @class(['active' => app()->getLocale() === $locale])>{{ __('ui.languages.'.$locale) }}</a>
                @endforeach
            </nav>
        </div>
        <div class="topbar-right">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn" type="submit">{{ __('ui.common.logout') }}</button>
            </form>
        </div>
    </header>

    <div class="workspace">
        <aside class="sidebar">
            <div class="profile">
                <div class="avatar">{{ mb_substr($user->first_name ?? 'U', 0, 1) }}{{ mb_substr($user->last_name ?? 'S', 0, 1) }}</div>
                <div style="font-weight:700;font-size:18px;">{{ $user->full_name ?: __('ui.dashboard.user_fallback') }}</div>
                <div class="muted">{{ __('ui.common.web_cabinet') }}</div>
            </div>
            <nav class="nav">
                <a class="active" href="{{ route('dashboard') }}">{{ __('ui.common.dashboard') }}</a>
                <a href="{{ route('login') }}">{{ __('ui.common.auth_pages') }}</a>
                <a href="/admin">{{ __('ui.common.admin_panel') }}</a>
            </nav>
        </aside>

        <main class="content">
            <section class="hero">
                <div>{{ __('ui.dashboard.breadcrumb') }}</div>
                <div class="stats">
                    <div class="stat">
                        <div>{{ __('ui.dashboard.phone') }}</div>
                        <div class="v">{{ $user->phone ?? __('ui.common.not_set') }}</div>
                    </div>
                    <div class="stat">
                        <div>{{ __('ui.common.status') }}</div>
                        <div class="v">{{ $user->status }}</div>
                    </div>
                    <div class="stat">
                        <div>{{ __('ui.dashboard.locale') }}</div>
                        <div class="v">{{ __('ui.languages.'.($user->preferred_locale ?: app()->getLocale())) }}</div>
                    </div>
                </div>
            </section>

            <div class="grid">
                <section class="card">
                    <h2>{{ __('ui.common.profile') }}</h2>
                    <div class="body">
                        <p><strong>{{ __('ui.common.profile') }}:</strong> {{ $user->full_name ?: __('ui.common.not_specified') }}</p>
                        <p><strong>{{ __('ui.dashboard.phone') }}:</strong> {{ $user->phone ?? __('ui.common.not_specified') }}</p>
                        <p><strong>{{ __('ui.dashboard.last_login') }}:</strong> {{ $user->last_login_at?->format('d.m.Y H:i') ?? __('ui.common.not_specified') }}</p>
                    </div>
                </section>

                <section class="card">
                    <h2>{{ __('ui.common.roles') }}</h2>
                    <div class="body">
                        @forelse ($user->roles as $role)
                            <span class="pill">{{ $role->code }}</span>
                        @empty
                            <div class="muted">{{ __('ui.dashboard.no_roles') }}</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>

</html>
