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

    @if ($sectionKey === 'reports')
        <section style="padding-bottom: 24px;">
            <div style="background:#fff;border:1px solid #d1d8e5;border-radius:20px;box-shadow:0 12px 32px rgba(35,64,103,0.08);overflow:hidden;">
                @php
                    $reportTypes = [
                        'Отчет по школе',
                        'Отчет по 1-4',
                        'Отчет по 1-5 СУСН',
                        'Отчет по 5-11',
                        'Отчет по 5-11 СУСН',
                    ];
                @endphp

                <div style="padding:24px;">
                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;">
                        <label style="display:grid;gap:6px;">
                            <span style="font-size:13px;font-weight:700;color:#4e607d;">Вид отчета</span>
                            <select name="report_type" style="width:100%;min-height:44px;padding:10px 12px;border:1px solid #d1d8e5;border-radius:12px;background:#fff;color:#16253d;">
                                <option value="">Выберите отчет</option>
                                @foreach ($reportTypes as $reportType)
                                    <option value="{{ $reportType }}">{{ $reportType }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label style="display:grid;gap:6px;">
                            <span style="font-size:13px;font-weight:700;color:#4e607d;">Дата от</span>
                            <input type="date" name="date_from" style="width:100%;min-height:44px;padding:10px 12px;border:1px solid #d1d8e5;border-radius:12px;background:#fff;color:#16253d;">
                        </label>

                        <label style="display:grid;gap:6px;">
                            <span style="font-size:13px;font-weight:700;color:#4e607d;">Дата до</span>
                            <input type="date" name="date_to" style="width:100%;min-height:44px;padding:10px 12px;border:1px solid #d1d8e5;border-radius:12px;background:#fff;color:#16253d;">
                        </label>
                    </div>
                </div>
            </div>
        </section>
    @endif
@endsection
