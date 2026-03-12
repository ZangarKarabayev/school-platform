@extends('layouts.auth')

@section('content')
    <div style="min-height: calc(100vh - 72px); display: grid; place-items: center;">
        <section class="panel" style="width: 100%; max-width: 760px;">
            <div class="panel-header">
                <h2>{{ __('ui.auth.register_phone_page_title') }}</h2>
            </div>
            <div class="panel-body">
                <p>{{ __('ui.auth.register_phone_page_text') }}</p>

                @if ($errors->has('phone_register'))
                    <div class="error">{{ $errors->first('phone_register') }}</div>
                @endif

                @if ($errors->any())
                    <div class="error">
                        {{ $errors->first('phone') ?? $errors->first('first_name') ?? $errors->first('last_name') ?? $errors->first('password') ?? $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('register.phone') }}" id="register-phone-form">
                    @csrf
                    <div class="field">
                        <label for="register_last_name">{{ __('ui.common.last_name') }} *</label>
                        <input id="register_last_name" name="last_name" value="{{ old('last_name') }}" required>
                    </div>
                    <div class="field">
                        <label for="register_first_name">{{ __('ui.common.first_name') }} *</label>
                        <input id="register_first_name" name="first_name" value="{{ old('first_name') }}" required>
                    </div>
                    <div class="field">
                        <label for="register_middle_name">{{ __('ui.common.middle_name') }}</label>
                        <input id="register_middle_name" name="middle_name" value="{{ old('middle_name') }}">
                    </div>
                    <div class="field">
                        <label for="register_phone">{{ __('ui.common.phone') }} *</label>
                        <input id="register_phone" name="phone" value="{{ old('phone') }}"
                            placeholder="{{ __('ui.auth.phone_placeholder') }}" inputmode="tel" autocomplete="tel" data-phone-input required>
                    </div>
                    <div class="field">
                        <label for="register_password">{{ __('ui.common.password') }} *</label>
                        <input id="register_password" type="password" name="password" placeholder="{{ __('ui.auth.password_placeholder') }}"
                            autocomplete="new-password" required>
                    </div>
                    <div class="field">
                        <label for="register_password_confirmation">{{ __('ui.common.confirm_password') }} *</label>
                        <input id="register_password_confirmation" type="password" name="password_confirmation"
                            placeholder="{{ __('ui.common.password_repeat') }}" autocomplete="new-password" required>
                    </div>
                    <div class="actions">
                        <button class="btn" type="submit" id="register-phone-submit" disabled>{{ __('ui.common.register') }}</button>
                        <a class="btn secondary" href="{{ route('register') }}">{{ __('ui.common.back') }}</a>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
        (function() {
            const submit = document.getElementById('register-phone-submit');

            if (!submit) {
                return;
            }

            const requiredFields = [
                document.getElementById('register_last_name'),
                document.getElementById('register_first_name'),
                document.getElementById('register_phone'),
                document.getElementById('register_password'),
                document.getElementById('register_password_confirmation'),
            ].filter(Boolean);

            const syncState = () => {
                submit.disabled = !requiredFields.every((field) => field.value.trim() !== '');
            };

            requiredFields.forEach((field) => {
                field.addEventListener('input', syncState);
                field.addEventListener('change', syncState);
            });

            syncState();
        })();
    </script>
@endsection
