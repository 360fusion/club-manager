// Journey: dues reminders, a member paying by bank, and the Treasurer matching it. DRY=1 takes screenshots.
import fs from 'fs';
import { execFileSync } from 'child_process';
import { ROOT, start, phone, go, tap, find, caption, shot, settle, pause, scene, card, join, OUT } from './lib.mjs';

const DRY = process.env.DRY === '1';
const browser = await start();
const files = [];
const step = async (page, name, fn) => {
    if (DRY) { await fn(); await shot(page, name); console.log('ok', name); } else { files.push(await scene(page, name, fn)); }
};
const title = async (name, heading, line) => { if (!DRY) files.push(await card(browser, name, heading, line)); };
const scrollTo = async (page, text) => { const e = await find(page, text, 'h1,h2,h3,h4,p,button,a,label,strong,summary,th,td,span,li,div'); await e.evaluate((x) => x.scrollIntoView({ block: 'center', behavior: 'smooth' })); await pause(900); };
const artisan = (...args) => execFileSync('php', ['artisan', ...args], { cwd: ROOT, env: { ...process.env, DB_DATABASE: 'club_manager_demo', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'log', APP_URL: 'http://127.0.0.1:8123' } }).toString();

await title('d0', 'Dues, without the chasing', 'Reminders go out by themselves; members pay by bank; the Treasurer ticks them off.');

const treasurer = await phone(browser, 'treasurer@example.com');
await go(treasurer, '/members/lodge-of-fraternity/accounts/dues');
await step(treasurer, 'd1', async () => {
    await caption(treasurer, 'WBro Jordan Lee, Treasurer', 'Everyone\'s dues for the year, those behind or due first.');
    await caption(treasurer, 'WBro Jordan Lee, Treasurer', 'He turns on reminders. They go by email and in the app, at most every four weeks.', 2400);
    await tap(treasurer, 'Send dues reminders', '[role=switch], button, label');
    await settle(treasurer, 1000);
    await pause(1200);
});

// The daily 9:15 run, done now for the demo.
console.log(artisan('app:send-dues-reminders').trim());
await title('d2', 'The next morning', 'Bro Thomas\'s dues are due in ten days, so he gets a reminder.');

const tom = await phone(browser, 'member@example.com');
await go(tom, '/members/notifications');
await step(tom, 'd3', async () => {
    await caption(tom, 'Bro Thomas Hartley', 'The reminder is in his inbox and on his phone.');
    await tap(tom, 'Dues: £180.00 still to pay', 'a, button, p, span, div');
    await settle(tom, 1000);
    await caption(tom, 'Bro Thomas Hartley', 'What he owes, by when, and the lodge\'s bank details with his reference.', 3000);
    await scrollTo(tom, "I've paid or set up a standing order");
    await pause(1200);
    await caption(tom, 'Bro Thomas Hartley', 'He pays from his banking app and taps to say so. The reminders stop.', 2200);
    await tap(tom, "I've paid or set up a standing order");
    await pause(1600);
});

await go(treasurer, '/members/lodge-of-fraternity/accounts/dues');
await step(treasurer, 'd4', async () => {
    await caption(treasurer, 'WBro Jordan Lee, Treasurer', 'He can see who says they have paid, to look for in the bank.');
    await tap(treasurer, 'Bro Thomas Hartley', 'summary');
    await pause(2200);
});

const statement = `${OUT}/dues-statement.csv`;
const today = new Date();
const d = `${String(today.getDate()).padStart(2, '0')}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
fs.writeFileSync(statement, `Date,Description,Reference,Amount,Balance\n${d},BACS T HARTLEY,SUB-2027-014,180.00,2648.40\n`);
await go(treasurer, '/members/lodge-of-fraternity/accounts/import');
await step(treasurer, 'd5', async () => {
    await caption(treasurer, 'WBro Jordan Lee, Treasurer', 'He brings in the latest bank statement.');
    const input = await treasurer.$('#statement-file');
    await input.uploadFile(statement);
    await pause(600);
    await tap(treasurer, 'Read the statement');
    await settle(treasurer, 1200);
    await tap(treasurer, 'Import 1 line');
    await settle(treasurer, 1200);
});
await go(treasurer, '/lodge-of-fraternity/admin/reconciliation-workspace');
await step(treasurer, 'd6', async () => {
    await caption(treasurer, 'WBro Jordan Lee, Treasurer', 'The £180 is matched to Bro Thomas\'s dues by the reference on his invoice.', 2600);
    await scrollTo(treasurer, 'Reconcile and mark paid');
    await pause(1200);
    await tap(treasurer, 'Reconcile and mark paid');
    await settle(treasurer, 1500);
});
await go(treasurer, '/members/lodge-of-fraternity/accounts/dues');
await step(treasurer, 'd7', async () => {
    await caption(treasurer, 'WBro Jordan Lee, Treasurer', 'Bro Thomas is paid in full. No spreadsheet, no chasing.', 2800);
});

await title('d8', 'Done', 'Reminded automatically, paid by bank, matched in one tap.');
if (!DRY) console.log('video', join(files, `${OUT}/dues.mp4`));
await browser.close();
