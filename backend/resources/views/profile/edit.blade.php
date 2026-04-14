@extends('layouts.app')

@section('content')
    <style>
        .profile-page {
            padding: 24px 0;
            display: grid;
            gap: 18px;
        }

        .profile-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
            overflow: hidden;
        }

        .profile-header {
            padding: 24px;
            border-bottom: 1px solid #e4e9f1;
        }

        .profile-title {
            margin: 8px 0 0;
            font-size: 30px;
            line-height: 1.1;
            color: #1d3151;
        }

        .profile-subtitle {
            margin-top: 10px;
            color: #71829a;
            font-size: 14px;
        }

        .profile-body {
            padding: 24px;
            display: grid;
            gap: 18px;
        }

        .profile-section-title {
            margin: 0;
            font-size: 22px;
            line-height: 1.2;
            color: #1d3151;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .profile-field {
            display: grid;
            gap: 6px;
        }

        .profile-field.full {
            grid-column: 1 / -1;
        }

        .profile-field.right-column {
            grid-column: 2;
        }

        .profile-field label {
            font-size: 13px;
            font-weight: 700;
            color: #4e607d;
        }

        .profile-field input,
        .profile-field select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d1d8e5;
            border-radius: 12px;
            background: #fff;
            color: #16253d;
        }

        .profile-field input.error,
        .profile-field select.error {
            border-color: #d8485f;
            background: #fff7f8;
        }

        .profile-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .profile-notice {
            padding: 12px 14px;
            border-radius: 12px;
            font-weight: 700;
        }

        .profile-notice.success {
            background: #eaf6ea;
            color: #22653a;
        }

        .profile-notice.error {
            background: #fdecee;
            color: #b9384d;
        }

        .profile-errors {
            margin: 0;
            padding-left: 18px;
        }

        .password-field {
            position: relative;
        }

        .password-field input {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            width: 32px;
            height: 32px;
            padding: 0;
            border: 0;
            border-radius: 10px;
            background: #eef3fb;
            color: #234067;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transform: translateY(-50%);
        }

        .password-toggle svg {
            width: 18px;
            height: 18px;
            display: block;
        }

        .field-error {
            color: #b9384d;
            font-size: 13px;
        }

        @media (max-width: 820px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .profile-field.right-column {
                grid-column: auto;
            }
        }
    </style>

    <section class="profile-page">
        <div class="profile-card">
            <div class="profile-header">
                <div class="muted">{{ __('ui.common.home') }} / {{ __('ui.common.profile') }}</div>
                <h1 class="profile-title">{{ $user->full_name ?: __('ui.dashboard.user_fallback') }}</h1>
                <div class="profile-subtitle">{{ __('ui.profile_page.subtitle') }}</div>
            </div>

            <div class="profile-body">
                <h2 class="profile-section-title">{{ __('ui.profile_page.profile_section') }}</h2>

                @if (session('profile_status'))
                    <div class="profile-notice success">{{ session('profile_status') }}</div>
                @endif

                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="profile-grid">
                        <div class="profile-field">
                            <label for="last_name">{{ __('ui.common.last_name') }}</label>
                            <input id="last_name" name="last_name" type="text"
                                class="{{ $errors->has('last_name') ? 'error' : '' }}"
                                value="{{ old('last_name', $user->last_name) }}">
                            @error('last_name')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="profile-field">
                            <label for="first_name">{{ __('ui.common.first_name') }}</label>
                            <input id="first_name" name="first_name" type="text"
                                class="{{ $errors->has('first_name') ? 'error' : '' }}"
                                value="{{ old('first_name', $user->first_name) }}">
                            @error('first_name')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="profile-field">
                            <label for="middle_name">{{ __('ui.common.middle_name') }}</label>
                            <input id="middle_name" name="middle_name" type="text"
                                class="{{ $errors->has('middle_name') ? 'error' : '' }}"
                                value="{{ old('middle_name', $user->middle_name) }}">
                            @error('middle_name')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="profile-field">
                            <label for="phone">{{ __('ui.common.phone') }}</label>
                            <input id="phone" name="phone" type="tel"
                                class="{{ $errors->has('phone') ? 'error' : '' }}"
                                value="{{ old('phone', $user->phone) }}">
                            @error('phone')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="profile-field">
                            <label for="preferred_locale">{{ __('ui.dashboard.locale') }}</label>
                            <select id="preferred_locale" name="preferred_locale"
                                class="{{ $errors->has('preferred_locale') ? 'error' : '' }}">
                                <option value="ru" @selected(old('preferred_locale', $user->preferred_locale) === 'ru')>{{ __('ui.languages.ru') }}</option>
                                <option value="kk" @selected(old('preferred_locale', $user->preferred_locale) === 'kk')>{{ __('ui.languages.kk') }}</option>
                            </select>
                            @error('preferred_locale')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="profile-actions" style="margin-top: 20px;">
                        <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="profile-card" id="password">
            <div class="profile-body">
                <h2 class="profile-section-title">{{ __('ui.profile_page.password_section') }}</h2>

                @if (session('password_status'))
                    <div class="profile-notice success">{{ session('password_status') }}</div>
                @endif

                @if ($errors->has('current_password') || $errors->has('password'))
                    <div class="profile-notice error">
                        <ul class="profile-errors">
                            @error('current_password')
                                <li>{{ $message }}</li>
                            @enderror
                            @error('password')
                                <li>{{ $message }}</li>
                            @enderror
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="profile-grid">
                        <div class="profile-field">
                            <label for="current_password">{{ __('ui.profile_page.current_password') }}</label>
                            <div class="password-field" data-password-field data-visible="false">
                                <input id="current_password" name="current_password" type="password"
                                    class="{{ $errors->has('current_password') ? 'error' : '' }}"
                                    autocomplete="current-password">
                                <button class="password-toggle" type="button" data-password-toggle
                                    data-show-label="{{ __('ui.common.show_password') }}"
                                    data-hide-label="{{ __('ui.common.hide_password') }}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path
                                            d="M12 5c5.5 0 9.5 4.5 10.8 6.1.3.4.3 1 0 1.4C21.5 14.1 17.5 18.6 12 18.6S2.5 14.1 1.2 12.5a1.1 1.1 0 0 1 0-1.4C2.5 9.5 6.5 5 12 5Zm0 2C8 7 4.8 10 3.3 11.8 4.8 13.6 8 16.6 12 16.6s7.2-3 8.7-4.8C19.2 10 16 7 12 7Zm0 1.5a3.3 3.3 0 1 1 0 6.6 3.3 3.3 0 0 1 0-6.6Zm0 2a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6Z"
                                            fill="currentColor" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="profile-field">
                            <label for="password">{{ __('ui.profile_page.new_password') }}</label>
                            <div class="password-field" data-password-field data-visible="false">
                                <input id="password" name="password" type="password"
                                    class="{{ $errors->has('password') ? 'error' : '' }}"
                                    autocomplete="new-password">
                                <button class="password-toggle" type="button" data-password-toggle
                                    data-show-label="{{ __('ui.common.show_password') }}"
                                    data-hide-label="{{ __('ui.common.hide_password') }}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path
                                            d="M12 5c5.5 0 9.5 4.5 10.8 6.1.3.4.3 1 0 1.4C21.5 14.1 17.5 18.6 12 18.6S2.5 14.1 1.2 12.5a1.1 1.1 0 0 1 0-1.4C2.5 9.5 6.5 5 12 5Zm0 2C8 7 4.8 10 3.3 11.8 4.8 13.6 8 16.6 12 16.6s7.2-3 8.7-4.8C19.2 10 16 7 12 7Zm0 1.5a3.3 3.3 0 1 1 0 6.6 3.3 3.3 0 0 1 0-6.6Zm0 2a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6Z"
                                            fill="currentColor" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="profile-field right-column">
                            <label for="password_confirmation">{{ __('ui.profile_page.new_password_confirmation') }}</label>
                            <div class="password-field" data-password-field data-visible="false">
                                <input id="password_confirmation" name="password_confirmation" type="password"
                                    autocomplete="new-password">
                                <button class="password-toggle" type="button" data-password-toggle
                                    data-show-label="{{ __('ui.common.show_password') }}"
                                    data-hide-label="{{ __('ui.common.hide_password') }}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path
                                            d="M12 5c5.5 0 9.5 4.5 10.8 6.1.3.4.3 1 0 1.4C21.5 14.1 17.5 18.6 12 18.6S2.5 14.1 1.2 12.5a1.1 1.1 0 0 1 0-1.4C2.5 9.5 6.5 5 12 5Zm0 2C8 7 4.8 10 3.3 11.8 4.8 13.6 8 16.6 12 16.6s7.2-3 8.7-4.8C19.2 10 16 7 12 7Zm0 1.5a3.3 3.3 0 1 1 0 6.6 3.3 3.3 0 0 1 0-6.6Zm0 2a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6Z"
                                            fill="currentColor" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="profile-actions" style="margin-top: 20px;">
                        <button class="btn" type="submit">{{ __('ui.profile_page.change_password') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    @include('auth.partials.password-toggle-script')
@endsection
