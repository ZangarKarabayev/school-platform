<dialog class="library-import-modal library-create-modal" id="library-create-modal" aria-labelledby="library-create-title">
    <div class="library-import-header">
        <h2 id="library-create-title">{{ __('library.add_book') }}</h2>
        <button class="btn secondary" type="button" id="library-create-close">{{ __('ui.common.close') }}</button>
    </div>
    @if(old('library_form') === 'create' && $errors->any())
        <div class="library-import-body"><div class="library-notice library-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif
<form method="POST" action="{{ route('library.books.store') }}" class="library-body">@csrf<input type="hidden" name="library_form" value="create"><input type="hidden" name="school_id" value="{{ $school?->id }}"><div class="library-fields">
@include('library.book-fields', ['editingBook' => null])
</div><div><button class="btn">{{ __('library.save') }}</button></div></form>
</dialog>
<script>
    (() => {
        const modal = document.getElementById('library-create-modal');
        const trigger = document.getElementById('library-create-open');
        trigger.addEventListener('click', () => modal.showModal());
        document.getElementById('library-create-close').addEventListener('click', () => modal.close());
        modal.addEventListener('click', (event) => {
            const bounds = modal.getBoundingClientRect();
            if (event.target === modal && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) {
                modal.close();
            }
        });
        modal.addEventListener('close', () => trigger.focus());
        @if(old('library_form') === 'create' && $errors->any())
            modal.showModal();
        @endif
    })();
</script>
