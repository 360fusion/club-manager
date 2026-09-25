// Mirrors App\Support\YouTubeUrl: a link only counts if it parses to a real 11-character video id, so nothing
// else is ever put into an embed URL.

const HOSTS = [
    'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
    'youtube-nocookie.com', 'www.youtube-nocookie.com',
    'youtu.be', 'www.youtu.be',
];

const ID_PATTERN = /^[A-Za-z0-9_-]{11}$/;

/** Seconds from "90", "90s", "1m30s" or "1h2m3s"; null when it is not a time. */
export function parseSeconds(value) {
    const text = String(value ?? '').trim().toLowerCase();

    if (/^\d+$/.test(text)) return parseInt(text, 10);

    const match = text !== '' && text.match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/);

    return match ? (parseInt(match[1] || 0, 10) * 3600) + (parseInt(match[2] || 0, 10) * 60) + parseInt(match[3] || 0, 10) : null;
}

/** Returns { id, start } for a YouTube video link, or null. */
export function parseYouTubeUrl(input) {
    let text = String(input ?? '').trim();

    if (text === '') return null;

    if (/^(www\.|m\.|music\.)?(youtube(-nocookie)?\.com|youtu\.be)\//i.test(text)) {
        text = `https://${text}`;
    }

    let url;
    try {
        url = new URL(text);
    } catch {
        return null;
    }

    if (!['http:', 'https:'].includes(url.protocol) || !HOSTS.includes(url.hostname.toLowerCase())) return null;

    const segments = url.pathname.split('/').filter(Boolean);
    let id = '';

    if (url.hostname.toLowerCase().endsWith('youtu.be')) {
        id = segments[0] || '';
    } else if (segments[0] === 'watch') {
        id = url.searchParams.get('v') || '';
    } else if (['embed', 'shorts', 'live', 'v'].includes(segments[0])) {
        id = segments[1] || '';
    } else {
        return null;
    }

    if (!ID_PATTERN.test(id)) return null;

    const hash = new URLSearchParams(url.hash.replace(/^#/, ''));
    let start = null;

    for (const candidate of [url.searchParams.get('t'), url.searchParams.get('start'), hash.get('t')]) {
        const seconds = candidate === null ? null : parseSeconds(candidate);
        if (seconds !== null) {
            start = seconds;
            break;
        }
    }

    return { id, start, isShort: segments[0] === 'shorts' };
}

/** "1:30" style text for a number of seconds (empty for null/0). */
export function formatTimestamp(seconds) {
    const total = parseInt(seconds, 10);

    if (!total || total < 0) return '';

    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;

    return h > 0
        ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
        : `${m}:${String(s).padStart(2, '0')}`;
}

/** Seconds from "90", "1:30" or "1:02:03" (also accepts "1m30s"); null when empty or invalid. */
export function parseTimestamp(text) {
    const value = String(text ?? '').trim();

    if (value === '') return null;

    if (value.includes(':')) {
        const parts = value.split(':').map((p) => p.trim());
        if (parts.length > 3 || parts.some((p) => !/^\d+$/.test(p))) return null;
        return parts.reduce((total, p) => (total * 60) + parseInt(p, 10), 0);
    }

    return parseSeconds(value);
}

export function youtubeThumbnail(id) {
    return `https://i.ytimg.com/vi/${id}/hqdefault.jpg`;
}

/** The privacy-enhanced embed URL, autoplaying because it is only ever loaded by a click. */
export function youtubeEmbedUrl(id, { start = null, end = null, loop = false, captions = false } = {}) {
    const params = new URLSearchParams({ autoplay: '1', rel: '0', playsinline: '1' });

    if (start) params.set('start', String(start));
    if (end) params.set('end', String(end));
    if (loop) {
        params.set('loop', '1');
        params.set('playlist', id);
    }
    if (captions) params.set('cc_load_policy', '1');

    return `https://www.youtube-nocookie.com/embed/${id}?${params.toString()}`;
}
