@extends('layouts.auth-minimal')

@section('content')
    <section class="panel">
        <div class="panel-header">
            <h2>{{ __('ui.auth.login_eds_title') }}</h2>
        </div>
        <div class="panel-body">
            <p>{{ __('ui.auth.login_eds_text') }}</p>

            @if ($errors->has('eds_login'))
                <div class="error">{{ $errors->first('eds_login') }}</div>
            @endif

            <form method="POST" action="{{ route('login.eds.verify') }}" id="eds-login-form">
                @csrf
                <input type="hidden" name="challenge_id" value="{{ old('challenge_id', $edsChallenge['challenge_id']) }}">
                <input type="hidden" name="signature" id="login_eds_signature_hidden" value="{{ old('signature') }}">

                <div class="note" id="eds-login-status" style="display: none;"></div>

                <div class="actions">
                    <button class="btn secondary" type="button"
                        id="fill-eds-login-data">{{ __('ui.auth.login_eds_select') }}</button>
                </div>
            </form>
            <div class="actions">
                <a class="btn secondary " href="{{ route('register') }}">{{ __('ui.common.back') }}</a>
            </div>
        </div>
    </section>

    @include('auth.partials.ncalayer-script')

    <script>
        function setLoginEdsStatus(message, type = 'note') {
            const node = document.getElementById('eds-login-status');

            if (!node) {
                return;
            }

            node.style.display = '';
            node.className = type;
            node.textContent = message;
        }

        document.getElementById('fill-eds-login-data')?.addEventListener('click', async function() {
            const challenge = @json($edsChallenge['challenge']);

            try {
                setLoginEdsStatus(@json(__('ui.auth.login_eds_pending')));

                const {
                    cms
                } = await window.NCALayerBridge.createCmsSignature(challenge);

                if (!cms || typeof cms !== 'string' || cms.trim() === '') {
                    throw new Error(@json(__('ui.auth.register_eds_no_signature')));
                }

                document.getElementById('login_eds_signature_hidden').value = cms;
                setLoginEdsStatus(@json(__('ui.auth.login_eds_ready')), 'success');
                document.getElementById('eds-login-form')?.submit();
            } catch (error) {
                const message = error?.message ?? @json(__('ui.auth.login_eds_failed'));

                if (!error?.canceledByUser) {
                    setLoginEdsStatus(message, 'error');
                }
            }
        });
    </script>
@endsection
