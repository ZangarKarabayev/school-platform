@extends('layouts.auth-minimal')

@section('content')
    @php($isSchoolBindingRequired = ($reason ?? '') === 'school')

    <section class="panel">
        <div class="panel-header">
            <h2>{{ $isSchoolBindingRequired ? __('ui.auth.school_binding_page_title') : __('ui.auth.pending_page_title') }}</h2>
        </div>
        <div class="panel-body">
            <div class="note">{{ __('ui.auth.pending_page_badge') }}</div>
            <p>{{ $isSchoolBindingRequired ? __('ui.auth.school_binding_page_text') : __('ui.auth.pending_page_text') }}</p>
            <p>{{ $isSchoolBindingRequired ? __('ui.auth.school_binding_page_review') : __('ui.auth.pending_page_review') }}</p>
            <div class="actions">
                @auth
                    <a class="btn secondary" href="{{ route('profile.edit') }}">{{ __('ui.common.profile') }}</a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn" type="submit">{{ __('ui.common.logout') }}</button>
                    </form>
                @else
                    <a class="btn" href="{{ route('login') }}">{{ __('ui.common.login') }}</a>
                    <a class="btn secondary" href="{{ route('register') }}">{{ __('ui.common.back') }}</a>
                @endauth
            </div>
        </div>
    </section>
@endsection
