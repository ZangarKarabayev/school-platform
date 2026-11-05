@extends('layouts.app')

@section('content')
    <style>
        .dishes-page { padding: 24px 0; display: grid; gap: 18px; }
        .dishes-grid { display: grid; grid-template-columns: 360px minmax(0, 1fr); gap: 18px; }
        .dishes-card { background: #fff; border: 1px solid #d1d8e5; border-radius: 20px; box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08); }
        .dishes-card h1, .dishes-card h2 { margin: 0; }
        .dishes-card-header { padding: 24px 24px 0; }
        .dishes-card-body { padding: 24px; }
        .dishes-form, .dishes-filters { display: grid; gap: 12px; }
        .dishes-filters { display: flex; align-items: flex-end; flex-wrap: wrap; }
        .dishes-filters > .field { flex: 1 1 150px; min-width: 0; }
        .dishes-filters > .field:has(#search) { flex: 2 1 220px; }
        .dishes-filters > .dishes-actions { flex: 0 0 auto; }
        @media (max-width: 600px) { .dishes-filters > .field { flex-basis: 100%; } }
        .field { display: grid; gap: 6px; }
        .field label { font-size: 13px; font-weight: 700; color: #4e607d; }
        .field .field-error { color: #b91c1c; font-size: 12px; }
        .field [aria-invalid="true"], .field input:user-invalid, .field select:user-invalid, .field textarea:user-invalid { border-color: #dc2626; }
        .field input, .field select, .field textarea { width: 100%; min-height: 44px; padding: 10px 12px; border: 1px solid #d1d8e5; border-radius: 12px; background: #fff; color: #16253d; }
        .field textarea { min-height: 110px; resize: vertical; }
        .dishes-form { gap: 18px; }
        .dishes-form .field input, .dishes-form .field select, .dishes-form .field textarea { border-color: #dce4ef; background-color: #fbfcfe; font: inherit; font-size: 14px; transition: border-color .15s, box-shadow .15s; }
        .dishes-form .field input:focus, .dishes-form .field select:focus, .dishes-form .field textarea:focus { outline: none; border-color: #2878df; background-color: #fff; box-shadow: 0 0 0 3px rgb(40 120 223 / 12%); }
        .dishes-form .field select { appearance: none; padding-right: 38px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%23677991' stroke-width='1.8'%3E%3Cpath d='m4 6 4 4 4-4'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }
        .dishes-form input[type="number"] { appearance: textfield; -moz-appearance: textfield; }
        .dishes-form input[type="number"]::-webkit-inner-spin-button, .dishes-form input[type="number"]::-webkit-outer-spin-button { appearance: none; margin: 0; }
        .dishes-form .field [aria-invalid="true"], .dishes-form .field input:user-invalid { border-color: #dc2626; }
        .dish-upload { position: relative; display: grid; justify-items: center; gap: 10px; padding: 20px 14px; border: 1px dashed #b9cce7; border-radius: 14px; background: #f6f9ff; text-align: center; }
        .dish-upload:focus-within { outline: 3px solid rgb(40 120 223 / 20%); border-color: #2878df; }
        .dish-upload svg { color: #2878df; }
        .dish-upload .dish-upload-input { position: absolute; width: 1px; height: 1px; min-height: 0; padding: 0; overflow: hidden; clip-path: inset(50%); border: 0; }
        .dishes-form .dish-upload .dish-upload-label { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 8px 16px; border: 1px solid #c5d7ef; border-radius: 9px; background: #fff; color: #2466bd; cursor: pointer; }
        .dish-upload-name { max-width: 100%; overflow-wrap: anywhere; color: #60738e; font-size: 12px; }
        .dish-upload img { width: 100%; max-width: 220px; height: 120px; object-fit: contain; border-radius: 10px; }
        .dish-upload img[hidden] { display: none; }
        .inline-check { display: inline-flex; align-items: center; gap: 10px; font-weight: 700; color: #234067; }
        .inline-check input { width: 18px; height: 18px; }
        .dishes-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .notice { margin: 0 24px 24px; padding: 12px 14px; border-radius: 12px; background: #eaf6ea; color: #22653a; font-weight: 700; }
        .dishes-table-wrap { overflow: auto; border-top: 1px solid #e4e9f1; }
        .dishes-table { width: 100%; border-collapse: collapse; min-width: 760px; }
        .dishes-table th, .dishes-table td { padding: 14px 18px; border-bottom: 1px solid #e8edf5; text-align: left; vertical-align: middle; }
        .dishes-table th { background: #f7f9fc; color: #4e607d; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; }
        .dish-name { font-weight: 700; color: #1d3151; }
        .dish-photo-button { display: block; padding: 0; border: 0; border-radius: 8px; background: transparent; cursor: zoom-in; }
        .dish-photo-button:focus-visible { outline: 3px solid #2878df; outline-offset: 3px; }
        .dishes-table .dish-delete { background: #dc2626; color: #fff; border: 1px solid #dc2626; }
        .dishes-table .dish-delete:hover { background: #b91c1c; border-color: #b91c1c; }
        .dish-photo-dialog form { margin-top: 16px; display: flex; justify-content: center; }
        .dish-photo-dialog { margin: auto; padding: 48px 16px 16px; width: fit-content; max-width: 94vw; max-height: 94dvh; border: 0; border-radius: 16px; background: #fff; box-shadow: 0 24px 80px #0006; }
        .dish-photo-dialog::backdrop { background: rgb(10 20 35 / 75%); }
        .dish-photo-dialog img { display: block; width: auto; height: auto; max-width: min(1100px, 86vw); max-height: 68dvh; object-fit: contain; margin: auto; }
        .dish-photo-dialog img[hidden] { display: none; }
        .dish-photo-close { position: absolute; top: 6px; right: 8px; width: 36px; height: 36px; border: 0; border-radius: 8px; background: #eef3fa; color: #16253d; font-size: 26px; cursor: pointer; }
        .dishes-table .dish-photo { display: block; width: 48px; height: 40px; min-width: 48px; max-width: 48px; min-height: 40px; max-height: 40px; object-fit: contain; border-radius: 8px; }
        .dish-description { margin-top: 4px; color: #71829a; font-size: 13px; }
        .dish-badge { display: inline-flex; padding: 6px 10px; border-radius: 999px; background: #eef5ff; color: #1f5cb8; font-size: 12px; font-weight: 700; }
        .dish-badge.inactive { background: #f0f2f6; color: #697991; }
        .pagination { padding: 18px 24px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .pagination-info { color: #71829a; font-size: 14px; }
        .pagination-links { display: flex; gap: 10px; }
        @media (max-width: 1080px) { .dishes-grid { grid-template-columns: 1fr; } }
    </style>

    <section class="dishes-page">
        <div class="dishes-grid">
            <div class="dishes-card">
                <div class="dishes-card-header">
                    <div class="muted">{{ __('ui.common.home') }}</div>
                    <h1>{{ __('ui.menu.dishes') }}</h1>
                </div>
                <div class="dishes-card-body">
                    <form method="POST" action="{{ route('dishes.store') }}" class="dishes-form" enctype="multipart/form-data">
                        @csrf
                        @if ($errors->any())
                            <div role="alert">{{ $errors->first() }}</div>
                        @endif
                        @if ($schools->isNotEmpty())
                            <div class="field">
                                <label for="school_id">{{ __('admin.labels.school') }}</label>
                                <select id="school_id" name="school_id" required>
                                    <option value="">{{ __('dishes.select_school') }}</option>
                                    @foreach ($schools as $school)
                                        <option value="{{ $school->id }}" @selected((int) old('school_id', $schoolId) === $school->id)>{{ $school->name }}</option>
                                    @endforeach
                                </select>
                                @error('school_id') <span class="field-error" id="school_id-error" role="alert">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <div class="field">
                            <label for="name">{{ __('admin.labels.name') }}</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="255" required aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="name-error">
                            @error('name') <span class="field-error" id="name-error" role="alert">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="category">{{ __('admin.labels.category') }}</label>
                            <select id="category" name="category" required aria-invalid="{{ $errors->has('category') ? 'true' : 'false' }}" aria-describedby="category-error">
                                <option value="">{{ __('dishes.select_category') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ \Illuminate\Support\Facades\Lang::has('dishes.categories.'.$category) ? __('dishes.categories.'.$category) : $category }}</option>
                                @endforeach
                            </select>
                            @error('category') <span class="field-error" id="category-error" role="alert">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="price">{{ __('dishes.price') }}</label>
                            <input id="price" name="price" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('price') }}" required aria-invalid="{{ $errors->has('price') ? 'true' : 'false' }}" aria-describedby="price-error">
                            @error('price') <span class="field-error" id="price-error" role="alert">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="photo">{{ __('dishes.photo') }}</label>
                            <div class="dish-upload">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 16V3m-5 5 5-5 5 5M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/></svg>
                                <img id="dish-upload-preview" alt="{{ __('dishes.photo') }}" hidden>
                                <input class="dish-upload-input" id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-invalid="{{ $errors->has('photo') ? 'true' : 'false' }}" aria-describedby="photo-error photo-hint">
                                <label class="dish-upload-label" for="photo">{{ __('dishes.choose_photo') }}</label>
                                <span class="dish-upload-name" id="dish-upload-name" aria-live="polite">{{ __('dishes.no_photo_selected') }}</span>
                            </div>
                            <small id="photo-hint">{{ __('dishes.photo_hint') }}</small>
                            @error('photo') <span class="field-error" id="photo-error" role="alert">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="description">{{ __('admin.labels.description') }}</label>
                            <textarea id="description" name="description" maxlength="1000" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" aria-describedby="description-error">{{ old('description') }}</textarea>
                            @error('description') <span class="field-error" id="description-error" role="alert">{{ $message }}</span> @enderror
                        </div>
                        <label class="inline-check" for="is_active">
                            <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                            <span>{{ __('admin.labels.active') }}</span>
                        </label>
                        <label class="inline-check" for="is_main">
                            <input id="is_main" name="is_main" type="checkbox" value="1" @checked(old('is_main') === '1')>
                            <span>{{ __('dishes.is_main') }}</span>
                        </label>
                        <div class="dishes-actions">
                            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="dishes-card">
                <div class="dishes-card-header">
                    <h2>{{ __('admin.labels.dishes') }}</h2>
                </div>
                @if (session('status'))
                    <div class="notice">{{ session('status') }}</div>
                @endif
                <div class="dishes-card-body">
                    <form method="GET" action="{{ route('dishes.index') }}" class="dishes-filters">
                        @if ($schools->isNotEmpty())
                            <div class="field">
                                <label for="filter-school">{{ __('admin.labels.school') }}</label>
                                <select id="filter-school" name="school_id">
                                    <option value="">{{ __('dishes.select_school') }}</option>
                                    @foreach ($schools as $school)
                                        <option value="{{ $school->id }}" @selected($schoolId === $school->id)>{{ $school->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="field">
                            <label for="search">{{ __('ui.common.search') }}</label>
                            <input id="search" name="search" type="text" value="{{ $filters['search'] }}" placeholder="{{ __('ui.common.name_or_description') }}">
                        </div>
                        <div class="field">
                            <label for="filter-category">{{ __('admin.labels.category') }}</label>
                            <select id="filter-category" name="category">
                                <option value="">{{ __('ui.common.all') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ \Illuminate\Support\Facades\Lang::has('dishes.categories.'.$category) ? __('dishes.categories.'.$category) : $category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="filter-status">{{ __('admin.labels.status') }}</label>
                            <select id="filter-status" name="is_active">
                                <option value="">{{ __('ui.common.all') }}</option>
                                <option value="1" @selected($filters['is_active'] === '1')>{{ __('ui.common.active') }}</option>
                                <option value="0" @selected($filters['is_active'] === '0')>{{ __('ui.common.inactive') }}</option>
                            </select>
                        </div>
                        <div class="dishes-actions">
                            <button class="btn" type="submit">{{ __('ui.common.filter') }}</button>
                            <a class="btn secondary" href="{{ route('dishes.index', ['school_id' => $schoolId]) }}">{{ __('ui.common.reset') }}</a>
                        </div>
                    </form>
                </div>
                @if ($dishes->isEmpty())
                    <div class="dishes-card-body muted">{{ __('ui.messages.no_dishes_found') }}</div>
                @else
                    <div class="dishes-table-wrap">
                        <table class="dishes-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.labels.name') }}</th>
                                    <th>{{ __('dishes.photo') }}</th>
                                    <th>{{ __('dishes.price') }}</th>
                                    <th>{{ __('dishes.is_main') }}</th>
                                    <th>{{ __('admin.labels.status') }}</th>
                                    <th>{{ __('dishes.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dishes as $dish)
                                    <tr>
                                        <td>
                                            <div class="dish-name">{{ $dish->name }}</div>
                                            <div class="dish-description">{{ $dish->description ?: '-' }}</div>
                                        </td>
                                        <td>
                                            @if ($dish->photo_path)
                                                <button type="button" class="dish-photo-button" data-photo-action="{{ route('dishes.photo', $dish) }}" data-school-id="{{ $dish->school_id }}" aria-label="{{ __('dishes.enlarge_photo', ['name' => $dish->name]) }}" aria-haspopup="dialog" aria-controls="dish-photo-dialog">
                                                    <img class="dish-photo" src="{{ Storage::disk('public')->url($dish->photo_path) }}" alt="{{ $dish->name }}" width="48" height="40" loading="lazy">
                                                </button>
                                            @else
                                                <button type="button" class="dish-photo-button" data-photo-action="{{ route('dishes.photo', $dish) }}" data-school-id="{{ $dish->school_id }}" aria-haspopup="dialog" aria-controls="dish-photo-dialog">{{ __('dishes.add_photo') }}</button>
                                            @endif
                                        </td>
                                        <td>{{ $dish->price !== null ? number_format((float) $dish->price, 2, ',', ' ').' ₸' : '—' }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('dishes.main', $dish) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="school_id" value="{{ $dish->school_id }}">
                                                <input type="hidden" name="is_main" value="0">
                                                <input type="checkbox" name="is_main" value="1" @checked($dish->is_main) aria-label="{{ __('dishes.is_main') }}: {{ $dish->name }}" onchange="this.form.submit()">
                                            </form>
                                        </td>
                                        <td>
                                            <span class="dish-badge {{ $dish->is_active ? '' : 'inactive' }}">
                                                {{ $dish->is_active ? __('ui.common.active') : __('ui.common.inactive') }}
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('dishes.destroy', $dish) }}" data-confirm="{{ __('dishes.delete_confirm') }}" onsubmit="return window.confirm(this.dataset.confirm)">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="school_id" value="{{ $dish->school_id }}">
                                                <button type="submit" class="btn dish-delete">{{ __('dishes.delete') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination">
                        <div class="pagination-info">{{ $dishes->firstItem() }}-{{ $dishes->lastItem() }} / {{ $dishes->total() }}</div>
                        <div class="pagination-links">
                            @if ($dishes->onFirstPage())
                                <span class="btn secondary" aria-disabled="true">{{ __('ui.common.previous') }}</span>
                            @else
                                <a class="btn secondary" href="{{ $dishes->previousPageUrl() }}">{{ __('ui.common.previous') }}</a>
                            @endif
                            @if ($dishes->hasMorePages())
                                <a class="btn" href="{{ $dishes->nextPageUrl() }}">{{ __('ui.common.next') }}</a>
                            @else
                                <span class="btn secondary" aria-disabled="true">{{ __('ui.common.next') }}</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
    <dialog id="dish-photo-dialog" class="dish-photo-dialog" aria-label="{{ __('dishes.photo') }}">
        <button type="button" class="dish-photo-close" aria-label="{{ __('dishes.close') }}" autofocus>&times;</button>
        <img alt="">
        <form id="dish-photo-upload" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="school_id">
            <input type="file" id="dish-photo-file" name="photo" accept="image/jpeg,image/png,image/webp" hidden onchange="if (this.files.length) this.form.requestSubmit()">
            <button type="button" class="btn" onclick="document.getElementById('dish-photo-file').click()">{{ __('dishes.replace_photo') }}</button>
        </form>
    </dialog>
    <script>
        (() => {
            const dialog = document.getElementById('dish-photo-dialog');
            const preview = dialog.querySelector('img');
            const upload = dialog.querySelector('form');
            const creationForm = document.querySelector('.dishes-form');
            const photoInput = creationForm.elements.photo;
            const uploadPreview = document.getElementById('dish-upload-preview');
            let uploadPreviewUrl;
            photoInput.addEventListener('change', () => {
                const file = photoInput.files[0];
                let message = '';
                if (file && file.size > 5 * 1024 * 1024) {
                    message = @json(__('dishes.validation')['photo.max']);
                } else if (file && !/\.(jpe?g|png|webp)$/i.test(file.name)) {
                    message = @json(__('dishes.validation')['photo.mimes']);
                }
                photoInput.setCustomValidity(message);
                document.getElementById('dish-upload-name').textContent = message || file?.name || @json(__('dishes.no_photo_selected'));
                if (uploadPreviewUrl) URL.revokeObjectURL(uploadPreviewUrl);
                uploadPreview.hidden = !file || !!message;
                uploadPreview.removeAttribute('src');
                if (file && !message) {
                    uploadPreviewUrl = URL.createObjectURL(file);
                    uploadPreview.src = uploadPreviewUrl;
                }
                photoInput.reportValidity();
            });

            document.querySelectorAll('.dish-photo-button').forEach(button => {
                button.addEventListener('click', () => {
                    const photo = button.querySelector('img');
                    preview.hidden = !photo;
                    if (photo) {
                        preview.src = photo.currentSrc || photo.src;
                        preview.alt = photo.alt;
                    }
                    upload.action = button.dataset.photoAction;
                    upload.elements.school_id.value = button.dataset.schoolId;
                    upload.elements.photo.value = '';
                    dialog.showModal();
                });
            });

            dialog.querySelector('.dish-photo-close').addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', event => {
                if (event.target === dialog) {
                    const bounds = dialog.getBoundingClientRect();
                    if (event.clientX < bounds.left || event.clientX > bounds.right ||
                        event.clientY < bounds.top || event.clientY > bounds.bottom) {
                        dialog.close();
                    }
                }
            });
            dialog.addEventListener('close', () => {
                preview.removeAttribute('src');
                preview.alt = '';
                upload.reset();
                upload.removeAttribute('action');
            });
        })();
    </script>
@endsection
