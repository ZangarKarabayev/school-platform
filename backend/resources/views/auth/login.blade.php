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
                    <input id="login_password" type="password" name="password" placeholder="{{ __('ui.auth.password_mask') }}"
                        autocomplete="current-password">
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
