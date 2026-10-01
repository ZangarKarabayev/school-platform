<input type="search" id="search" @if(!request('barcode')) name="search" @endif value="{{ $filterBook ? $filterBook->title.' · '.$filterBook->barcode : request('search', request('barcode')) }}" autocomplete="off" placeholder="{{ __('library.book_search_hint') }}" aria-controls="filter-book-results" @disabled(!$school)>
<input type="hidden" id="filter-book-barcode" name="barcode" value="{{ request('barcode') }}">
<div id="filter-book-results" class="library-filter-results" aria-live="polite" hidden></div>
<style>
    .library-filter-results{display:grid;gap:6px;max-height:240px;overflow:auto}
    .library-filter-results[hidden]{display:none}
    .library-filter-results button{padding:10px 12px;text-align:left;border:1px solid #d1d8e5;border-radius:10px;background:#f7f9fc;color:#16253d;cursor:pointer;font:inherit}
    .library-filter-results small{display:block;color:#71829a}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('search');
    const barcode = document.getElementById('filter-book-barcode');
    const results = document.getElementById('filter-book-results');
    const form = input.form;
    let timer, controller, version = 0;
    input.addEventListener('input', () => {
        barcode.value = '';
        input.name = 'search';
        clearTimeout(timer);
        controller?.abort();
        const current = ++version;
        results.replaceChildren();
        results.hidden = true;
        const query = input.value.trim();
        if (!query) return;
        timer = setTimeout(async () => {
            controller = new AbortController();
            const url = new URL(@json(route('library.lookup')));
            url.search = new URLSearchParams({ school_id: form.elements.school_id.value, type: 'book', q: query });
            try {
                const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('lookup');
                const items = await response.json();
                if (current !== version) return;
                results.replaceChildren();
                results.hidden = false;
                if (!items.length) results.textContent = @json(__('library.empty'));
                for (const item of items) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = item.label;
                    const detail = document.createElement('small');
                    detail.textContent = item.detail;
                    button.append(detail);
                    button.addEventListener('click', () => {
                        barcode.value = item.value;
                        input.value = `${item.label} · ${item.value}`;
                        input.removeAttribute('name');
                        results.hidden = true;
                        results.replaceChildren();
                        form.requestSubmit();
                    });
                    results.append(button);
                }
            } catch (error) {
                if (error.name !== 'AbortError' && current === version) {
                    results.textContent = @json(__('library.lookup_error'));
                    results.hidden = false;
                }
            }
        }, 250);
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' && !results.hidden) {
            event.preventDefault();
            results.querySelector('button')?.focus();
        }
        if (event.key === 'Escape') results.hidden = true;
    });
    results.addEventListener('keydown', event => {
        const buttons = [...results.querySelectorAll('button')];
        const index = buttons.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            buttons[index + (event.key === 'ArrowDown' ? 1 : -1)]?.focus();
        }
        if (event.key === 'Escape') {
            results.hidden = true;
            input.focus();
        }
    });
});
</script>
