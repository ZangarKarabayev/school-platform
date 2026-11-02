@php
    $parentRows = old('parents', old('parents_submitted') ? [] : (isset($student) ? $student->parents->toArray() : []));
@endphp
<style>
    .student-parents { margin-top: 24px; padding-top: 20px; border-top: 1px solid #d1d8e5; }
    .student-parents-title { margin: 0 0 16px; font-size: 16px; font-weight: 700; color: #16253d; }
    .student-parent-row { display: grid; grid-template-columns: minmax(180px, 2fr) repeat(2, minmax(0, 1fr)) auto; gap: 16px; align-items: start; margin-bottom: 16px; }
    .student-parent-remove { margin-top: 24px; min-height: 44px; }
    .student-parent-error { color: #b91c1c; font-size: 13px; }
    @media (max-width: 1000px) {
        .student-parent-row { grid-template-columns: 1fr; }
        .student-parent-remove { margin-top: 0; justify-self: start; }
    }
</style>
<section data-parents class="student-parents" aria-label="{{ __('admin.labels.parents') }}">
    <h3 class="student-parents-title">{{ __('admin.labels.parents') }}</h3>
    <input type="hidden" name="parents_submitted" value="1">
    <div data-parent-rows>
        @foreach ($parentRows as $index => $parent)
            @include('students.parent-fields', ['index' => $index, 'parent' => $parent])
        @endforeach
    </div>
    <template data-parent-template>
        @include('students.parent-fields', ['index' => '__INDEX__', 'parent' => []])
    </template>
    <button class="btn secondary" type="button" data-parent-add>{{ __('admin.labels.add_parent') }}</button>
</section>
<script>
    (() => {
        const container = document.currentScript.previousElementSibling;
        const rows = container.querySelector('[data-parent-rows]');
        const formatPhone = input => {
            let digits = input.value.replace(/\D/g, '');
            if (!digits) { input.value = ''; return; }
            if (digits[0] === '7' || digits[0] === '8') digits = digits.slice(1);
            digits = digits.slice(0, 10);
            let formatted = '+7';
            if (digits.length) formatted += ' (' + digits.slice(0, 3);
            if (digits.length > 3) formatted += ') ' + digits.slice(3, 6);
            if (digits.length > 6) formatted += '-' + digits.slice(6, 8);
            if (digits.length > 8) formatted += '-' + digits.slice(8, 10);
            input.value = formatted;
        };
        rows.querySelectorAll('[data-parent-phone]').forEach(formatPhone);
        rows.addEventListener('input', event => {
            if (!event.target.matches('[data-parent-phone]')) return;
            const input = event.target;
            const cursor = input.selectionStart ?? input.value.length;
            const digitsBeforeCursor = input.value.slice(0, cursor).replace(/\D/g, '').length;
            formatPhone(input);
            let position = 0, count = 0;
            while (position < input.value.length && count < digitsBeforeCursor) {
                if (/\d/.test(input.value[position])) count++;
                position++;
            }
            input.setSelectionRange(position, position);
        });
        let nextIndex = {{ count($parentRows) ? max(array_keys($parentRows)) + 1 : 0 }};
        container.querySelector('[data-parent-add]').addEventListener('click', () => {
            rows.insertAdjacentHTML('beforeend', container.querySelector('template').innerHTML.replaceAll('__INDEX__', nextIndex++));
        });
        rows.addEventListener('click', event => {
            if (event.target.closest('[data-parent-remove]') && window.confirm(@js(__('admin.labels.remove_parent_confirm')))) {
                event.target.closest('[data-parent-row]').remove();
            }
        });
    })();
</script>
