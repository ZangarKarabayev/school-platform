<form method="GET" id="library-operation-search" class="library-body" style="padding:0">
    <input type="hidden" name="school_id" value="{{ $school->id }}">
    <div class="library-field">
        <label for="mode">{{ __('library.operations') }}</label>
        <select name="mode" id="mode"><option value="issue" @selected(request('mode') !== 'return')>{{ __('library.issue') }}</option><option value="return" @selected(request('mode') === 'return')>{{ __('library.return') }}</option></select>
    </div>
    <input type="hidden" id="student_code" name="student_code" value="{{ request('student_code') }}">
    <input type="hidden" id="barcode" name="barcode" value="{{ request('barcode') }}">
    <div id="selected-book-inputs">
        @if(request('barcodes'))
            @foreach((array) request('barcodes') as $barcode)
                @if($barcode)
                    <input type="hidden" name="barcodes[]" value="{{ $barcode }}">
                @endif
            @endforeach
        @elseif(request('barcode'))
            <input type="hidden" name="barcodes[]" value="{{ request('barcode') }}">
        @endif
    </div>
    @foreach(['student', 'book'] as $kind)
        <div class="library-field">
            <label for="{{ $kind }}-search">{{ __('library.'.$kind) }}</label>
            <input type="search" id="{{ $kind }}-search" autocomplete="off" placeholder="{{ __('library.'.$kind.'_search_hint') }}" value="{{ $kind === 'student' ? $student?->full_name : '' }}" aria-controls="{{ $kind }}-results" @if($kind === 'book' && $stocks && $stocks->isNotEmpty()) autofocus @endif>
            <div id="{{ $kind }}-results" class="library-lookup-results" aria-live="polite"></div>
        </div>
        <button type="button" class="btn secondary" id="{{ $kind }}-qr-button">{{ __('library.qr_'.$kind) }}</button>
    @endforeach
</form>
<dialog id="student-qr-modal" class="library-qr-modal" aria-labelledby="student-qr-title">
    <div class="library-qr-spinner" aria-hidden="true"></div>
    <p id="student-qr-title" role="status">{{ __('library.scan_waiting') }}</p>
    <button type="button" class="btn secondary" id="student-qr-cancel" autofocus>{{ __('ui.common.close') }}</button>
