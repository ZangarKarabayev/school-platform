<header class="topbar">
    <div class="topbar-left">
        <button class="topbar-menu-btn" id="mobile-menu-btn" aria-label="{{ __('ui.menu.open') }}" type="button">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                <path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>
        <div style="font-weight:700;">{{ __('ui.app_name') }}</div>
        @include('partials.lang-switcher')
    </div>
    <div class="topbar-right">
        <a class="btn secondary icon-btn" href="{{ route('profile.edit') }}" aria-label="{{ __('ui.common.profile') }}" title="{{ __('ui.common.profile') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 1.79-8 4v1h16v-1c0-2.21-3.58-4-8-4Z" fill="currentColor"/>
            </svg>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn icon-btn" type="submit" aria-label="{{ __('ui.common.logout') }}" title="{{ __('ui.common.logout') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10 17l1.41-1.41L8.83 13H20v-2H8.83l2.58-2.59L10 7l-5 5 5 5Zm10 7H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h16v2H4v20h16v2Z" fill="currentColor"/>
                </svg>
            </button>
        </form>
    </div>
</header>
