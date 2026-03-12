@extends('layouts.auth')

@section('content')
    <div style="min-height: calc(100vh - 72px); display: grid; place-items: center;">
        <section class="panel" style="width: 100%; max-width: 760px;">
            <div class="panel-header">
                <h3>{{ __('ui.auth.register_methods_title') }}</h3>
            </div>
            <div class="panel-body">
                <p>{{ __('ui.auth.choose_method') }}</p>
                <div class="choice-grid">
                    <a class="choice-card" href="{{ route('register.phone') }}">
                        <strong>{{ __('ui.auth.register_phone_title') }}</strong>
                        <span>{{ __('ui.auth.register_phone_text') }}</span>
                    </a>
                    <a class="choice-card" href="{{ route('register.eds') }}">
                        <strong>{{ __('ui.auth.register_eds_title') }}</strong>
                        <span>{{ __('ui.auth.register_eds_text') }}</span>
                    </a>
                </div>
                <div class="actions">
                    <a class="btn secondary" href="{{ route('login') }}">{{ __('ui.common.back') }}</a>
                </div>
            </div>
        </section>
    </div>
@endsection
