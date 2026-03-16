@extends('layouts.app')

@php
    /** @var \Illuminate\Contracts\Auth\Authenticatable|\App\Models\User|null $authUser */
    $authUser = auth()->user();
    $user = $user ?? $authUser?->loadMissing('roles', 'scopes');
    $sectionTitle = __('ui.menu.' . $sectionKey);
    $title = $title ?? $sectionTitle;
@endphp

@section('content')
    <section style="padding: 24px 0;">
        <div style="background:#fff;border:1px solid #d1d8e5;border-radius:20px;padding:24px;box-shadow:0 12px 32px rgba(35,64,103,0.08);">
            <div class="muted" style="margin-bottom:8px;">{{ __('ui.common.home') }}</div>
            <h1 style="margin:0;font-size:28px;line-height:1.2;">{{ $sectionTitle }}</h1>
        </div>
    </section>
@endsection
