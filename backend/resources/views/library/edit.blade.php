@extends('library.layout')
@section('library-content')
@include('library.modal-styles')
<dialog class="library-import-modal library-create-modal" id="library-edit-modal" aria-labelledby="library-edit-title">
    <div class="library-import-header">
        <h2 id="library-edit-title">{{ __('library.edit') }}: {{ $book->title }}</h2>
        <button class="btn secondary" type="button" id="library-edit-close">{{ __('ui.common.close') }}</button>
    </div>
    <form method="POST" action="{{ route('library.books.update', $book->id) }}" class="library-body">
        @csrf
        @method('PUT')
        <input type="hidden" name="school_id" value="{{ $school?->id }}">
        <div class="library-fields">
            @include('library.book-fields', ['editingBook' => $book])
        </div>
        <div><button class="btn">{{ __('library.save') }}</button></div>
    </form>
</dialog>
<script>
(() => {
    const modal = document.getElementById('library-edit-modal');
    modal.addEventListener('close', () => {
        window.location.href = @json(route('library.index', ['school_id' => $school?->id]));
    });
    document.getElementById('library-edit-close').addEventListener('click', () => modal.close());
    modal.addEventListener('click', event => {
        const bounds = modal.getBoundingClientRect();
        if (event.target === modal && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) modal.close();
    });
    modal.showModal();
})();
</script>
@endsection
