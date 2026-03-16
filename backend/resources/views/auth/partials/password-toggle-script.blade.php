<script>
    (function() {
        const wrappers = document.querySelectorAll('[data-password-field]');

        wrappers.forEach((wrapper) => {
            const input = wrapper.querySelector('input');
            const button = wrapper.querySelector('[data-password-toggle]');

            if (!input || !button) {
                return;
            }

            const showLabel = button.dataset.showLabel || 'Show password';
            const hideLabel = button.dataset.hideLabel || 'Hide password';

            const syncState = () => {
                const isVisible = input.type === 'text';
                button.setAttribute('aria-label', isVisible ? hideLabel : showLabel);
                button.setAttribute('title', isVisible ? hideLabel : showLabel);
                button.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
                wrapper.dataset.visible = isVisible ? 'true' : 'false';
            };

            button.addEventListener('click', () => {
                input.type = input.type === 'password' ? 'text' : 'password';
                syncState();
            });

            syncState();
        });
    })();
</script>