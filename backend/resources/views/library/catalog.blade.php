@extends('library.layout')
@section('library-header-actions')
@endsection
@section('library-content')
<div class="library-card"><div class="library-body">
<form method="GET" class="library-actions library-search-form"><input type="hidden" name="school_id" value="{{ $school?->id }}"><div class="library-search-controls"><div class="library-field"><label for="search" class="visually-hidden">{{ __('library.search') }}</label><input id="search" name="search" value="{{ request('search') }}" placeholder="{{ __('library.search_hint') }}" aria-label="{{ __('library.search') }}"></div><button class="btn" type="submit">{{ __('library.find') }}</button></div>@if($canManage)<div class="library-actions library-catalog-buttons"><button class="btn secondary" type="button" id="library-import-open" aria-haspopup="dialog" aria-controls="library-import-modal">{{ __('library.import') }}</button><button class="btn" type="button" id="library-create-open" aria-haspopup="dialog" aria-controls="library-create-modal">{{ __('library.add') }}</button></div>@endif</form>

</div><div class="library-table-wrap"><table class="library-table"><thead><tr><th>{{ __('library.book') }}</th><th>{{ __('library.barcode') }}</th><th>{{ __('library.publisher') }}</th><th>{{ __('library.grade') }} / {{ __('library.language') }}</th></tr></thead><tbody>@forelse($books as $book)<tr><td>@if($canManage)<a data-library-edit href="{{ route('library.books.edit', ['book'=>$book->id,'school_id'=>$school?->id]) }}"><strong>{{ $book->title }}</strong></a>@else<strong>{{ $book->title }}</strong>@endif<div class="library-muted">{{ $book->author }}</div><span class="library-badge">{{ __('library.'.$book->literature_type) }}</span></td><td>{{ $book->barcode }}</td><td>{{ $book->publisher ?: '—' }}<div class="library-muted">{{ $book->publication_year }} @if($book->part) · {{ __('library.part') }} {{ $book->part }} @endif</div></td><td>{{ $book->grade ?: '—' }} / {{ $book->language ?: '—' }}</td></tr>@empty<tr><td colspan="4">{{ __('library.empty') }}</td></tr>@endforelse</tbody></table></div>@include('library.pagination',['items'=>$books])</div>
@if($canManage)
    @include('library.import-modal')
    @include('library.create-modal')
    @include('library.catalog-edit')
@endif
@endsection
