<button type="button" class="btn secondary" id="stock-qr-button">{{ __('library.qr_book') }}</button>
<dialog id="stock-qr-modal" class="library-stock-qr" aria-labelledby="stock-qr-title">
    <p id="stock-qr-title">{{ __('library.scan_books_waiting') }}</p>
    <button type="button" class="btn secondary" id="stock-qr-close" autofocus>{{ __('ui.common.close') }}</button>
</dialog>
<style>
    .library-stock-qr{width:min(360px,calc(100% - 40px));box-sizing:border-box;padding:24px;border:0;border-radius:24px;background:#fff;color:#16253d;text-align:center;box-shadow:0 24px 60px rgba(8,19,38,.28)}
    .library-stock-qr::backdrop{background:rgba(10,21,39,.72)}
    .library-stock-qr p{line-height:1.5;font-weight:600}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('stock-qr-button');
    const modal = document.getElementById('stock-qr-modal');
    const barcode = document.getElementById('barcode');
    let code = '', timer;
    function finish() {
        clearTimeout(timer);
        if (!modal.open || !code.trim()) return;
        barcode.value = code.trim();
        barcode.dispatchEvent(new Event('input', { bubbles: true }));
        modal.close();
        document.getElementById('quantity').focus();
    }
    button.addEventListener('click', () => { code = ''; modal.showModal(); });
    document.getElementById('stock-qr-close').addEventListener('click', () => modal.close());
    modal.addEventListener('close', () => { clearTimeout(timer); code = ''; button.focus(); });
    modal.addEventListener('keydown', event => {
        if (event.key === 'Enter' && code) { event.preventDefault(); finish(); return; }
        if (event.ctrlKey || event.altKey || event.metaKey || event.key.length !== 1) return;
        event.preventDefault();
        code += event.key;
        clearTimeout(timer);
        timer = setTimeout(finish, 500);
    });
    modal.addEventListener('paste', event => {
        event.preventDefault();
        code = event.clipboardData.getData('text');
        finish();
    });
});
</script>
