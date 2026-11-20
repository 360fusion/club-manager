// The biggest files one member page downloads, cold, with the service worker bypassed.
import { BASE, start, phone, pause } from './lib.mjs';
const path = process.argv[2] || '/members/dashboard';
const browser = await start();
const member = await phone(browser, 'member@example.com');
const page = await member.browserContext().newPage();
const client = await page.createCDPSession();
await client.send('Network.enable');
await client.send('Network.setCacheDisabled', { cacheDisabled: true });
await client.send('Network.setBypassServiceWorker', { bypass: true });
const urls = {}; const rows = [];
client.on('Network.requestWillBeSent', (e) => { urls[e.requestId] = e.request.url; });
client.on('Network.loadingFinished', (e) => rows.push([e.encodedDataLength, urls[e.requestId]]));
await page.goto(BASE + path, { waitUntil: 'networkidle0' });
await pause(300);
rows.sort((a, b) => b[0] - a[0]);
for (const [bytes, url] of rows.slice(0, 14)) console.log(String(Math.round(bytes / 1024)).padStart(5), 'KB', url.replace(BASE, ''));
console.log('total', Math.round(rows.reduce((t, r) => t + r[0], 0) / 1024), 'KB in', rows.length, 'files');
await browser.close();
