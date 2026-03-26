@extends('layouts.auth-minimal')

@section('content')
    <section class="panel">
        <div class="panel-header">
            <h2>Вход для кухни</h2>
        </div>
        <div class="panel-body">
            <p>Вход предназначен только для сотрудников столовой. После авторизации откроется страница сканирования заказов.</p>

            @if ($errors->has('kitchen_login'))
                <div class="error">{{ $errors->first('kitchen_login') }}</div>
            @endif

            <form method="POST" action="{{ route('kitchen.login.phone') }}">
                @csrf
                <div class="field">
                    <label for="kitchen_login_phone">{{ __('ui.common.phone') }}</label>
                    <input id="kitchen_login_phone" name="phone" value="{{ old('phone') }}" placeholder="{{ __('ui.auth.phone_placeholder') }}"
                        inputmode="tel" autocomplete="tel" data-phone-input>
                </div>
                <div class="field">
                    <label for="kitchen_login_password">{{ __('ui.common.password') }}</label>
                    <div class="password-field" data-password-field data-visible="false">
                        <input id="kitchen_login_password" type="password" name="password" placeholder="{{ __('ui.auth.password_mask') }}"
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
                    <button class="btn" type="submit">Войти в кухню</button>
                    <a class="btn secondary" href="{{ route('login') }}">Общий вход</a>
                </div>
            </form>
        </div>
    </section>
@endsection