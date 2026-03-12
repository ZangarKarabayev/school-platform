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
                            <input id="eds_password" type="password" name="password"
                                placeholder="{{ __('ui.auth.password_placeholder') }}" autocomplete="new-password"
                                required>
                        </div>
                        <div class="field">
                            <label for="eds_password_confirmation">{{ __('ui.common.password_repeat') }} *</label>
                            <input id="eds_password_confirmation" type="password" name="password_confirmation"
                                placeholder="{{ __('ui.common.password_repeat') }}" autocomplete="new-password" required>
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
