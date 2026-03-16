<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('ui.dashboard.title') }}</title>
    <style>
        :root {
            --topbar-height: 68px;
            --sidebar-width: 296px;
            --sidebar-collapsed-width: 88px;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #2876dd;
        }

        body {
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: #16253d;
        }

        .app-shell {
            position: fixed;
            inset: 0;
            display: grid;
            grid-template-rows: var(--topbar-height) minmax(0, 1fr);
            background: #dfe5ef;
        }

        .topbar {
            grid-row: 1;
            margin: 0;
            min-height: var(--topbar-height);
            height: var(--topbar-height);
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
            grid-row: 2;
            width: 100%;
            min-width: 100%;
            min-height: 0;
            display: grid;
            grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
            align-items: stretch;
            justify-items: stretch;
        }

        .workspace[data-collapsed="true"] {
            grid-template-columns: var(--sidebar-collapsed-width) minmax(0, 1fr);
        }

        .sidebar {
            grid-column: 1;
            grid-row: 1;
            justify-self: stretch;
            align-self: stretch;
            min-width: 0;
            width: 100%;
            max-width: var(--sidebar-width);
            background: #fff;
            border-right: 1px solid #d1d8e5;
            display: flex;
            flex-direction: column;
            overflow: hidden auto;
        }

        .sidebar[data-collapsed="true"] {
            max-width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 16px 16px 0;
            background: #f6f8fc;
        }

        .profile {
            padding: 16px;
            border-bottom: 1px solid #d1d8e5;
            background: #f6f8fc;
            text-align: left;
        }

        .profile-name {
            font-weight: 700;
            font-size: 16px;
            line-height: 1.35;
            word-break: break-word;
        }

        .profile-subtitle {
            margin-top: 6px;
        }

        .avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #173d74 0%, #3f91ff 100%);
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 12px;
        }

        .sidebar-toggle {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            padding: 0 12px;
            border: 1px solid #d1d8e5;
            border-radius: 12px;
            background: #fff;
            color: #234067;
            cursor: pointer;
            font-weight: 700;
        }

        .sidebar-toggle-icon {
            font-size: 16px;
            line-height: 1;
            flex: 0 0 auto;
        }

        .sidebar-toggle-label {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-menu {
            padding: 0;
            overflow: auto;
        }

        .nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 48px;
            padding: 12px 16px 12px 14px;
            color: #2a3953;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .nav a.active,
        .nav a:hover {
            background: #2876dd;
            color: #fff;
            border-left-color: #b9d8ff;
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 20px;
        }

        .nav-icon svg {
            width: 20px;
            height: 20px;
        }

        .nav-label {
            min-width: 0;
            line-height: 1.3;
            word-break: break-word;
        }

        .sidebar-note {
            margin-top: auto;
            padding: 16px 18px 22px;
            color: #71829a;
            font-size: 12px;
            line-height: 1.5;
            border-top: 1px solid #e4e9f1;
        }

        .sidebar[data-collapsed="true"] .profile,
        .sidebar[data-collapsed="true"] .sidebar-note {
            padding-left: 12px;
            padding-right: 12px;
        }

        .sidebar[data-collapsed="true"] .profile {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .sidebar[data-collapsed="true"] .profile-name,
        .sidebar[data-collapsed="true"] .profile-subtitle,
        .sidebar[data-collapsed="true"] .nav-label,
        .sidebar[data-collapsed="true"] .sidebar-note,
        .sidebar[data-collapsed="true"] .sidebar-toggle-label {
            display: none;
        }

        .sidebar[data-collapsed="true"] .avatar {
            width: 44px;
            height: 44px;
            margin-bottom: 0;
            font-size: 18px;
        }

        .sidebar[data-collapsed="true"] .nav a {
            justify-content: center;
            padding-left: 12px;
            padding-right: 12px;
        }

        .content {
            grid-column: 2;
            grid-row: 1;
            justify-self: stretch;
            align-self: stretch;
            min-width: 0;
            width: 100%;
            overflow: auto;
            padding: 0 22px 22px;
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

        .btn.secondary {
            background: #dde7f4;
            color: #234067;
        }

        .icon-btn {
            width: 40px;
            min-width: 40px;
            height: 40px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .icon-btn svg {
            width: 18px;
            height: 18px;
            display: block;
        }

        .muted {
            color: #71829a;
        }

        @media (max-width: 820px) {
            .app-shell {
                grid-template-rows: auto minmax(0, 1fr);
            }

            .topbar {
                height: auto;
                min-height: var(--topbar-height);
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

            .sidebar,
            .sidebar[data-collapsed="true"] {
                grid-column: 1;
                max-width: none;
                width: 100%;
            }

            .content {
                grid-column: 1;
            }

            .sidebar[data-collapsed="true"] .profile-name,
            .sidebar[data-collapsed="true"] .profile-subtitle,
            .sidebar[data-collapsed="true"] .nav-label,
            .sidebar[data-collapsed="true"] .sidebar-note,
            .sidebar[data-collapsed="true"] .sidebar-toggle-label {
                display: initial;
            }

            .sidebar[data-collapsed="true"] .profile {
                display: block;
            }

            .sidebar[data-collapsed="true"] .avatar {
                width: 64px;
                height: 64px;
                margin: 0 0 12px;
                font-size: 22px;
            }

            .sidebar[data-collapsed="true"] .nav a {
                justify-content: flex-start;
                padding-left: 18px;
                padding-right: 18px;
            }
        }
    </style>
</head>

<body>
    <div class="app-shell">
        @include('partials.topbar-app')

        <div class="workspace" id="app-workspace">
            @include('partials.sidebar-app', ['user' => $user])

            <main class="content">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            const storageKey = 'app.sidebar.collapsed';
            const toggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('app-sidebar');
            const workspace = document.getElementById('app-workspace');

            if (!toggle || !sidebar || !workspace) {
                return;
            }

            const isMobile = () => window.matchMedia('(max-width: 820px)').matches;

            const sync = (collapsed) => {
                const resolved = isMobile() ? false : collapsed;
                sidebar.dataset.collapsed = resolved ? 'true' : 'false';
                workspace.dataset.collapsed = resolved ? 'true' : 'false';
                toggle.setAttribute('aria-expanded', resolved ? 'false' : 'true');
            };

            sync(window.localStorage.getItem(storageKey) === '1');

            toggle.addEventListener('click', () => {
                const collapsed = sidebar.dataset.collapsed !== 'true';
                window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
                sync(collapsed);
            });

            window.addEventListener('resize', () => {
                sync(window.localStorage.getItem(storageKey) === '1');
            });
        })();
    </script>
</body>

</html>



