@extends('layouts.app')
@section('content')
<style>
    .library-page{display:grid;gap:18px;padding:24px 0;min-width:0}.library-page h1,.library-page h2,.library-page p{margin:0}.library-page h1{font-size:28px}.library-page h2{font-size:19px}.library-head{display:flex;justify-content:space-between;gap:18px;align-items:center;flex-wrap:wrap}.library-card{background:#fff;border:1px solid #d1d8e5;border-radius:20px;box-shadow:0 12px 32px rgba(35,64,103,.08);overflow:hidden}.library-body{padding:24px;display:grid;gap:16px}.library-tabs,.library-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.library-tabs a{padding:12px 16px;border-radius:12px;color:#4e607d;text-decoration:none;font-weight:700;background:#fff;border:1px solid #d1d8e5}.library-tabs a[aria-current=page]{color:#fff;background:#2876dd;border-color:#2876dd}.library-grid{display:grid;grid-template-columns:320px minmax(0,1fr);gap:18px;align-items:start}.library-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.library-field{display:grid;gap:6px;min-width:0}.library-field label{font-size:13px;font-weight:700;color:#4e607d}.library-page input,.library-page select{width:100%;min-height:44px;padding:10px 12px;border:1px solid #d1d8e5;border-radius:12px;color:#16253d;background:white;font:inherit}.library-field.wide{grid-column:1/-1}.library-muted{color:#71829a;font-size:14px;line-height:1.6}.library-table-wrap{overflow:auto}.library-table{width:100%;border-collapse:collapse;min-width:660px}.library-table th,.library-table td{padding:14px 18px;border-bottom:1px solid #e8edf5;text-align:left;vertical-align:top}.library-table th{background:#f7f9fc;color:#4e607d;font-size:12px}.library-badge{display:inline-block;padding:5px 10px;border-radius:999px;background:#eef5ff;color:#1f5cb8;font-size:12px;white-space:nowrap}.library-badge.danger{background:#fff0ed;color:#a43d31}.library-notice{padding:16px;border-radius:12px;background:#eaf6ea;color:#22653a}.library-error{background:#fff0ed;color:#a43d31}.library-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.library-stat strong{display:block;font-size:32px;margin-top:8px;color:#234067}.library-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:18px 24px}.library-page summary{cursor:pointer;font-weight:700}.library-page :focus-visible{outline:3px solid #8abafa;outline-offset:3px}@media(max-width:1100px){.library-grid{grid-template-columns:1fr}.library-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.library-fields,.library-stats{grid-template-columns:1fr}.library-body{padding:18px}.library-tabs a{flex:1;text-align:center}}
</style>
<style>
    .visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    .library-search-form { align-items: flex-end; width: 100%; }
    .library-search-controls { display: flex; align-items: flex-end; gap: 10px; width: 30%; min-width: 430px; }
    .library-search-controls .library-field { flex: 1; }
    .library-search-controls input,
    .library-search-controls > .btn { height: 44px; min-height: 44px; box-sizing: border-box; }
    .library-catalog-buttons { margin-left: auto; }
    .library-search-form > .btn { min-height: 44px; }
    .library-header-buttons { gap: 12px; }
    .library-header-buttons .btn { padding: 10px 14px; min-height: 36px; border: 0; border-radius: 13px; background: #2876dd; color: #fff; font-size: 14px; font-weight: 700; }
    .library-header-buttons .btn.secondary { background: #dce6f5; color: #16253d; }
    @media (max-width: 760px) { .library-search-form { width: 100%; min-width: 0; } .library-search-controls { width: 100%; min-width: 0; } .library-catalog-buttons { margin-left: 0; } }
</style>
<section class="library-page">
    <header class="library-head"><div><div class="library-muted">{{ __('ui.common.home') }}</div><h1>{{ __('library.title') }}</h1></div>
        @yield('library-header-actions')
    </header>
    <nav class="library-tabs" aria-label="{{ __('library.title') }}">@foreach(['index'=>'catalog','stocks'=>'stocks','operations'=>'operations','loans'=>'loans','reports'=>'reports'] as $route=>$label)<a href="{{ route('library.'.$route, ['school_id'=>$school?->id]) }}" @if(request()->routeIs('library.'.$route)) aria-current="page" @endif>{{ __('library.'.$label) }}</a>@endforeach</nav>
    @if(session('status'))<div class="library-notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="library-notice library-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(!$canManage)<p class="library-muted">{{ __('library.read_only') }}</p>@endif
    @yield('library-content')
</section>
@endsection