</dialog>
<style>
    .library-lookup-results{display:grid;gap:6px;max-height:240px;overflow:auto}
    .library-lookup-results button{padding:10px 12px;text-align:left;border:1px solid #d1d8e5;border-radius:10px;background:#f7f9fc;color:#16253d;cursor:pointer;font:inherit}
    .library-lookup-results small{display:block;color:#71829a}
    .library-qr-modal{width:min(360px,calc(100% - 40px));box-sizing:border-box;padding:32px 24px;border:0;border-radius:24px;background:#fff;color:#16253d;text-align:center;box-shadow:0 24px 60px rgba(8,19,38,.28)}
    .library-qr-modal::backdrop{background:rgba(10,21,39,.72)}
    .library-qr-modal p{margin:20px 0;line-height:1.5;font-weight:600}
    .library-qr-modal .btn{width:100%}
    .library-qr-spinner{width:44px;height:44px;margin:auto;border:4px solid #dce6f5;border-top-color:#24487b;border-radius:50%;animation:library-qr-spin 1s linear infinite}
    @keyframes library-qr-spin{to{transform:rotate(360deg)}}
    @media(prefers-reduced-motion:reduce){.library-qr-spinner{animation-duration:3s}}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('library-operation-search');
    const endpoint = @json(route('library.lookup'));
    const selectedBooks = new Map();
    const selectedBookInputs = document.getElementById('selected-book-inputs');
    const selectedBooksList = document.getElementById('selected-books-list');
    const initialSelectedBooks = @json(($stocks ?? collect())->values()->map(function ($stock) {
        $detail = __('library.grade').': '.($stock->grade ?? '—').' · '.$stock->barcode;

        return ['value' => $stock->barcode, 'label' => $stock->title, 'detail' => $detail];
    })->all());

    initialSelectedBooks.forEach(item => {
        if (item.value) selectedBooks.set(item.value, { label: item.label, detail: item.detail ?? '' });
    });

    selectedBookInputs.querySelectorAll('input[name="barcodes[]"]').forEach(input => {
        if (input.value && !selectedBooks.has(input.value)) {
            selectedBooks.set(input.value, { label: @json(__('library.book_missing')), detail: input.value });
        }
    });

    function syncSelectedBooks() {
        document.getElementById('barcode').value = '';
        const values = [...selectedBooks.keys()];
        selectedBookInputs.replaceChildren();
        for (const value of values) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'barcodes[]';
            input.value = value;
            selectedBookInputs.append(input);
        }
        selectedBooksList.replaceChildren();
        if (!values.length) {
            selectedBooksList.textContent = @json(__('library.empty'));
            return;
        }
        for (const [barcode, item] of selectedBooks.entries()) {
            const row = document.createElement('div');
            row.style.display = 'flex';
            row.style.justifyContent = 'space-between';
            row.style.gap = '10px';
            row.style.padding = '8px 10px';
            row.style.border = '1px solid #d1d8e5';
            row.style.borderRadius = '10px';
            row.style.background = '#f7f9fc';
            const text = document.createElement('span');
            const title = document.createElement('strong');
            title.textContent = item.label;
            const detail = document.createElement('small');
            detail.textContent = item.detail;
            text.append(title, detail);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                selectedBooks.delete(barcode);
                syncSelectedBooks();
                form.requestSubmit();
            });
            row.append(text, remove);
            selectedBooksList.append(row);
        }
    }

    for (const kind of ['student', 'book']) {
        const input = document.getElementById(`${kind}-search`);
        const value = document.getElementById(kind === 'student' ? 'student_code' : 'barcode');
        const results = document.getElementById(`${kind}-results`);
        let timer, controller, version = 0;
        input.addEventListener('input', () => {
            value.value = '';
            results.replaceChildren();
            clearTimeout(timer);
            controller?.abort();
            const current = ++version;
            const query = input.value.trim();
            if (!query) return;
            timer = setTimeout(async () => {
                controller = new AbortController();
                const url = new URL(endpoint);
                url.search = new URLSearchParams({ school_id: form.elements.school_id.value, type: kind, q: query });
                try {
                    const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('lookup');
                    const items = await response.json();
                    if (current !== version) return;
                    results.replaceChildren();
                    if (!items.length) {
                        results.textContent = @json(__('library.empty'));
                        return;
                    }
                    for (const item of items) {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.textContent = item.label;
                        const detail = document.createElement('small');
                        detail.textContent = item.detail;
                        button.append(detail);
                        button.addEventListener('click', () => {
                            if (kind === 'student') {
                                value.value = item.value;
                                input.value = item.label;
                                results.replaceChildren();
                                form.requestSubmit();
                                return;
                            }
                            selectedBooks.set(item.value, {
                                label: item.label,
                                detail: item.detail ?? '',
                            });
                            syncSelectedBooks();
                            input.value = '';
                            results.replaceChildren();
                            form.requestSubmit();
                        });
                        results.append(button);
                    }
                } catch (error) {
                    if (error.name !== 'AbortError' && current === version) results.textContent = @json(__('library.lookup_error'));
                }
            }, 250);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !value.value) {
                event.preventDefault();
                results.querySelector('button')?.focus();
            }
        });
    }
    syncSelectedBooks();
    const qrModal = document.getElementById('student-qr-modal');
    const qrTitle = document.getElementById('student-qr-title');
    let scanKind = 'student';
    let scannedCode = '';
    let scanTimer;
    let scanBusy = false;
    let scanVersion = 0;
    async function finishScan() {
        clearTimeout(scanTimer);
        const code = scannedCode.trim();
        if (!qrModal.open || !code || scanBusy) return;
        scannedCode = '';
        if (scanKind === 'student') {
            document.getElementById('student_code').value = code;
            qrModal.close();
            form.requestSubmit();
            return;
        }
        scanBusy = true;
        const current = scanVersion;
        const url = new URL(endpoint);
        url.search = new URLSearchParams({ school_id: form.elements.school_id.value, type: 'book', q: code, exact: '1' });
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('lookup');
            const items = await response.json();
            if (!qrModal.open || current !== scanVersion) return;
            const item = items.find(item => item.value === code);
            if (!item) {
                qrTitle.textContent = @json(__('library.book_missing'));
                return;
            }
            selectedBooks.set(item.value, { label: item.label, detail: item.detail ?? '' });
            syncSelectedBooks();
            document.getElementById('barcode').value = '';
            qrModal.close();
            form.requestSubmit();
        } catch (error) {
            if (qrModal.open && current === scanVersion) qrTitle.textContent = @json(__('library.lookup_error'));
        } finally {
            if (current === scanVersion) scanBusy = false;
        }
    }
    for (const kind of ['student', 'book']) {
        document.getElementById(`${kind}-qr-button`).addEventListener('click', () => {
            scanKind = kind;
            scanVersion++;
            scanBusy = false;
            scannedCode = '';
            clearTimeout(scanTimer);
            qrTitle.textContent = kind === 'student' ? @json(__('library.scan_waiting')) : @json(__('library.scan_books_waiting'));
            qrModal.showModal();
        });
    }
    document.getElementById('student-qr-cancel').addEventListener('click', () => {
        qrModal.close();
    });
    qrModal.addEventListener('close', () => {
        clearTimeout(scanTimer);
        scannedCode = '';
        scanVersion++;
        scanBusy = false;
        document.getElementById(`${scanKind}-qr-button`).focus();
    });
    qrModal.addEventListener('keydown', event => {
        if (scanBusy && (event.key === 'Enter' || event.key.length === 1)) {
            event.preventDefault();
            return;
        }
        if (event.key === 'Enter' && scannedCode) {
            event.preventDefault();
            finishScan();
            return;
        }
        if (event.ctrlKey || event.altKey || event.metaKey || event.key.length !== 1) return;
        event.preventDefault();
        scannedCode += event.key;
        clearTimeout(scanTimer);
        scanTimer = setTimeout(finishScan, 500);
    });
    qrModal.addEventListener('click', event => {
        const bounds = qrModal.getBoundingClientRect();
        if (event.target === qrModal && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) qrModal.close();
    });
});
</script>
