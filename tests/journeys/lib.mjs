// Toolkit for the journey videos: phone-sized pages per person, captions, visible taps, recording in scenes.
// The videos are recorded against the demo server (`demo` in .claude/launch.json, on port 8123, with its own
// club_manager_demo database); run ./record.sh to rebuild the demo and record all of them.
import { createRequire } from 'module';
import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';

export const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..');
const puppeteer = createRequire(ROOT + '/package.json')('puppeteer');
export const BASE = process.env.DEMO_URL || 'http://127.0.0.1:8123';
export const OUT = path.dirname(new URL(import.meta.url).pathname) + '/out';
fs.mkdirSync(OUT, { recursive: true });
export const pause = (ms) => new Promise((r) => setTimeout(r, ms));

export async function start() {
    return puppeteer.launch({ headless: true, executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', args: ['--hide-scrollbars'] });
}

/** A phone for one person, signed in. */
export async function phone(browser, email) {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    await page.setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1');
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'light' }]);
    await page.evaluateOnNewDocument(() => {
        try { localStorage.setItem('superadmin-theme', 'light'); localStorage.setItem('cm-install-tip-dismissed', '1'); } catch {}
    });
    page.on('pageerror', (e) => console.log('pageerror:', e.message.slice(0, 200)));
    if (email) {
        await page.goto(BASE + '/login', { waitUntil: 'networkidle0' });
        await page.$eval('input[type=email]', (e) => { e.value = ''; });
        await page.$eval('input[type=password]', (e) => { e.value = ''; });
        await page.type('input[type=email]', email);
        await page.type('input[type=password]', 'password');
        await page.click('button[type=submit]');
        await page.waitForFunction(() => !location.pathname.startsWith('/login'), { timeout: 15000 });
        await settle(page);
    }
    return page;
}

export async function settle(page, ms = 700) {
    try { await page.waitForNetworkIdle({ idleTime: 300, timeout: 8000 }); } catch {}
    await pause(ms);
}

export async function go(page, url) {
    await page.goto(BASE + url, { waitUntil: 'networkidle0' });
    await settle(page);
}

/** A caption card at the bottom of the screen: who is doing what. */
export async function caption(page, who, text, ms = 2600, { top = false } = {}) {
    await page.evaluate((who, text, top) => {
        let box = document.getElementById('cm-caption');
        if (!box) {
            box = document.createElement('div');
            box.id = 'cm-caption';
            box.style.cssText = 'position:fixed;left:12px;right:12px;bottom:96px;z-index:2147483647;pointer-events:none;background:rgba(15,23,42,.92);color:#fff;border-radius:14px;padding:12px 14px;font:500 15px/1.35 system-ui,-apple-system,sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.3);transition:opacity .3s';
            document.body.appendChild(box);
        }
        box.style.top = top ? '150px' : 'auto';
        box.style.bottom = top ? 'auto' : '96px';
        box.innerHTML = (who ? `<div style="font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#fbbf24;margin-bottom:3px">${who}</div>` : '') + text;
        box.style.opacity = '1';
    }, who, text, top);
    await pause(ms);
}

export async function hideCaption(page) {
    await page.evaluate(() => { const box = document.getElementById('cm-caption'); if (box) box.style.opacity = '0'; });
}

/** Finds an element by its visible text (buttons, links, labels...), scrolls to it and shows a tap on it. */
export async function find(page, text, selector = 'button, a, label, [role=button], summary, [role=switch]') {
    const handle = await page.evaluateHandle((text, selector) => {
        const all = [...document.querySelectorAll(selector)].filter((e) => e.offsetParent !== null || e.getClientRects().length);
        const words = (e) => (e.textContent || '').replace(/\s+/g, ' ').trim();
        return all.find((e) => e.innerText?.trim() === text) || all.find((e) => words(e) === text) || all.find((e) => e.innerText?.trim().startsWith(text)) || all.find((e) => words(e).startsWith(text)) || all.find((e) => (e.getAttribute('aria-label') || '') === text)
            || all.filter((e) => words(e).includes(text)).sort((a, b) => words(a).length - words(b).length)[0] || null;
    }, text, selector);
    const element = handle.asElement();
    if (!element) throw new Error(`Nothing on ${page.url()} reads "${text}"`);
    return element;
}

export async function tap(page, textOrElement, selector) {
    const element = typeof textOrElement === 'string' ? await find(page, textOrElement, selector) : textOrElement;
    await element.evaluate((e) => e.scrollIntoView({ block: 'center', behavior: 'smooth' }));
    await pause(500);
    const box = await element.boundingBox();
    if (box) {
        await page.evaluate((x, y) => {
            const dot = document.createElement('div');
            dot.style.cssText = `position:fixed;left:${x - 22}px;top:${y - 22}px;width:44px;height:44px;border-radius:50%;background:rgba(251,191,36,.55);border:2px solid rgba(251,191,36,.95);z-index:2147483646;pointer-events:none;transition:transform .45s,opacity .45s`;
            document.body.appendChild(dot);
            requestAnimationFrame(() => { dot.style.transform = 'scale(1.5)'; dot.style.opacity = '0'; });
            setTimeout(() => dot.remove(), 600);
        }, box.x + box.width / 2, box.y + box.height / 2);
        await pause(350);
    }
    await element.click();
    await settle(page);
}

