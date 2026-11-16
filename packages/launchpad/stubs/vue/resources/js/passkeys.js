// WebAuthn with the browser's own API: signIn(remember) and add(name) resolve with the page to go to, or reject with a message to show
export const passkeysSupported = typeof window !== 'undefined' && !!window.PublicKeyCredential;

const toBytes = (s) => Uint8Array.from(atob(s.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - (s.length % 4)) % 4)), (c) => c.charCodeAt(0));
const toText = (b) => btoa(String.fromCharCode(...new Uint8Array(b))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const xsrf = () => decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

async function send(url, method, body) {
    const response = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() }, body: body && JSON.stringify(body), credentials: 'same-origin' });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || 'Something went wrong. Try again.');
    return data;
}

const credential = (c, extra) => ({ id: c.id, rawId: toText(c.rawId), type: c.type, clientExtensionResults: c.getClientExtensionResults(), response: { clientDataJSON: toText(c.response.clientDataJSON), ...extra(c.response) } });
const cancelled = (cancelText) => (e) => {
    throw new Error(e.name === 'NotAllowedError' ? cancelText : e.message);
};

export async function signIn(remember, cancelText) {
    const options = await send('/passkeys/options', 'GET');
    options.challenge = toBytes(options.challenge);
    (options.allowCredentials || []).forEach((c) => (c.id = toBytes(c.id)));
    const c = await navigator.credentials.get({ publicKey: options }).catch(cancelled(cancelText));
    const answer = credential(c, (r) => ({ authenticatorData: toText(r.authenticatorData), signature: toText(r.signature), userHandle: r.userHandle ? toText(r.userHandle) : null }));
    return (await send('/passkeys/login', 'POST', { credential: JSON.stringify(answer), remember })).redirect;
}

export async function add(name, cancelText) {
    const options = await send('/account/passkeys/options', 'POST');
    options.challenge = toBytes(options.challenge);
    options.user.id = toBytes(options.user.id);
    (options.excludeCredentials || []).forEach((c) => (c.id = toBytes(c.id)));
    const c = await navigator.credentials.create({ publicKey: options }).catch(cancelled(cancelText));
    const answer = credential(c, (r) => ({ attestationObject: toText(r.attestationObject), transports: r.getTransports ? r.getTransports() : [] }));
    return (await send('/account/passkeys', 'POST', { name, credential: JSON.stringify(answer) })).redirect;
}
