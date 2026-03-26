@extends('layouts.auth-minimal')

@section('content')
    <style>
        .kitchen-page {
            padding: 8px 0;
        }

        .kitchen-shell {
            display: grid;
            gap: 16px;
        }

        .kitchen-camera-wrap {
            border: 1px solid #dbe4f2;
            border-radius: 20px;
            background: #f8fbff;
            padding: 16px;
            display: grid;
            gap: 16px;
        }

        .kitchen-title {
            padding: 12px 14px;
            border-radius: 12px;
            background: #eef3fb;
            color: #234067;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
        }

        .kitchen-camera-frame {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            background: #0f1a2d;
            min-height: 420px;
        }

        .kitchen-camera-actions {
            display: none;
        }

        .kitchen-camera-switch {
            border: 0;
            border-radius: 12px;
            background: #234067;
            color: #f3f7ff;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .kitchen-camera-switch:disabled {
            opacity: 0.55;
            cursor: wait;
        }

        #kitchen-reader {
            min-height: 420px;
            background: #0f1a2d;
        }

        #kitchen-reader video {
            width: 100% !important;
            height: auto !important;
            object-fit: cover;
            border-radius: 18px;
        }

        #kitchen-reader__scan_region {
            min-height: 420px;
            border: 0 !important;
            background: #0f1a2d;
        }

        #kitchen-reader__dashboard {
            display: none !important;
        }

        .kitchen-camera-overlay {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background: rgba(15, 26, 45, 0.88);
            text-align: center;
            z-index: 2;
        }

        .kitchen-camera-overlay[hidden] {
            display: none;
        }

        .kitchen-camera-message {
            max-width: 420px;
            display: grid;
            gap: 14px;
        }

        .kitchen-camera-text {
            color: #f3f7ff;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.45;
        }

        .kitchen-camera-hint {
            color: rgba(243, 247, 255, 0.76);
            font-size: 13px;
            line-height: 1.5;
        }

        .kitchen-result {
            min-height: 96px;
        }

        .kitchen-result-card {
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid #dbe4f2;
            border-radius: 18px;
            background: #ffffff;
            padding: 14px 16px;
        }

        .kitchen-result-photo,
        .kitchen-result-placeholder {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            flex: 0 0 72px;
        }

        .kitchen-result-photo {
            object-fit: cover;
            background: #d9e6f7;
        }

        .kitchen-result-placeholder {
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #dce8f8 0%, #c8d9f0 100%);
            color: #24487b;
            font-size: 28px;
            font-weight: 800;
        }

        .kitchen-result-name {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.15;
            color: #18365f;
        }

        .kitchen-result-classroom {
            margin-top: 4px;
            color: #6c809c;
            font-size: 15px;
            font-weight: 600;
        }

        .kitchen-result-status {
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .kitchen-result-status.success {
            background: #e8f7ee;
            color: #1b7a46;
        }

        .kitchen-result-status.warning {
            background: #fff2e2;
            color: #a85d00;
        }

        .kitchen-empty {
            min-height: 96px;
        }

        @media (max-width: 720px) {
            .kitchen-camera-actions[data-mobile='true'] {
                display: block;
            }

            .kitchen-camera-frame,
            #kitchen-reader,
            #kitchen-reader__scan_region {
                min-height: 320px;
            }

            .kitchen-result-card {
                padding: 12px;
                gap: 12px;
            }

            .kitchen-result-photo,
            .kitchen-result-placeholder {
                width: 60px;
                height: 60px;
                border-radius: 16px;
                flex-basis: 60px;
            }

            .kitchen-result-name {
                font-size: 20px;
            }
        }
    </style>

    <section class="kitchen-page">
        <div class="kitchen-shell">
            <div class="kitchen-camera-wrap">
                @if (!$school)
                    <div class="error">Получите токен для кухни и откройте страницу по ссылке /kitchen/{token}.</div>
                @else
                    <div class="kitchen-title">QR-сканер</div>
                    <div class="kitchen-camera-actions" id="kitchen-camera-actions" data-mobile="false">
                        <button class="kitchen-camera-switch" id="kitchen-camera-switch" type="button">
                            &#1057;&#1084;&#1077;&#1085;&#1080;&#1090;&#1100; &#1082;&#1072;&#1084;&#1077;&#1088;&#1091;
                        </button>
                    </div>
                    <div class="kitchen-camera-frame">
                        <div id="kitchen-reader"></div>
                        <div class="kitchen-camera-overlay" id="kitchen-camera-overlay">
                            <div class="kitchen-camera-message">
                                <div class="kitchen-camera-text" id="kitchen-camera-text">Запуск камеры...</div>
                                <div class="kitchen-camera-hint" id="kitchen-camera-hint">Наведите камеру на QR ученика.
                                    Если доступ уже разрешен, камера включится автоматически.</div>
                            </div>
                        </div>
                    </div>
                    <div class="kitchen-result" id="kitchen-result"></div>
                @endif
            </div>
        </div>
    </section>

    @if ($school)
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const readerId = 'kitchen-reader';
                const resultBox = document.getElementById('kitchen-result');
                const overlay = document.getElementById('kitchen-camera-overlay');
                const overlayText = document.getElementById('kitchen-camera-text');
                const overlayHint = document.getElementById('kitchen-camera-hint');
                const cameraActions = document.getElementById('kitchen-camera-actions');
                const cameraSwitchButton = document.getElementById('kitchen-camera-switch');
                const csrfToken = @json(csrf_token());
                const scanUrl = @json(route('kitchen.scan'));
                let scanner = null;
                let submitting = false;
                let lastScannedValue = null;
                let lastScannedAt = 0;
                let resultTimer = null;
                let currentFacingMode = 'environment';
                let restartingScanner = false;
                const rescanDelayMs = 1500;
                const isMobileDevice = window.matchMedia('(max-width: 720px)').matches &&
                    /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);

                const logStep = (step, payload = null) => {
                    if (payload === null) {
                        console.log(`[kitchen] ${step}`);
                        return;
                    }

                    console.log(`[kitchen] ${step}`, payload);
                };

                const showOverlay = (title, hint = '') => {
                    logStep('overlay', {
                        title,
                        hint
                    });
                    overlayText.textContent = title;
                    overlayHint.textContent = hint;
                    overlay.hidden = false;
                };

                const hideOverlay = () => {
                    logStep('overlay hidden');
                    overlay.hidden = true;
                };

                const clearResult = () => {
                    if (resultTimer) {
                        clearTimeout(resultTimer);
                        resultTimer = null;
                    }

                    resultBox.innerHTML = '<div class="kitchen-empty"></div>';
                };

                const escapeHtml = (value) => {
                    return String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                };

                const renderTimedResult = (html) => {
                    if (resultTimer) {
                        clearTimeout(resultTimer);
                    }

                    resultBox.innerHTML = html;
                    resultTimer = setTimeout(() => {
                        clearResult();
                    }, 5000);
                };

                const renderResult = (payload) => {
                    const student = payload.student || {};
                    const fullName = student.full_name || '-';
                    const classroom = student.classroom || '';
                    const statusText = payload.created ? '\u0417\u0430\u043a\u0430\u0437 \u0441\u043e\u0437\u0434\u0430\u043d' : '\u0417\u0430\u043a\u0430\u0437 \u0443\u0436\u0435 \u0435\u0441\u0442\u044c';
                    const statusClass = payload.created ? 'success' : 'warning';
                    const initialSource = (student.full_name || '').trim();
                    const initial = initialSource ? initialSource.charAt(0).toUpperCase() : 'U';
                    const photo = student.photo_url ?
                        `<img class="kitchen-result-photo" src="${escapeHtml(student.photo_url)}" alt="${escapeHtml(fullName)}">` :
                        `<div class="kitchen-result-placeholder">${escapeHtml(initial)}</div>`;

                    renderTimedResult(`
                        <div class="kitchen-result-card">
                            ${photo}
                            <div>
                                <div class="kitchen-result-name">${escapeHtml(fullName)}</div>
                                ${classroom ? `<div class="kitchen-result-classroom">${escapeHtml(classroom)}</div>` : ''}
                                <div class="kitchen-result-status ${statusClass}">${escapeHtml(statusText)}</div>
                            </div>
                        </div>
                    `);
                };

                const renderScanError = (message) => {
                    renderTimedResult(`
                        <div class="kitchen-result-card">
                            <div class="kitchen-result-placeholder">!</div>
                            <div>
                                <div class="kitchen-result-name">\u041e\u0448\u0438\u0431\u043a\u0430 \u0441\u043a\u0430\u043d\u0438\u0440\u043e\u0432\u0430\u043d\u0438\u044f.</div>
                                <div class="kitchen-result-classroom">${escapeHtml(message)}</div>
                                <div class="kitchen-result-status warning">\u0421\u043a\u0430\u043d \u043e\u0442\u043a\u043b\u043e\u043d\u0435\u043d</div>
                            </div>
                        </div>
                    `);
                };

                const submitCode = async (studentCode) => {
                    const normalized = (studentCode || '').trim();

                    if (!normalized || submitting) {
                        logStep('submit skipped', {
                            normalized,
                            submitting
                        });
                        return;
                    }

                    submitting = true;
                    logStep('submit start', {
                        normalized
                    });
                    clearResult();

                    try {
                        logStep('request sent', {
                            url: scanUrl,
                            student_code: normalized
                        });
                        const response = await fetch(scanUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                student_code: normalized
                            }),
                        });

                        const payload = await response.json();
                        logStep('response received', {
                            ok: response.ok,
                            status: response.status,
                            payload
                        });

                        if (!response.ok) {
                            throw new Error(payload.message || 'Не удалось обработать QR-код.');
                        }

                        logStep('render result', payload);
                        renderResult(payload);
                    } catch (error) {
                        hideOverlay();
                        renderScanError(error.message || '\u041d\u0435 \u0443\u0434\u0430\u043b\u043e\u0441\u044c \u043e\u0431\u0440\u0430\u0431\u043e\u0442\u0430\u0442\u044c QR-\u043a\u043e\u0434.');
                    } finally {
                        submitting = false;
                    }
                };

                const consumeScannedValue = (rawValue) => {
                    const normalized = (rawValue || '').trim();

                    if (normalized === '') {
                        logStep('decoded empty value');
                        return;
                    }

                    const now = Date.now();

                    if (!submitting && (normalized !== lastScannedValue || now - lastScannedAt > rescanDelayMs)) {
                        logStep('decoded value accepted', {
                            normalized
                        });
                        lastScannedValue = normalized;
                        lastScannedAt = now;
                        void submitCode(normalized);
                        return;
                    }

                    logStep('decoded value ignored', {
                        normalized,
                        submitting,
                        lastScannedValue,
                        age: now - lastScannedAt,
                    });
                };

                const stopScanner = async () => {
                    if (!scanner) {
                        return;
                    }

                    try {
                        if (scanner.isScanning) {
                            await scanner.stop();
                        }
                    } catch (error) {
                        // ignore
                    }
                };

                const updateCameraSwitchVisibility = () => {
                    if (!cameraActions) {
                        return;
                    }

                    cameraActions.dataset.mobile = isMobileDevice ? 'true' : 'false';
                };

                const updateCameraSwitchState = (disabled) => {
                    if (!cameraSwitchButton) {
                        return;
                    }

                    cameraSwitchButton.disabled = disabled;
                };

                const startScanner = async () => {
                    logStep('scanner bootstrap');

                    if (typeof window.Html5Qrcode === 'undefined') {
                        showOverlay(
                            'Сканер QR не загрузился.',
                            'Проверьте интернет или доступ к CDN.',
                        );
                        return;
                    }

                    logStep('html5-qrcode loaded');
                    scanner = new Html5Qrcode(readerId, {
                        verbose: false,
                        formatsToSupport: [
                            Html5QrcodeSupportedFormats.QR_CODE,
                            Html5QrcodeSupportedFormats.DATA_MATRIX,
                        ],
                    });

                    try {
                        logStep('scanner start requested');
                        await scanner.start({
                                facingMode: currentFacingMode,
                            }, {
                                fps: 10,
                                disableFlip: false,
                                rememberLastUsedCamera: true,
                            },
                            (decodedText) => {
                                logStep('decoded callback', {
                                    decodedText
                                });
                                hideOverlay();
                                consumeScannedValue(decodedText);
                            },
                            () => {
                                // keep listening silently
                            }
                        );

                        logStep('scanner started');
                        updateCameraSwitchState(false);
                        hideOverlay();
                    } catch (error) {
                        logStep('scanner start error', {
                            message: error?.message || String(error)
                        });
                        updateCameraSwitchState(false);
                        showOverlay(
                            'Нет доступа к камере или сканер не запустился.',
                            'Разрешите доступ к камере и попробуйте снова. В Firefox сканер должен работать через html5-qrcode.',
                        );
                    }
                };

                const restartScannerWithFacingMode = async () => {
                    if (restartingScanner) {
                        return;
                    }

                    restartingScanner = true;
                    updateCameraSwitchState(true);
                    showOverlay('?????&#1057;&#1084;&#1077;&#1085;&#1080;&#1090;&#1100; &#1082;&#1072;&#1084;&#1077;&#1088;&#1091;...', '?????????, ?????? ???????????????.');

                    try {
                        await stopScanner();
                        await startScanner();
                    } finally {
                        restartingScanner = false;
                    }
                };

                clearResult();
                updateCameraSwitchVisibility();
                logStep('page ready');
                startScanner();

                if (cameraSwitchButton && isMobileDevice) {
                    cameraSwitchButton.addEventListener('click', () => {
                        currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
                        void restartScannerWithFacingMode();
                    });
                }

                window.addEventListener('beforeunload', () => {
                    void stopScanner();
                });
            });
        </script>
    @endif
@endsection
