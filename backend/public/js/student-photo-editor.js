(() => {
    const init = () => {
        const dialog = document.getElementById('student-photo-editor');
        if (!dialog || typeof Cropper === 'undefined') return;
        const image = dialog.querySelector('[data-editor-image]');
        const form = dialog.querySelector('form');
        const save = dialog.querySelector('[data-editor-save]');
        const error = dialog.querySelector('[data-editor-error]');
        const fields = dialog.querySelectorAll('[data-editor-field]');
        let cropper = null;
        let objectUrl = null;
        let loadingImage = null;
        let sourceInput = null;

        const cleanup = () => {
            loadingImage && (loadingImage.onload = loadingImage.onerror = null);
            loadingImage = null;
            cropper?.destroy();
            cropper = null;
            image.removeAttribute('src');
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            if (sourceInput) sourceInput.value = '';
            sourceInput = null;
            form.elements.photo_data.value = '';
        };
        const close = () => dialog.close();
        dialog.addEventListener('close', cleanup);
        dialog.querySelector('[data-editor-cancel]').addEventListener('click', close);
        dialog.addEventListener('click', event => { if (event.target === dialog) close(); });

        const open = (source, action, input = null) => {
            cleanup();
            sourceInput = input;
            error.hidden = true;
            save.disabled = true;
            form.action = action;
            dialog.querySelector('[data-editor-aspect]').value = '1';
            if (source instanceof Blob) {
                objectUrl = URL.createObjectURL(source);
                source = objectUrl;
            }
            if (!dialog.open) dialog.showModal();
            loadingImage = new Image();
            loadingImage.onload = () => {
                image.src = source;
                cropper = new Cropper(image, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    background: true,
                    responsive: true,
                    ready() { save.disabled = false; },
                    crop(event) {
                        fields.forEach(field => {
                            if (document.activeElement !== field) field.value = Math.round(event.detail[field.dataset.editorField] || 0);
                        });
                    },
                });
            };
            loadingImage.onerror = () => { error.hidden = false; };
            loadingImage.src = source;
        };

        // Capture before the existing upload handler, so selecting a file never saves it immediately.
        document.addEventListener('change', event => {
            const input = event.target;
            if (!input.matches('.js-student-photo-file')) return;
            event.stopImmediatePropagation();
            const file = input.files?.[0];
            if (!file) return;
            if (!file.type.startsWith('image/') || file.size > 5 * 1024 * 1024) {
                window.alert(dialog.dataset.invalidFile);
                input.value = '';
                return;
            }
            open(file, input.form.action, input);
        }, true);

        // Route camera captures through the same editor before submitting.
        document.addEventListener('submit', event => {
            if (event.target.id !== 'camera-photo-form') return;
            event.preventDefault();
            event.stopImmediatePropagation();
            const cameraForm = event.target;
            const source = cameraForm.elements.photo_data.value;
            if (!source) return;
            const action = cameraForm.action;
            document.getElementById('camera-close')?.click();
            open(source, action);
        }, true);

        const photoModal = document.getElementById('photo-modal');
        if (photoModal) {
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'btn';
            edit.textContent = dialog.dataset.editLabel;
            edit.dataset.photoCropTrigger = '';
            photoModal.querySelector('.photo-panel-actions')?.prepend(edit);
            const updateButton = () => {
                edit.style.display = document.getElementById('photo-preview-image')?.getAttribute('src') ? '' : 'none';
            };
            new MutationObserver(updateButton).observe(photoModal, { attributes: true, subtree: true, attributeFilter: ['src', 'data-open'] });
            updateButton();
            edit.addEventListener('click', () => {
                const source = document.getElementById('photo-preview-image')?.getAttribute('src');
                if (!source || !photoModal.dataset.updateUrl) return;
                photoModal.dataset.open = 'false';
                open(source, photoModal.dataset.updateUrl);
            });
        }

        dialog.querySelectorAll('[data-editor-action]').forEach(button => button.addEventListener('click', () => {
            if (!cropper) return;
            const data = cropper.getData();
            switch (button.dataset.editorAction) {
                case 'zoom-in': cropper.zoom(0.1); break;
                case 'zoom-out': cropper.zoom(-0.1); break;
                case 'left': cropper.rotate(-90); break;
                case 'right': cropper.rotate(90); break;
                case 'flip-x': cropper.scaleX(-data.scaleX); break;
                case 'flip-y': cropper.scaleY(-data.scaleY); break;
                case 'reset': cropper.reset(); break;
            }
        }));
        fields.forEach(field => field.addEventListener('change', () => {
            if (!cropper || !field.validity.valid || field.value === '') return;
            const value = Number(field.value);
            if (field.dataset.editorField === 'rotate') cropper.rotateTo(value);
            else cropper.setData({ [field.dataset.editorField]: value });
        }));
        dialog.querySelector('[data-editor-aspect]').addEventListener('change', event => {
            cropper?.setAspectRatio(event.target.value === 'free' ? NaN : Number(event.target.value));
        });
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (!cropper || save.disabled) return;
            try {
                const canvas = cropper.getCroppedCanvas({
                    maxWidth: 1600, maxHeight: 1600, fillColor: '#fff', imageSmoothingQuality: 'high',
                });
                if (!canvas?.width || !canvas?.height) throw new Error('Empty crop');
                form.elements.photo_data.value = canvas.toDataURL('image/jpeg', 0.9);
                save.disabled = true;
                form.submit();
            } catch {
                error.hidden = false;
            }
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
