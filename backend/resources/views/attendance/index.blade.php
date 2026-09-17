@extends('layouts.app')

@section('content')
<style>
    .attendance { padding: 24px 0; display: grid; gap: 18px; color: #16253d; }
    .attendance h1 { margin: 0; font-size: 30px; line-height: 1.1; }
    .attendance h2 { margin: 0; font-size: 20px; color: #1d3151; }
    .att-muted { color: #71829a; }
    .att-card { background: #fff; border: 1px solid #d1d8e5; border-radius: 20px; box-shadow: 0 12px 32px rgba(35, 64, 103, .08); padding: 24px; min-width: 0; }
    .att-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
    .att-subtitle { margin-top: 8px; font-size: 14px; }
    .att-date { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 12px; background: #f3f7fd; color: #234067; font-size: 14px; font-weight: 600; }
    .att-svg { width: 20px; height: 20px; flex-shrink: 0; }
    .att-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
    .att-stat { display: flex; gap: 16px; align-items: center; }
    .att-stat .att-muted { font-size: 14px; }
    .att-stat strong { display: block; font-size: 30px; line-height: 1.2; margin-top: 6px; color: #1d3151; }
    .att-icon, .att-avatar { display: grid; place-items: center; flex-shrink: 0; background: #eef5ff; color: #1f5cb8; }
    .att-icon { width: 48px; height: 48px; border-radius: 14px; }
    .att-icon .att-svg { width: 24px; height: 24px; }
    .att-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 18px; align-items: start; }
    .att-journal { padding: 0; overflow: hidden; }
    .att-title { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 24px; }
    .att-live { font-size: 12px; color: #71829a; }
    .att-live:before { content: ''; display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #22653a; margin-right: 8px; }
    .att-filters { display: grid; grid-template-columns: minmax(180px, 2fr) minmax(120px, 1fr) minmax(150px, 1fr); gap: 12px; align-items: end; padding: 0 24px 24px; }
    .att-field { display: grid; gap: 6px; min-width: 0; }
    .att-field > label { font-size: 13px; font-weight: 700; color: #4e607d; }
    .att-field input, .att-field select { width: 100%; min-width: 0; min-height: 44px; padding: 10px 12px; border: 1px solid #d1d8e5; border-radius: 12px; background: #fff; color: #16253d; font: inherit; font-size: 14px; }
    .attendance :is(input, select, button, a):focus-visible { outline: 2px solid #2876dd; outline-offset: 3px; }
    .att-tabs { display: flex; gap: 4px; background: #eef3fb; border-radius: 12px; padding: 4px; width: fit-content; max-width: 100%; }
    .att-tabs label { position: relative; cursor: pointer; border-radius: 9px; }
    .att-tabs span { display: block; padding: 9px 12px; font-size: 13px; font-weight: 700; border-radius: 9px; color: #446389; }
    .att-tabs input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .att-tabs input:checked + span { background: #2876dd; color: #fff; }
    .att-tabs input:focus-visible + span { outline: 2px solid #2876dd; outline-offset: 2px; }
    .att-actions { display: flex; gap: 10px; flex-wrap: wrap; grid-column: 2 / -1; justify-content: flex-end; }
    .attendance .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; font-size: 14px; text-decoration: none; }
    .attendance .btn:hover { filter: brightness(.96); }
    .attendance .btn[aria-disabled=true] { opacity: .55; cursor: default; }
    .att-scroll { overflow-x: auto; }
    .att-table { width: 100%; min-width: 650px; border-collapse: collapse; text-align: left; }
    .att-table th, .att-table td { padding: 14px 18px; border-bottom: 1px solid #e8edf5; }
    .att-table th { background: #f7f9fc; color: #4e607d; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
    .att-table td { font-size: 14px; color: #1d3151; }
    .att-table tbody tr:hover { background: #f7faff; }
    .att-person { display: flex; align-items: center; gap: 12px; overflow-wrap: anywhere; }
    .att-avatar { width: 40px; height: 40px; border-radius: 50%; font-size: 14px; font-weight: 700; }
    .att-avatar { position: relative; overflow: hidden; }
    .att-avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .att-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 999px; background: #eef3fb; color: #446389; font-size: 12px; font-weight: 700; white-space: nowrap; }
    .att-badge.entry { background: #eaf6ea; color: #22653a; }
    .att-badge.exit { background: #fff4dd; color: #9a6400; }
    .att-latest .att-person { margin: 24px 0; }
    .att-latest .att-avatar { width: 56px; height: 56px; font-size: 20px; }
    .att-latest .att-muted { margin-top: 4px; font-size: 13px; }
    .att-latest > .att-badge { padding: 10px 14px; margin-bottom: 8px; white-space: normal; }
    .att-detail { display: flex; align-items: center; gap: 10px; padding: 16px 0; border-bottom: 1px solid #e8edf5; color: #4e607d; font-size: 14px; }
    .att-detail:last-child { border-bottom: 0; padding-bottom: 0; }
    .att-empty { padding: 28px 24px 32px !important; text-align: center; color: #71829a !important; line-height: 1.6; }
    .att-note { font-size: 13px; line-height: 1.6; margin: 0; padding: 14px 18px; border: 1px solid #dbe4f2; border-radius: 14px; background: #f8fbff; color: #61728d; }
    .att-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 18px 24px 24px; font-size: 14px; }
    .att-pagination { display: flex; gap: 10px; }
    @media (max-width: 1280px) { .att-grid { grid-template-columns: 1fr; } .att-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 600px) {
        .att-stats { grid-template-columns: 1fr; }
        .att-card { padding: 18px; }
        .att-journal { padding: 0; }
        .att-title, .att-footer { padding: 18px; }
        .att-filters { grid-template-columns: 1fr; padding: 0 18px 18px; }
        .att-actions { grid-column: auto; justify-content: flex-start; }
        .attendance h1 { font-size: 26px; }
    }
</style>
<div class="attendance">
    <header class="att-card att-header"><div><div class="att-muted" style="margin-bottom:8px;font-size:14px">{{ __('ui.common.home') }}</div><h1>{{ __('attendance.title') }}</h1><div class="att-muted att-subtitle">{{ __('attendance.subtitle') }}</div></div><time class="att-date" datetime="{{ $date->toDateString() }}">{{ $date->translatedFormat('j F Y') }}</time></header>
    <div class="att-stats">
        @foreach (['total' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0', 'inside' => 'M14 3h6v18h-6 M3 12h12 M10 7l5 5-5 5', 'outside' => 'M10 3H4v18h6 M10 12h11 M16 7l5 5-5 5', 'absent' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0 M12 7v5l3 2'] as $key => $icon)
            <div class="att-card att-stat"><span class="att-icon" aria-hidden="true"><svg class="att-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg></span><div><span class="att-muted">{{ __('attendance.'.$key) }}</span><strong>{{ number_format($stats[$key], 0, '.', ' ') }}</strong></div></div>
        @endforeach
    </div>
    <div class="att-grid"><section class="att-card att-journal">
        <div class="att-title"><h2>{{ __('attendance.journal') }}</h2><span class="att-live">{{ __('attendance.refresh') }}</span></div>
        <form class="att-filters" method="get" id="attendance-filters">
            <div class="att-field"><label for="att-search">{{ __('attendance.search') }}</label><input id="att-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('attendance.search') }}" aria-label="{{ __('attendance.search') }}"></div>
            <div class="att-field"><label for="att-classroom">{{ __('attendance.class') }}</label><select id="att-classroom" name="classroom" aria-label="{{ __('attendance.class') }}"><option value="">{{ __('attendance.classes') }}</option>@foreach($classrooms as $classroom)<option value="{{ $classroom->id }}" @selected(($filters['classroom'] ?? '') == $classroom->id)>{{ $classroom->full_name }}</option>@endforeach</select></div>
            <div class="att-field"><label for="att-date">{{ __('attendance.date') }}</label><input id="att-date" type="date" name="date" value="{{ $date->toDateString() }}" aria-label="{{ __('attendance.date') }}"></div>
            <div class="att-tabs">@foreach(['' => 'all', 'entry' => 'entry', 'exit' => 'exit'] as $value => $label)<label><input type="radio" name="direction" value="{{ $value }}" @checked(($filters['direction'] ?? '') === $value)><span>{{ __('attendance.'.$label) }}</span></label>@endforeach</div>
            <div class="att-actions"><button class="btn" type="submit">{{ __('attendance.apply') }}</button><a class="btn secondary" href="{{ route('attendance.index') }}">{{ __('attendance.reset') }}</a></div>
        </form>
        <div class="att-scroll"><table class="att-table"><thead><tr>@foreach(['student','class','event','time','point'] as $label)<th scope="col">{{ __('attendance.'.$label) }}</th>@endforeach</tr></thead><tbody>
        @forelse($events as $event)
            <tr><td><div class="att-person">@include('attendance.avatar', ['student' => $event->student]){{ $event->student?->full_name ?: $event->name }}</div></td><td>{{ $event->student?->classroom?->full_name ?? '—' }}</td><td><span class="att-badge {{ $event->direction }}">{{ __('attendance.'.($event->direction ?? 'unknown')) }}</span></td><td>{{ $event->create_time->format('H:i:s') }}</td><td>{{ __('attendance.terminal') }} {{ $event->device_id ?? '—' }}</td></tr>
        @empty<tr><td colspan="5" class="att-empty">{{ __('attendance.empty') }}</td></tr>@endforelse
        </tbody></table></div>
        <div class="att-footer"><span class="att-muted">{{ __('attendance.range', ['from' => $events->firstItem() ?? 0, 'to' => $events->lastItem() ?? 0, 'total' => $events->total()]) }}</span>@if($events->hasPages())
            <nav class="att-pagination" aria-label="{{ __('attendance.journal') }}">
                @if($events->onFirstPage())<span class="btn secondary" aria-disabled="true">{{ __('ui.common.previous') }}</span>
                @else<a class="btn secondary" href="{{ $events->previousPageUrl() }}">{{ __('ui.common.previous') }}</a>@endif
                @if($events->hasMorePages())<a class="btn" href="{{ $events->nextPageUrl() }}">{{ __('ui.common.next') }}</a>
                @else<span class="btn secondary" aria-disabled="true">{{ __('ui.common.next') }}</span>@endif
            </nav>
        @endif</div>
    </section><aside class="att-card att-latest"><h2>{{ __('attendance.latest') }}</h2>
        @if($latest)
            <div class="att-person">@include('attendance.avatar', ['student' => $latest->student])<div><strong>{{ $latest->student?->full_name ?: $latest->name }}</strong><div class="att-muted">{{ $latest->student?->classroom?->full_name ?? '—' }}</div></div></div>
            <div class="att-badge {{ $latest->direction }}">{{ $latest->direction ? __('attendance.'.$latest->direction) : __('attendance.recorded') }}</div>
            <div class="att-detail"><svg class="att-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> {{ $latest->create_time->format('H:i:s') }}</div><div class="att-detail">{{ __('attendance.terminal') }} {{ $latest->device_id ?? '—' }}</div>
        @else<div class="att-empty">{{ __('attendance.empty') }}</div>@endif
    </aside></div>
</div>
<script>
    (() => {
        const form = document.getElementById('attendance-filters');
        let dirty = false;
        form.addEventListener('input', () => dirty = true);
        form.addEventListener('change', () => dirty = true);
        setInterval(() => {
            if (!document.hidden && !dirty && !form.contains(document.activeElement)) window.location.reload();
        }, 600000);
    })();
</script>
@endsection
