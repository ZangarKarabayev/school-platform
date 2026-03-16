@extends('layouts.auth')

@section('content')
    <div style="min-height: calc(100vh - 72px); display: grid; place-items: center;">
        <section class="panel" style="width: 100%; max-width: 760px;">
            <div class="panel-header">
                <h2>{{ __('ui.auth.register_eds_page_title') }}</h2>
            </div>
            <div class="panel-body">
                <p>{{ __('ui.auth.register_eds_page_text') }}</p>

                @if ($errors->has('eds_register'))
                    <div class="error">{{ $errors->first('eds_register') }}</div>
                @endif

                @if ($errors->any())
                    <div class="error">
                        {{ $errors->first('phone') ?? ($errors->first('last_name') ?? ($errors->first('first_name') ?? ($errors->first('password') ?? ($errors->first('signature') ?? $errors->first())))) }}
                    </div>
                @endif

                <form method="POST" action="{{ route('register.eds.complete') }}" id="eds-register-form">
                    @csrf
                    <input type="hidden" name="challenge_id" id="eds_challenge_id"
                        value="{{ old('challenge_id', $edsChallenge['challenge_id']) }}">
                    <input type="hidden" name="signature" id="eds_signature" value="{{ old('signature') }}">
                    @php($edsDataFilled = filled(old('signature')) || filled(old('last_name')) || filled(old('phone')))

                    <div class="note" id="eds-register-status" style="display: none;"></div>

                    <div class="actions" style="justify-content: flex-end;">
                        <button class="btn secondary" type="button"
                            id="fill-eds-data">{{ __('ui.auth.register_eds_pick') }}</button>
                    </div>

                    <div id="eds-register-fields" @unless ($edsDataFilled) style="display: none;" @endunless>
                        <div class="field">
                            <label for="eds_last_name">{{ __('ui.common.last_name') }} *</label>
                            <input id="eds_last_name" name="last_name" value="{{ old('last_name') }}" readonly required>
                        </div>
                        <div class="field">
                            <label for="eds_first_name">{{ __('ui.common.first_name') }} *</label>
                            <input id="eds_first_name" name="first_name" value="{{ old('first_name') }}" readonly required>
                        </div>
                        <div class="field">
                            <label for="eds_middle_name">{{ __('ui.common.middle_name') }}</label>
                            <input id="eds_middle_name" name="middle_name" value="{{ old('middle_name') }}" readonly>
                        </div>
                        <div class="field">
                            <label for="eds_phone">{{ __('ui.common.phone') }} *</label>
                            <input id="eds_phone" name="phone" value="{{ old('phone') }}"
                                placeholder="{{ __('ui.auth.phone_placeholder') }}" inputmode="tel" autocomplete="tel"
                                data-phone-input required>
                        </div>
                        <div class="field">
                            <label for="eds_password">{{ __('ui.common.password') }} *</label>
                            <div class="password-field" data-password-field data-visible="false">
                                <input id="eds_password" type="password" name="password"
                                    placeholder="{{ __('ui.auth.password_placeholder') }}" autocomplete="new-password"
                                    required>
                                <button class="password-toggle" type="button" data-password-toggle
                                    data-show-label="{{ __('ui.common.show_password') }}"
                                    data-hide-label="{{ __('ui.common.hide_password') }}">
                                    <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z" /><circle cx="12" cy="12" r="3" /></svg>
                                    <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18" /><path d="M10.6 10.7a3 3 0 0 0 4.2 4.2" /><path d="M9.9 5.1A10.9 10.9 0 0 1 12 5c6.4 0 10 7 10 7a18.7 18.7 0 0 1-4 4.9" /><path d="M6.6 6.6C4 8.4 2 12 2 12s3.6 6 10 6a10.7 10.7 0 0 0 5.4-1.4" /></svg>
                                </button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="eds_password_confirmation">{{ __('ui.common.password_repeat') }} *</label>
                            <div class="password-field" data-password-field data-visible="false">
                                <input id="eds_password_confirmation" type="password" name="password_confirmation"
                                    placeholder="{{ __('ui.common.password_repeat') }}" autocomplete="new-password" required>
                                <button class="password-toggle" type="button" data-password-toggle
                                    data-show-label="{{ __('ui.common.show_password') }}"
                                    data-hide-label="{{ __('ui.common.hide_password') }}">
                                    <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z" /><circle cx="12" cy="12" r="3" /></svg>
                                    <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18" /><path d="M10.6 10.7a3 3 0 0 0 4.2 4.2" /><path d="M9.9 5.1A10.9 10.9 0 0 1 12 5c6.4 0 10 7 10 7a18.7 18.7 0 0 1-4 4.9" /><path d="M6.6 6.6C4 8.4 2 12 2 12s3.6 6 10 6a10.7 10.7 0 0 0 5.4-1.4" /></svg>
                                </button>
                            </div>
                        </div>

                        <div class="actions">
                            <button class="btn" type="submit" id="register-eds-submit"
                                disabled>{{ __('ui.common.register') }}</button>
                        </div>
                    </div>
                </form>
                <div class="actions">
                    <a class="btn secondary" href="{{ route('register') }}">{{ __('ui.common.back') }}</a>
                </div>
            </div>
        </section>
    </div>

    @include('auth.partials.ncalayer-script')

    <script>
        function showEdsRegisterFields() {
            document.getElementById('eds-register-fields')?.style.removeProperty('display');
            syncEdsSubmitState();
        }

        function setRegisterEdsStatus(message, type = 'note') {
            const node = document.getElementById('eds-register-status');

            if (!node) {
                return;
            }

            node.style.display = '';
            node.className = type;
            node.textContent = message;
        }

        function syncEdsSubmitState() {
            const submit = document.getElementById('register-eds-submit');

            if (!submit) {
                return;
            }

            const requiredFields = [
                document.getElementById('eds_last_name'),
                document.getElementById('eds_first_name'),
                document.getElementById('eds_phone'),
                document.getElementById('eds_password'),
                document.getElementById('eds_password_confirmation'),
            ].filter(Boolean);

            submit.disabled = !requiredFields.every((field) => field.value.trim() !== '');
        }

        document.getElementById('fill-eds-data')?.addEventListener('click', async function() {
            const challenge = @json($edsChallenge['challenge']);
            const challengeId = document.getElementById('eds_challenge_id')?.value;
            const previewUrl = @json(route('eds.preview'));

            try {
                setRegisterEdsStatus(@json(__('ui.auth.register_eds_pending')));

                const {
                    cms
                } = await window.NCALayerBridge.createCmsSignature(challenge);

                if (!cms || typeof cms !== 'string' || cms.trim() === '') {
                    throw new Error(@json(__('ui.auth.register_eds_no_signature')));
                }

                document.getElementById('eds_signature').value = cms;
                const identity = await window.NCALayerBridge.fetchEdsIdentityPreview(previewUrl, challengeId,
                    cms);

                document.getElementById('eds_last_name').value = identity.last_name ?? '';
                document.getElementById('eds_first_name').value = identity.first_name ?? '';
                document.getElementById('eds_middle_name').value = identity.middle_name ?? '';

                showEdsRegisterFields();
                setRegisterEdsStatus(@json(__('ui.auth.register_eds_ready')), 'success');
            } catch (error) {
                let message = error?.message ?? @json(__('ui.auth.register_eds_failed'));

                if (error?.strategy) {
                    message += ` STRATEGY: ${error.strategy}.`;
                }

                if (error?.raw !== undefined) {
                    try {
                        message += ` RAW: ${JSON.stringify(error.raw)}`;
                    } catch (serializationError) {
                        message += ' RAW: [unserializable]';
                    }
                }

                if (!error?.canceledByUser) {
                    setRegisterEdsStatus(message, 'error');
                }
            }
        });

        [
            document.getElementById('eds_phone'),
            document.getElementById('eds_password'),
            document.getElementById('eds_password_confirmation'),
        ].filter(Boolean).forEach((field) => {
            field.addEventListener('input', syncEdsSubmitState);
            field.addEventListener('change', syncEdsSubmitState);
        });

        syncEdsSubmitState();
    </script>
@endsection
