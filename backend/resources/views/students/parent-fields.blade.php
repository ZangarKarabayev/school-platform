<div data-parent-row class="student-parent-row">
    <div class="{{ $parentFieldClass ?? 'student-edit-field' }}">
        <label for="parent-{{ $index }}-full-name">{{ __('admin.labels.parent_full_name') }}</label>
        <input id="parent-{{ $index }}-full-name" name="parents[{{ $index }}][full_name]" value="{{ $parent['full_name'] ?? '' }}" type="text" maxlength="255" autocomplete="name">
        @error("parents.$index.full_name") <span class="student-parent-error" role="alert">{{ $message }}</span> @enderror
    </div>
    <div class="{{ $parentFieldClass ?? 'student-edit-field' }}">
        <label for="parent-{{ $index }}-iin">{{ __('admin.labels.iin') }}</label>
        <input id="parent-{{ $index }}-iin" name="parents[{{ $index }}][iin]" value="{{ $parent['iin'] ?? '' }}" type="text" inputmode="numeric" maxlength="12" pattern="[0-9]{12}">
        @error("parents.$index.iin") <span class="student-parent-error" role="alert">{{ $message }}</span> @enderror
    </div>
    <div class="{{ $parentFieldClass ?? 'student-edit-field' }}">
        <label for="parent-{{ $index }}-phone">{{ __('admin.labels.phone') }}</label>
        <input id="parent-{{ $index }}-phone" name="parents[{{ $index }}][phone]" value="{{ $parent['phone'] ?? '' }}" type="tel" maxlength="18" inputmode="tel" autocomplete="tel" placeholder="+7 (777) 777-77-77" pattern="[+]7 [(][0-9]{3}[)] [0-9]{3}-[0-9]{2}-[0-9]{2}" data-parent-phone required>
        @error("parents.$index.phone") <span class="student-parent-error" role="alert">{{ $message }}</span> @enderror
    </div>
    <button class="btn secondary student-parent-remove" type="button" data-parent-remove>{{ __('admin.labels.remove_parent') }}</button>
</div>
