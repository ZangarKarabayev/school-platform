@extends('library.layout')
@section('library-content')
@if(!$school)<div class="library-notice">{{ __('library.choose_school') }}</div>@else
<div class="library-grid"><div class="library-card"><div class="library-body"><h2>{{ __('library.operations') }}</h2>
@include('library.operation-search')
</div></div>
<div style="display:grid;gap:18px"><div class="library-card"><div class="library-body">
@if($student)<h2>{{ $student->full_name }}</h2><p class="library-muted">{{ __('library.grade') }}: {{ $student->classroom?->full_name ?: '—' }}</p>@elseif(request()->filled('student_code'))<p class="library-notice library-error">{{ __('library.student_missing') }}</p>@else<p class="library-muted">{{ __('library.student_code') }}</p>@endif
<h2>{{ __('library.cart') }}</h2>
<div id="selected-books-list" class="library-lookup-results" aria-live="polite">
@forelse($stocks as $selectedStock)
<div><strong>{{ $selectedStock->title }}</strong><small>{{ __('library.grade') }}: {{ $selectedStock->grade ?? '—' }} · {{ $selectedStock->barcode }}</small></div>
@empty
<p class="library-muted">{{ __('library.empty') }}</p>
@endforelse
</div>
@if($student && $stocks && $stocks->isNotEmpty() && $canManage)<form method="POST" action="{{ route('library.operate') }}" class="library-body" style="padding:0">@csrf<input type="hidden" name="school_id" value="{{ $school->id }}"><input type="hidden" name="student_code" value="{{ request('student_code') }}">@foreach($stocks as $selectedStock)<input type="hidden" name="barcodes[]" value="{{ $selectedStock->barcode }}">@endforeach<input type="hidden" name="mode" value="{{ request('mode','issue') }}"><input type="hidden" name="operation_token" value="{{ $token }}"><div class="library-fields"> @if(request('mode') !== 'return') @include('library.field',['name'=>'due_at','type'=>'date','value'=>now()->addDays(30)->toDateString(),'min'=>today()->toDateString(),'required'=>true]) @endif</div><div><button class="btn">{{ __('library.confirm') }}: {{ __('library.'.(request('mode') === 'return' ? 'return' : 'issue')) }}</button></div></form>@endif
</div></div>
@if($student)<div class="library-card"><div class="library-body"><h2>{{ __('library.student_books') }}</h2>
@if($canManage && $loans->isNotEmpty())
<a class="btn secondary" href="{{ route('library.operations', ['school_id' => $school->id, 'student_code' => request('student_code'), 'barcodes' => $loans->pluck('barcode')->unique()->values()->all(), 'mode' => 'return']) }}">{{ __('library.return_all') }}</a>
@endif
</div><div class="library-table-wrap"><table class="library-table"><thead><tr><th>{{ __('library.book') }}</th><th>{{ __('library.outstanding') }}</th><th>{{ __('library.due_at') }}</th><th></th></tr></thead><tbody>@forelse($loans as $loan)<tr><td>{{ $loan->title }}<div class="library-muted">{{ $loan->barcode }}@if($loan->grade !== null) · {{ __('library.grade') }}: {{ $loan->grade }}@endif</div></td><td>{{ $loan->outstanding }}</td><td>{{ $loan->due_at ?: '—' }}</td><td>@if(request('mode') === 'return' && $stocks->contains('barcode', $loan->barcode))
<span class="library-muted">{{ __('library.in_cart') }}</span>
@else
<a class="btn secondary" href="{{ route('library.operations', ['school_id' => $school->id, 'student_code' => request('student_code'), 'barcodes' => (request('mode') === 'return' ? $stocks->pluck('barcode') : collect())->push($loan->barcode)->unique()->values()->all(), 'mode' => 'return']) }}">{{ __('library.return') }}</a>
@endif</td></tr>@empty<tr><td colspan="4">{{ __('library.empty') }}</td></tr>@endforelse</tbody></table></div></div>@endif
</div></div>
@endif
@endsection
