<link rel="stylesheet" href="{{ asset('vendor/cropperjs/cropper.min.css') }}">
<style>
    .student-photo-editor { width: min(1400px, calc(100vw - 32px)); max-width: none; max-height: calc(100dvh - 32px); padding: 0; border: 0; border-radius: 20px; color: #16253d; background: #fff; }
    .student-photo-editor::backdrop { background: rgb(15 23 42 / 65%); }
    .student-photo-editor header { padding: 18px 24px; border-bottom: 1px solid #d1d8e5; font-size: 18px; font-weight: 700; }
    .student-photo-editor-layout { display: grid; grid-template-columns: minmax(0, 1fr) 260px; }
    .student-photo-editor-stage { height: min(70dvh, 760px); min-height: 250px; background: #eef1f6; overflow: hidden; }
    .student-photo-editor-stage > img { display: block; max-width: 100%; }
    .student-photo-editor-controls { padding: 20px; display: grid; gap: 12px; align-content: start; }
    .student-photo-editor-controls label { display: grid; gap: 5px; font-size: 13px; font-weight: 700; color: #4e607d; }
    .student-photo-editor-controls input, .student-photo-editor-controls select { width: 100%; padding: 8px 10px; min-height: 40px; border: 1px solid #d1d8e5; border-radius: 10px; background: #fff; color: #16253d; }
    .student-photo-editor-tools { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .student-photo-editor .btn { min-height: 40px; cursor: pointer; }
    .student-photo-editor .btn:disabled { opacity: .5; cursor: wait; }
    .student-photo-editor footer { display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid #d1d8e5; }
    .student-photo-editor-error { color: #b91c1c; padding: 0 24px; }
    @media (max-width: 760px) {
        .student-photo-editor-layout { grid-template-columns: 1fr; }
        .student-photo-editor-stage { height: 45dvh; }
        .student-photo-editor-controls { grid-template-columns: 1fr 1fr; padding: 12px; }
        .student-photo-editor-tools { grid-column: 1 / -1; grid-template-columns: repeat(4, 1fr); }
    }
</style>
<dialog id="student-photo-editor" class="student-photo-editor" aria-labelledby="student-photo-editor-title"
    data-edit-label="{{ __('ui.photo_editor.edit') }}" data-invalid-file="{{ __('ui.photo_editor.invalid_file') }}">
    <form method="POST">
        @csrf
        <input type="hidden" name="photo_data">
        <header id="student-photo-editor-title">{{ __('ui.photo_editor.title') }}</header>
        <div class="student-photo-editor-layout">
            <div class="student-photo-editor-stage"><img data-editor-image alt="{{ __('ui.common.photo') }}"></div>
            <div class="student-photo-editor-controls">
                @foreach (['x', 'y', 'width', 'height', 'rotate'] as $field)
                    <label>{{ __('ui.photo_editor.'.$field) }}
                        <input type="number" data-editor-field="{{ $field }}" step="1" @if (in_array($field, ['width', 'height'])) min="1" @endif>
                    </label>
                @endforeach
                <label>{{ __('ui.photo_editor.aspect') }}
                    <select data-editor-aspect>
                        <option value="1">{{ __('ui.photo_editor.square') }}</option>
                        <option value="0.75">3:4</option>
                        <option value="free">{{ __('ui.photo_editor.free') }}</option>
                    </select>
                </label>
                <div class="student-photo-editor-tools">
                    @foreach (['zoom-in', 'zoom-out', 'left', 'right', 'flip-x', 'flip-y', 'reset'] as $action)
                        <button class="btn secondary" type="button" data-editor-action="{{ $action }}">{{ __('ui.photo_editor.'.$action) }}</button>
                    @endforeach
                </div>
            </div>
        </div>
        <p data-editor-error class="student-photo-editor-error" role="alert" hidden>{{ __('ui.photo_editor.error') }}</p>
        <footer>
            <button class="btn secondary" type="button" data-editor-cancel>{{ __('ui.common.close') }}</button>
            <button class="btn" type="submit" data-editor-save disabled>{{ __('ui.common.save') }}</button>
        </footer>
    </form>
</dialog>
<script src="{{ asset('vendor/cropperjs/cropper.min.js') }}" defer></script>
<script src="{{ asset('js/student-photo-editor.js') }}" defer></script>
