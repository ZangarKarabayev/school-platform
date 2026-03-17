@extends('layouts.app')

@section('content')
    <section style="padding: 24px 0;">
        <div style="background:#fff;border:1px solid #d1d8e5;border-radius:20px;padding:24px;box-shadow:0 12px 32px rgba(35,64,103,0.08);">
            <div class="muted" style="margin-bottom:8px;">{{ __('ui.common.home') }}</div>
            <h1 style="margin:0;font-size:28px;line-height:1.2;">{{ __('ui.menu.devices') }}</h1>
        </div>
    </section>

    <section style="padding-bottom:24px;">
        <div style="background:#fff;border:1px solid #d1d8e5;border-radius:20px;box-shadow:0 12px 32px rgba(35,64,103,0.08);overflow:hidden;">
            @if ($terminals->isEmpty())
                <div style="padding:24px;color:#71829a;">Терминалы не найдены.</div>
            @else
                <div style="overflow:auto;">
                    <table style="width:100%;border-collapse:collapse;min-width:900px;">
                        <thead>
                            <tr style="background:#f7f9fc;color:#4e607d;text-align:left;">
                                <th style="padding:14px 16px;">ID</th>
                                <th style="padding:14px 16px;">Школа</th>
                                <th style="padding:14px 16px;">Устройство</th>
                                <th style="padding:14px 16px;">IP</th>
                                <th style="padding:14px 16px;">MAC</th>
                                <th style="padding:14px 16px;">Последний heartbeat</th>
                                <th style="padding:14px 16px;">Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($terminals as $terminal)
                                @php
                                    $isOnline = $terminal->time && $terminal->time->greaterThanOrEqualTo(now()->subMinutes(15));
                                @endphp
                                <tr style="border-top:1px solid #e4e9f1;">
                                    <td style="padding:14px 16px;color:#1d3151;">{{ $terminal->id }}</td>
                                    <td style="padding:14px 16px;color:#1d3151;">{{ $terminal->school?->display_name ?? 'Не указана' }}</td>
                                    <td style="padding:14px 16px;color:#1d3151;">{{ $terminal->device_id ?? '—' }}</td>
                                    <td style="padding:14px 16px;color:#1d3151;">{{ $terminal->ip ?? '—' }}</td>
                                    <td style="padding:14px 16px;color:#1d3151;">{{ $terminal->mac_addr ?? '—' }}</td>
                                    <td style="padding:14px 16px;color:#1d3151;">{{ $terminal->time?->format('d.m.Y H:i:s') ?? '—' }}</td>
                                    <td style="padding:14px 16px;">
                                        <span style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:{{ $isOnline ? '#e9f8ef' : '#fff1f2' }};color:{{ $isOnline ? '#167c3b' : '#c2414c' }};font-weight:700;font-size:13px;">
                                            <span style="width:8px;height:8px;border-radius:50%;background:currentColor;"></span>
                                            {{ $isOnline ? 'Онлайн' : 'Оффлайн' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="padding:16px 24px;border-top:1px solid #e4e9f1;">
                    {{ $terminals->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
