// How heavy each member page is on a phone with a weak signal: a cold load of each page under Chrome's "Slow 4G"
// emulation, with what it downloads by kind. Run against the demo server: node tests/journeys/measure.mjs
import { createRequire } from 'module';
import { ROOT, BASE, start, phone, pause } from './lib.mjs';

const { PredefinedNetworkConditions } = createRequire(ROOT + '/package.json')('puppeteer');
const PAGES = process.argv.slice(2).length ? process.argv.slice(2) : [
    '/login', '/members/dashboard', '/members/calendar', '/members/notifications', '/members/lodge-of-fraternity/events', '/members/dues',
];

const browser = await start();
const kb = (bytes) => Math.round(bytes / 1024);
console.log('page'.padEnd(42), 'seconds', 'total KB', 'JS KB', 'CSS KB', 'fonts KB', 'requests');
// Signed in once (the sign-in limit is five a minute), then each page loads in a fresh tab of that session.
const member = await phone(browser, 'member@example.com');
for (const path of PAGES) {
    const page = path === '/login' ? await phone(browser, null) : await member.browserContext().newPage();
    if (path !== '/login') {
        await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    }
    const client = await page.createCDPSession();
    await client.send('Network.enable');
    await client.send('Network.clearBrowserCache');
    await client.send('Network.setCacheDisabled', { cacheDisabled: true });
    await client.send('Network.setBypassServiceWorker', { bypass: true });
    const sizes = { total: 0, js: 0, css: 0, font: 0, count: 0 };
    const types = {};
    client.on('Network.responseReceived', (e) => { types[e.requestId] = e.type; });
    client.on('Network.loadingFinished', (e) => {
        sizes.total += e.encodedDataLength; sizes.count++;
        const type = types[e.requestId];
        if (type === 'Script') sizes.js += e.encodedDataLength;
        if (type === 'Stylesheet') sizes.css += e.encodedDataLength;
        if (type === 'Font') sizes.font += e.encodedDataLength;
    });
    await page.emulateNetworkConditions(PredefinedNetworkConditions['Slow 4G']);
    const started = Date.now();
    await page.goto(BASE + path, { waitUntil: 'networkidle0', timeout: 120000 });
    const seconds = ((Date.now() - started) / 1000).toFixed(1);
    await pause(200);
    console.log(path.padEnd(42), seconds.padStart(7), String(kb(sizes.total)).padStart(8), String(kb(sizes.js)).padStart(5), String(kb(sizes.css)).padStart(6), String(kb(sizes.font)).padStart(8), String(sizes.count).padStart(8));
    await (path === '/login' ? page.browserContext().close() : page.close());
}
await browser.close();
