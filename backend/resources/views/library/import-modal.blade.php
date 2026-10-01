@include('library.modal-styles')
<dialog class="library-import-modal" id="library-import-modal" aria-labelledby="library-import-title" aria-describedby="library-import-description">
    <div class="library-import-header">
        <h2 id="library-import-title">{{ __('library.import') }}</h2>
        <button class="btn secondary" type="button" id="library-import-close">{{ __('ui.common.close') }}</button>
    </div>
    <div class="library-import-body">
        <p class="library-muted" id="library-import-description">{{ __('library.import_hint') }}</p>
        @if($errors->has('books_file'))
            <div class="library-notice library-error" role="alert">{{ $errors->first('books_file') }}</div>
        @endif
        <a class="btn secondary" href="{{ route('library.template') }}">{{ __('library.template') }}</a>
        @if($school)
            <form method="POST" action="{{ route('library.import') }}" enctype="multipart/form-data" class="library-import-form">
                @csrf
                <input type="hidden" name="school_id" value="{{ $school->id }}">
                <div class="library-field">
                    <label for="books_file">CSV</label>
                    <input type="file" id="books_file" name="books_file" accept=".csv,.txt" required>
                </div>
                <button class="btn" type="submit">{{ __('library.import') }}</button>
            </form>
        @else
            <p class="library-muted">{{ __('library.choose_school') }}</p>
        @endif
    </div>
</dialog>
<script>
    (() => {
        const modal = document.getElementById('library-import-modal');
        const trigger = document.getElementById('library-import-open');
        trigger.addEventListener('click', () => modal.showModal());
        document.getElementById('library-import-close').addEventListener('click', () => modal.close());
        modal.addEventListener('click', (event) => {
            const bounds = modal.getBoundingClientRect();
            if (event.target === modal && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) {
                modal.close();
            }
        });
        modal.addEventListener('close', () => trigger.focus());
        @if($errors->has('books_file'))
            modal.showModal();
        @endif
    })();
</script>
