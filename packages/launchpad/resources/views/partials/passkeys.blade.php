{{-- WebAuthn with the browser's own API (no build step): window.lpPasskeys.signIn(remember) and .add(name); each resolves with the page to go to,
     or rejects with a message to show. What has data-passkey stays hidden where the browser cannot use passkeys (also on a page that is not https),
     and what has data-no-passkey shows there instead. --}}
@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(() => {
    const supported = !!window.PublicKeyCredential;
    document.querySelectorAll('[data-passkey]').forEach((el) => { el.hidden = !supported; });
    document.querySelectorAll('[data-no-passkey]').forEach((el) => { el.hidden = supported; });
    if (!supported) return;

    const toBytes = (s) => Uint8Array.from(atob(s.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - s.length % 4) % 4)), (c) => c.charCodeAt(0));
    const toText = (b) => btoa(String.fromCharCode(...new Uint8Array(b))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const send = async (url, method, body) => {
        const response = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: body && JSON.stringify(body), credentials: 'same-origin' });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || @json(__('Something went wrong. Try again.')));
        return data;
    };
    const credential = (c, extra) => ({ id: c.id, rawId: toText(c.rawId), type: c.type, clientExtensionResults: c.getClientExtensionResults(), response: { clientDataJSON: toText(c.response.clientDataJSON), ...extra(c.response) } });
    const cancelled = (e) => { throw new Error(e.name === 'NotAllowedError' ? @json(__('The passkey was cancelled.')) : e.message); };

    window.lpPasskeys = {
        async signIn(remember) {
            const options = await send(@json(Route::has('passkey.options') ? route('passkey.options') : ''), 'GET');
            options.challenge = toBytes(options.challenge);
            (options.allowCredentials || []).forEach((c) => { c.id = toBytes(c.id); });
            const c = await navigator.credentials.get({ publicKey: options }).catch(cancelled);
            const answer = credential(c, (r) => ({ authenticatorData: toText(r.authenticatorData), signature: toText(r.signature), userHandle: r.userHandle ? toText(r.userHandle) : null }));
            return (await send(@json(Route::has('passkey.login') ? route('passkey.login') : ''), 'POST', { credential: JSON.stringify(answer), remember })).redirect;
        },
        async add(name) {
            const options = await send(@json(Route::has('account.passkeys.options') ? route('account.passkeys.options') : ''), 'POST');
            options.challenge = toBytes(options.challenge);
            options.user.id = toBytes(options.user.id);
            (options.excludeCredentials || []).forEach((c) => { c.id = toBytes(c.id); });
            const c = await navigator.credentials.create({ publicKey: options }).catch(cancelled);
            const answer = credential(c, (r) => ({ attestationObject: toText(r.attestationObject), transports: r.getTransports ? r.getTransports() : [] }));
            return (await send(@json(Route::has('account.passkeys.store') ? route('account.passkeys.store') : ''), 'POST', { name, credential: JSON.stringify(answer) })).redirect;
        },
    };
})();
</script>
@endpush
