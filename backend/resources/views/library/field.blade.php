<div class="library-field {{ $wide ?? false ? 'wide' : '' }}">
    <label for="{{ $name }}">{{ __('library.'.($label ?? $name)) }}{{ ($required ?? false) ? ' *' : '' }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" value="{{ old($name, $value ?? '') }}" @required($required ?? false) @if(isset($min)) min="{{ $min }}" @endif @if(isset($max)) max="{{ $max }}" @endif @if(isset($maxlength)) maxlength="{{ $maxlength }}" @endif>
</div>