/** The form control a label names. */
export async function field(page, label) {
    const handle = await page.evaluateHandle((label) => {
        const labels = [...document.querySelectorAll('label')].filter((l) => l.offsetParent !== null);
        const match = labels.find((l) => l.innerText.replace('*', '').trim() === label) || labels.find((l) => l.innerText.trim().startsWith(label));
        if (!match) {
            return [...document.querySelectorAll('input,textarea,select')].find((e) => e.offsetParent !== null && (e.placeholder === label || e.getAttribute('aria-label') === label)) || null;
        }
        return match.control || (match.htmlFor && document.getElementById(match.htmlFor)) || match.querySelector('input,select,textarea') || match.parentElement.querySelector('input,select,textarea');
    }, label);
    const element = handle.asElement();
    if (!element) throw new Error(`No field labelled "${label}" on ${page.url()}`);
    return element;
}

export async function fill(page, label, value, { clear = true } = {}) {
    const element = await field(page, label);
    await element.evaluate((e) => e.scrollIntoView({ block: 'center', behavior: 'smooth' }));
    await pause(300);
    const type = await element.evaluate((e) => e.type);
    if (['date', 'time', 'datetime-local', 'select-one'].includes(type)) {
        await element.evaluate((e, v) => { e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); e.dispatchEvent(new Event('change', { bubbles: true })); }, value);
    } else {
        if (clear) await element.evaluate((e) => { e.value = ''; e.dispatchEvent(new Event('input', { bubbles: true })); });
        await element.type(value, { delay: 35 });
    }
    await pause(250);
}

export async function shot(page, name) {
    await page.screenshot({ path: `${OUT}/${name}.png` });
}

/** Records a scene of one page; returns the file. */
export async function scene(page, name, fn) {
    const file = `${OUT}/${name}.webm`;
    const recorder = await page.screencast({ path: file });
    try {
        await fn();
        await pause(600);
    } finally {
        await recorder.stop();
    }
    return file;
}

/** A title card between scenes, recorded like any other. */
export async function card(browser, name, title, line, ms = 2600) {
    const page = await (await browser.createBrowserContext()).newPage();
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
    await page.setContent(`<body style="margin:0;height:100vh;display:flex;flex-direction:column;justify-content:center;align-items:center;background:#17264d;color:#fff;font-family:system-ui,-apple-system,sans-serif;text-align:center;padding:0 28px;box-sizing:border-box"><div style="width:44px;height:3px;background:#fbbf24;border-radius:2px;margin-bottom:18px"></div><div style="font-size:27px;font-weight:700;line-height:1.2">${title}</div><div style="font-size:16px;opacity:.85;margin-top:12px;line-height:1.4">${line}</div><div style="position:fixed;left:0;bottom:0;height:4px;background:#fbbf24;animation:grow ${ms}ms linear forwards;width:0"></div><style>@keyframes grow{to{width:100%}}</style></body>`);
    const file = await scene(page, name, () => pause(ms));
    await page.close();
    return file;
}

/** Joins scenes into one phone-sized MP4. */
export function join(files, out) {
    const list = `${OUT}/${path.basename(out)}.txt`;
    fs.writeFileSync(list, files.map((f) => `file '${f}'`).join('\n'));
    execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', list, '-vf', 'scale=540:-2,fps=30', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-crf', '28', '-movflags', '+faststart', out]);
    return out;
}

/** Picks a dropdown option by its text; `nth` picks among several dropdowns with the same label (the booker's, a guest's...). */
export async function choose(page, label, optionText, nth = 0) {
    const handle = await page.evaluateHandle((label, nth) => {
        const selects = [...document.querySelectorAll('select')].filter((s) => s.offsetParent !== null && (s.labels?.[0]?.innerText.trim().startsWith(label) || s.getAttribute('aria-label') === label));
        return selects[nth] || null;
    }, label, nth);
    const select = handle.asElement();
    if (!select) throw new Error(`No dropdown "${label}" #${nth} on ${page.url()}`);
    await select.evaluate((e) => e.scrollIntoView({ block: 'center', behavior: 'smooth' }));
    await pause(400);
    await select.evaluate((e, text) => {
        const option = [...e.options].find((o) => o.textContent.trim() === text);
        e.value = option.value;
        e.dispatchEvent(new Event('change', { bubbles: true }));
    }, optionText);
    await pause(450);
}
