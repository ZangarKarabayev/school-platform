<nav class="lang-switcher" aria-label="{{ __('ui.language') }}">
    @foreach (['ru', 'kk'] as $locale)
        <a href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}"
            @class(['active' => app()->getLocale() === $locale])>{{ __('ui.languages.' . $locale) }}</a>
    @endforeach
</nav>
