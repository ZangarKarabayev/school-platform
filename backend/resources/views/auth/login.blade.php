@extends('layouts.auth-minimal')

@section('content')
    <section class="panel">
        <div class="panel-header">
            <h2>{{ __('ui.auth.login_phone_title') }}</h2>
        </div>
        <div class="panel-body">
            @if ($errors->has('phone_login'))
                <div class="error">{{ $errors->first('phone_login') }}</div>
            @endif

            <form method="POST" action="{{ route('login.phone') }}">
                @csrf
                <div class="field">
                    <label for="login_phone">{{ __('ui.common.phone') }}</label>
                    <input id="login_phone" name="phone" value="{{ old('phone') }}" placeholder="{{ __('ui.auth.phone_placeholder') }}"
                        inputmode="tel" autocomplete="tel" data-phone-input>
                </div>
                <div class="field">
                    <label for="login_password">{{ __('ui.common.password') }}</label>
                    <div class="password-field" data-password-field data-visible="false">
                        <input id="login_password" type="password" name="password" placeholder="{{ __('ui.auth.password_mask') }}"
                            autocomplete="current-password">
                        <button class="password-toggle" type="button" data-password-toggle
                            data-show-label="{{ __('ui.common.show_password') }}"
                            data-hide-label="{{ __('ui.common.hide_password') }}">
                            <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z" /><circle cx="12" cy="12" r="3" /></svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18" /><path d="M10.6 10.7a3 3 0 0 0 4.2 4.2" /><path d="M9.9 5.1A10.9 10.9 0 0 1 12 5c6.4 0 10 7 10 7a18.7 18.7 0 0 1-4 4.9" /><path d="M6.6 6.6C4 8.4 2 12 2 12s3.6 6 10 6a10.7 10.7 0 0 0 5.4-1.4" /></svg>
                        </button>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn" type="submit">{{ __('ui.common.login') }}</button>
                </div>
                <div class="mini">
                    <a href="{{ route('register') }}">{{ __('ui.common.register') }}</a>
                    <a href="{{ route('login.eds') }}">{{ __('ui.auth.login_eds_title') }}</a>
                </div>
            </form>
        </div>
    </section>
@endsection
