<span class="att-avatar">
    <span aria-hidden="true">{{ mb_substr($student?->first_name ?? '', 0, 1) }}{{ mb_substr($student?->last_name ?? '', 0, 1) }}</span>
    @if ($student?->photo)
        <img src="{{ $student->photo_url }}" alt="{{ $student->full_name }}"
            loading="lazy" decoding="async" onerror="this.remove()">
    @endif
</span>
