@extends('layouts.auth-minimal')

@section('content')
    <section class="panel">
        <div class="panel-header">
            <h2>{{ __('ui.auth.pending_page_title') }}</h2>
        </div>
        <div class="panel-body">
            <div class="note">{{ __('ui.auth.pending_page_badge') }}</div>
            <p>{{ __('ui.auth.pending_page_text') }}</p>
            <p>{{ __('ui.auth.pending_page_review') }}</p>
            <div class="actions">
                <a class="btn" href="{{ route('login') }}">{{ __('ui.common.login') }}</a>
                <a class="btn secondary" href="{{ route('register') }}">{{ __('ui.common.back') }}</a>
            </div>
        </div>
    </section>
@endsection
