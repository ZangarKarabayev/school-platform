<div id="library-edit-error" class="library-notice library-error" role="alert" hidden></div>
<script>
(() => {
    let loading = false;
    const errorNotice = document.getElementById('library-edit-error');
    document.querySelectorAll('[data-library-edit]').forEach(trigger => {
        trigger.addEventListener('click', async event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            if (loading) return;
            loading = true;
            errorNotice.hidden = true;
            trigger.setAttribute('aria-busy', 'true');
            try {
                const response = await fetch(trigger.href, { headers: { Accept: 'text/html' } });
                if (!response.ok || response.redirected) throw new Error('edit');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const source = page.getElementById('library-edit-modal');
                if (!source) throw new Error('edit');
                const modal = document.importNode(source, true);
                // Keep the create and edit forms' label targets unique.
                modal.querySelectorAll('[id]').forEach(element => {
                    const previous = element.id;
                    element.id = `catalog-edit-${previous}`;
                    modal.querySelectorAll('label').forEach(label => {
                        if (label.htmlFor === previous) label.htmlFor = element.id;
                    });
                    if (modal.getAttribute('aria-labelledby') === previous) {
                        modal.setAttribute('aria-labelledby', element.id);
                    }
                });
                errorNotice.parentElement.append(modal);
                modal.querySelector('#catalog-edit-library-edit-close').addEventListener('click', () => modal.close());
                modal.addEventListener('close', () => {
                    modal.remove();
                    trigger.focus();
                });
                modal.addEventListener('click', event => {
                    const bounds = modal.getBoundingClientRect();
                    if (event.target === modal && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) modal.close();
                });
                const form = modal.querySelector('form');
                const errors = document.createElement('div');
                errors.className = 'library-notice library-error';
                errors.setAttribute('role', 'alert');
                errors.hidden = true;
                form.prepend(errors);
                form.addEventListener('submit', async event => {
                    event.preventDefault();
                    const button = form.querySelector('button[type="submit"], button:not([type])');
                    if (button.disabled) return;
                    button.disabled = true;
                    errors.hidden = true;
                    try {
                        const result = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { Accept: 'application/json' },
                        });
                        if (result.status === 422) {
                            const data = await result.json();
                            const list = document.createElement('ul');
                            for (const message of Object.values(data.errors ?? {}).flat()) {
                                const item = document.createElement('li');
                                item.textContent = message;
                                list.append(item);
                            }
                            errors.replaceChildren(list);
                            errors.hidden = false;
                            return;
                        }
                        if (!result.ok || result.redirected) throw new Error('save');
                        const data = await result.json();
                        if (!data.saved) throw new Error('save');
                        window.location.reload();
                    } catch (error) {
                        errors.textContent = @json(__('library.edit_error'));
                        errors.hidden = false;
                    } finally {
                        button.disabled = false;
                    }
                });
                modal.showModal();
            } catch (error) {
                errorNotice.textContent = @json(__('library.edit_error'));
                errorNotice.hidden = false;
            } finally {
                loading = false;
                trigger.removeAttribute('aria-busy');
            }
        });
    });
})();
</script>
