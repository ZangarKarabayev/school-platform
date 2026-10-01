<div class="library-field">
    <label for="{{ $name }}">{{ __('library.'.$name) }}</label>
    <select id="{{ $name }}" name="{{ $name }}">
        <option value="">{{ __('library.choose') }}</option>
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>
