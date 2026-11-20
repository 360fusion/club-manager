// Face ID, fingerprint or security key sign-in on the website (WebAuthn passkeys, Fortify's /passkeys and /user/passkeys
// endpoints). The server sends and takes the standard JSON form, with binary fields as base64url.

const toBytes = (value) => {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(base64.padEnd(Math.ceil(base64.length / 4) * 4, '=')), (char) => char.charCodeAt(0));
};

const toBase64Url = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

const withIds = (list) => (list ?? []).map((item) => ({ ...item, id: toBytes(item.id) }));

function creationOptions(options) {
    return { ...options, challenge: toBytes(options.challenge), user: { ...options.user, id: toBytes(options.user.id) }, excludeCredentials: withIds(options.excludeCredentials) };
}

function requestOptions(options) {
    return { ...options, challenge: toBytes(options.challenge), allowCredentials: withIds(options.allowCredentials) };
}

function credentialJson(credential) {
    const response = credential.response;
    const json = { clientDataJSON: toBase64Url(response.clientDataJSON) };

    if (response.attestationObject) {
        json.attestationObject = toBase64Url(response.attestationObject);
        json.transports = response.getTransports?.() ?? [];
    } else {
        json.authenticatorData = toBase64Url(response.authenticatorData);
        json.signature = toBase64Url(response.signature);
        json.userHandle = response.userHandle ? toBase64Url(response.userHandle) : null;
    }

    return {
        id: credential.id,
        rawId: toBase64Url(credential.rawId),
        type: credential.type,
        response: json,
        clientExtensionResults: credential.getClientExtensionResults?.() ?? {},
        authenticatorAttachment: credential.authenticatorAttachment ?? null,
    };
}

async function send(method, url, body) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? 'That did not work. Try again.');
    }

    return data;
}

export const passkeysSupported = () => typeof window !== 'undefined' && 'PublicKeyCredential' in window && !!navigator.credentials;

/** Signs in with a passkey on this phone or computer and goes to the member area. */
export async function signInWithPasskey(remember = true) {
    const { options } = await send('GET', '/passkeys/login/options');
    const credential = await navigator.credentials.get({ publicKey: requestOptions(options) });
    const { redirect } = await send('POST', '/passkeys/login', { credential: credentialJson(credential), remember });

    // A full page load: signing in changes the routes and shared data the page was built with.
    window.location.assign(redirect || '/members/dashboard');
}

/** Confirms the password (needed before passkeys are added or removed), then saves a new passkey for this device. */
export async function addPasskey(name, password) {
    await send('POST', '/user/confirm-password', { password });
    const { options } = await send('GET', '/user/passkeys/options');
    const credential = await navigator.credentials.create({ publicKey: creationOptions(options) });
    await send('POST', '/user/passkeys', { name, credential: credentialJson(credential) });
}

export async function removePasskey(id, password) {
    await send('POST', '/user/confirm-password', { password });
    await send('DELETE', `/user/passkeys/${id}`);
}
