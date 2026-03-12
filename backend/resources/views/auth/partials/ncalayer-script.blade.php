<script>
    const NCA_LAYER_MESSAGES = {{ \Illuminate\Support\Js::from([
        'connect_failed' => __('ui.ncalayer.connect_failed'),
        'not_connected' => __('ui.ncalayer.not_connected'),
        'timeout' => __('ui.ncalayer.timeout'),
        'interaction_error' => __('ui.ncalayer.interaction_error'),
        'connection_closed' => __('ui.ncalayer.connection_closed'),
        'empty_error' => __('ui.ncalayer.empty_error'),
        'invalid_response' => __('ui.ncalayer.invalid_response', ['message' => ':message']),
        'cannot_connect_url' => __('ui.ncalayer.cannot_connect_url', ['url' => ':url']),
        'unavailable_url' => __('ui.ncalayer.unavailable_url', ['url' => ':url']),
        'no_cms' => __('ui.ncalayer.no_cms'),
        'preview_failed' => __('ui.ncalayer.preview_failed'),
    ]) }};

    class NcaLayerError extends Error {
        constructor(message, canceledByUser = false) {
            super(message);
            this.name = 'NcaLayerError';
            this.canceledByUser = canceledByUser;
        }
    }

    class NcaLayerClient {
        constructor(urls = ['wss://127.0.0.1:13579', 'wss://127.0.0.1:13580']) {
            this.urls = urls;
            this.wsConnection = null;
        }

        async connect() {
            let lastError = null;

            for (const url of this.urls) {
                try {
                    await this.connectToUrl(url);
                    return;
                } catch (error) {
                    lastError = error;
                }
            }

            throw lastError ?? new NcaLayerError(NCA_LAYER_MESSAGES.connect_failed);
        }

        disconnect() {
            if (this.wsConnection && this.wsConnection.readyState === WebSocket.OPEN) {
                this.wsConnection.close();
            }
        }

        async getActiveTokens() {
            return this.request({
                module: 'kz.gov.pki.knca.commonUtils',
                method: 'getActiveTokens',
            });
        }

        async basicsSignCMS(allowedStorages, data, signingParams, signerParams, locale = 'ru') {
            return this.request({
                module: 'kz.gov.pki.knca.basics',
                method: 'sign',
                args: {
                    allowedStorages,
                    format: 'cms',
                    data,
                    signingParams,
                    signerParams,
                    locale,
                },
            });
        }

        async createCMSSignatureFromBase64(storageType, data, keyType = 'SIGNATURE', attach = true) {
            return this.request({
                module: 'kz.gov.pki.knca.commonUtils',
                method: 'createCMSSignatureFromBase64',
                args: [storageType, keyType, data, attach],
            });
        }

        request(payload) {
            if (!this.wsConnection || this.wsConnection.readyState !== WebSocket.OPEN) {
                throw new NcaLayerError(NCA_LAYER_MESSAGES.not_connected);
            }

            return new Promise((resolve, reject) => {
                let settled = false;
                let timeoutId = null;
                const cleanup = () => {
                    settled = true;

                    if (timeoutId !== null) {
                        clearTimeout(timeoutId);
                    }
                };

                timeoutId = setTimeout(() => {
                    if (!settled) {
                        cleanup();
                        reject(new NcaLayerError(NCA_LAYER_MESSAGES.timeout));
                    }
                }, 120000);

                this.wsConnection.onerror = () => {
                    if (!settled) {
                        cleanup();
                        reject(new NcaLayerError(NCA_LAYER_MESSAGES.interaction_error));
                    }
                };

                this.wsConnection.onclose = () => {
                    if (!settled) {
                        cleanup();
                        reject(new NcaLayerError(NCA_LAYER_MESSAGES.connection_closed));
                    }
                };

                this.wsConnection.onmessage = (event) => {
                    if (settled) {
                        return;
                    }

                    try {
                        const response = JSON.parse(event.data);

                        if (Object.prototype.hasOwnProperty.call(response, 'status')) {
                            if (!response.status) {
                                const errorText = [
                                    response.code,
                                    response.message,
                                    response.details,
                                    response.body?.message,
                                    response.body?.details,
                                ].filter((value) => typeof value === 'string' && value.trim() !== '').join(': ');

                                cleanup();
                                reject(new NcaLayerError(errorText || NCA_LAYER_MESSAGES.empty_error));
                                return;
                            }

                            if (this.isPendingNcLayerResponse(response)) {
                                return;
                            }

                            cleanup();
                            resolve(response);
                            return;
                        }

                        if (Object.prototype.hasOwnProperty.call(response, 'code') && String(response.code) !== '200') {
                            const errorText = [
                                response.code,
                                response.message,
                                response.details,
                            ].filter((value) => typeof value === 'string' && value.trim() !== '').join(': ');

                            cleanup();
                            reject(new NcaLayerError(errorText || NCA_LAYER_MESSAGES.empty_error));
                            return;
                        }

                        if (this.isPendingNcLayerResponse(response)) {
                            return;
                        }

                        cleanup();
                        resolve(response.responseObject);
                    } catch (error) {
                        cleanup();
                        reject(new NcaLayerError(NCA_LAYER_MESSAGES.invalid_response.replace(':message', error.message)));
                    }
                };

                this.wsConnection.send(JSON.stringify(payload));
            });
        }

        isPendingNcLayerResponse(response) {
            const body = response?.body;

            if (Array.isArray(body) && body.length === 0) {
                return true;
            }

            if (Array.isArray(body?.result) && body.result.length === 0) {
                return true;
            }

            if (Array.isArray(response?.responseObject) && response.responseObject.length === 0) {
                return true;
            }

            return false;
        }

        connectToUrl(url) {
            return new Promise((resolve, reject) => {
                const socket = new WebSocket(url);
                const cleanup = () => {
                    socket.onopen = null;
                    socket.onerror = null;
                    socket.onclose = null;
                };

                socket.onopen = () => {
                    cleanup();
                    this.wsConnection = socket;
                    resolve();
                };

                socket.onerror = () => {
                    cleanup();

                    try {
                        socket.close();
                    } catch (error) {
                        /* ignore */
                    }

                    reject(new NcaLayerError(NCA_LAYER_MESSAGES.cannot_connect_url.replace(':url', url)));
                };

                socket.onclose = () => {
                    cleanup();
                    reject(new NcaLayerError(NCA_LAYER_MESSAGES.unavailable_url.replace(':url', url)));
                };
            });
        }
    }

    async function signChallengeWithNcLayer(client, challenge, preferredStorageType = null) {
        const base64Challenge = btoa(unescape(encodeURIComponent(challenge)));
        let activeTokens = [];

        try {
            activeTokens = await client.getActiveTokens();
        } catch (tokenError) {
            activeTokens = [];
        }

        const storageType = preferredStorageType || activeTokens?.[0] || 'PKCS12';

        try {
            const cms = await client.createCMSSignatureFromBase64(storageType, base64Challenge, 'SIGNATURE', true);

            return {
                storageType,
                cms,
                strategy: 'commonUtils',
            };
        } catch (error) {
            const allowedStorages = preferredStorageType ? [preferredStorageType] : null;
            const cms = await client.basicsSignCMS(
                allowedStorages,
                base64Challenge,
                {
                    decode: true,
                    encapsulate: true,
                    digested: false,
                },
                {
                    extKeyUsageOids: [],
                },
                document.documentElement.lang?.startsWith('kk') ? 'kk' : 'ru',
            );

            return {
                storageType: allowedStorages?.[0] ?? storageType,
                cms,
                strategy: 'basics',
            };
        }
    }

    function extractCmsValue(payload) {
        if (typeof payload === 'string' && payload.trim() !== '') {
            return payload.trim();
        }

        if (Array.isArray(payload)) {
            for (const item of payload) {
                const extracted = extractCmsValue(item);

                if (extracted) {
                    return extracted;
                }
            }

            return '';
        }

        if (!payload || typeof payload !== 'object') {
            return '';
        }

        const directCandidates = [
            payload.body?.result,
            payload.body?.cms,
            payload.body?.cmsSignature,
            payload.body?.signature,
            payload.body?.signedData,
            payload.result,
            payload.cms,
            payload.cmsSignature,
            payload.signature,
            payload.signedData,
            payload.responseObject,
        ];

        for (const candidate of directCandidates) {
            if (typeof candidate === 'string' && candidate.trim() !== '') {
                return candidate.trim();
            }
        }

        if (payload.result && typeof payload.result === 'object') {
            return extractCmsValue(payload.result);
        }

        if (payload.body && typeof payload.body === 'object') {
            return extractCmsValue(payload.body);
        }

        if (payload.responseObject && typeof payload.responseObject === 'object') {
            return extractCmsValue(payload.responseObject);
        }

        return '';
    }

    async function createCmsSignature(challengeForSelection, preferredStorageType = null) {
        const client = new NcaLayerClient();

        try {
            await client.connect();
            const result = await signChallengeWithNcLayer(client, challengeForSelection, preferredStorageType);
            const cms = extractCmsValue(result.cms);

            if (!cms) {
                const error = new NcaLayerError(NCA_LAYER_MESSAGES.no_cms);
                error.raw = result;
                error.strategy = result.strategy ?? null;
                throw error;
            }

            return {
                ...result,
                cms,
                raw: result.cms,
            };
        } finally {
            client.disconnect();
        }
    }

    async function fetchEdsIdentityPreview(previewUrl, challengeId, signature) {
        const response = await fetch(previewUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                challenge_id: challengeId,
                signature,
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new NcaLayerError(payload.message ?? NCA_LAYER_MESSAGES.preview_failed);
        }

        return payload;
    }

    window.NCALayerBridge = {
        NcaLayerError,
        createCmsSignature,
        fetchEdsIdentityPreview,
        extractCmsValue,
    };
</script>
