// Journey 1: a meeting from summons to the bank. DRY=1 takes screenshots instead of recording.
import fs from 'fs';
import { start, phone, go, fill, tap, find, caption, hideCaption, shot, settle, pause, scene, card, join, OUT } from './lib.mjs';

const DRY = process.env.DRY === '1';
const browser = await start();
const files = [];
const step = async (page, name, fn) => {
    if (DRY) { await fn(); await shot(page, name); console.log('ok', name); } else { files.push(await scene(page, name, fn)); }
};
const title = async (name, heading, line) => { if (!DRY) files.push(await card(browser, name, heading, line)); };
const scrollTo = async (page, text) => { const e = await find(page, text, 'h1,h2,h3,h4,p,button,a,label,strong,summary,th,td,span,li,div'); await e.evaluate((x) => x.scrollIntoView({ block: 'center', behavior: 'smooth' })); await pause(900); };

await title('m0', 'A lodge meeting, from summons to the bank', 'The Secretary sends the summons, members reply and pay, the Treasurer checks it off.');

// 1. The Secretary sets up the meeting.
const sec = await phone(browser, 'admin@example.com');
await go(sec, '/lodge-of-fraternity/admin/meetings/create');
await step(sec, 'm1', async () => {
    await caption(sec, 'WBro Alex Morgan, Secretary', 'Sets up the December meeting on his phone.');
    await fill(sec, 'Meeting title', 'Regular meeting');
    await fill(sec, 'Meeting date', '2026-12-15');
    await fill(sec, 'Meeting start time', '18:30');
    await fill(sec, 'Dress code', 'Dark suit, white gloves');
    await fill(sec, 'Venue address', 'Riverside Masonic Hall, 12 Mill Lane, Ashford');
    await caption(sec, 'WBro Alex Morgan, Secretary', 'The festive board: menu, price, and where to pay.', 1800);
    await fill(sec, 'Summons section', 'festive');
    await settle(sec);
    await fill(sec, 'Festive board theme or notice text', 'Christmas festive board with carols');
    await fill(sec, 'Menu', 'Prawn cocktail\nRoast turkey with all the trimmings\nChristmas pudding');
    await fill(sec, 'Price per head', '32');
    await fill(sec, 'Bank sort code', '12-34-56');
    await fill(sec, 'Bank account number', '12345678');
    await fill(sec, 'Payment reference convention', 'XMAS');
    await tap(sec, 'Save summons changes');
    await settle(sec, 1200);
});
const meetingId = sec.url().match(/meetings\/(\d+)/)[1];
await go(sec, `/lodge-of-fraternity/admin/meetings/${meetingId}`);
await step(sec, 'm2', async () => {
    await caption(sec, 'WBro Alex Morgan, Secretary', 'Publishes it. Every member gets the summons by email and in the app.');
    await tap(sec, 'Publish and send summons');
    await pause(700);
    await tap(sec, 'Publish summons');
    await settle(sec, 1200);
    await caption(sec, '', 'Published.', 1500);
});

await title('m3', 'Bro Thomas Hartley', 'Opens the summons from the notification on his phone.');

// 2. A member replies, dining, and pays.
const tom = await phone(browser, 'member@example.com');
await go(tom, `/members/lodge-of-fraternity/meetings/${meetingId}/summons`);
await step(tom, 'm4', async () => {
    await caption(tom, 'Bro Thomas Hartley', 'Everything he needs: date, time, dress, venue and the festive board.');
    await scrollTo(tom, 'Going, dining');
    await caption(tom, 'Bro Thomas Hartley', 'One tap: coming, and staying to dine.', 1800);
    await tap(tom, 'Going, dining');
    await pause(900);
    await scrollTo(tom, 'Pay by card');
    await caption(tom, 'Bro Thomas Hartley', 'He can pay by card now (it opens Stripe\'s secure page, left out of this demo)...', 3200);
    await caption(tom, 'Bro Thomas Hartley', '...or by bank transfer, quoting his own reference.', 2000);
    await scrollTo(tom, 'Bank transfer details');
    await pause(1800);
    await scrollTo(tom, "I've paid by bank transfer");
    await caption(tom, 'Bro Thomas Hartley', 'He pays from his banking app, then taps to say so. Reminders stop.', 2200);
    await tap(tom, "I've paid by bank transfer");
    await pause(1500);
});

// 3. Another member sends apologies.
const david = await phone(browser, 'coach@example.com');
await go(david, `/members/lodge-of-fraternity/meetings/${meetingId}/summons`);
await step(david, 'm5', async () => {
    await caption(david, 'Bro David Price', "Can't make it, so he sends his apologies.");
    await scrollTo(david, 'Apologies');
    await tap(david, 'Apologies');
    await pause(1200);
    await caption(david, 'Bro David Price', 'The Secretary has them for the minutes.', 1800);
});

// 4. The Secretary sees the replies.
await go(sec, `/lodge-of-fraternity/admin/meetings/${meetingId}`);
await step(sec, 'm6', async () => {
    await scrollTo(sec, 'Member name');
    await caption(sec, 'WBro Alex Morgan, Secretary', 'The replies come in: who is dining, who sent apologies.', 2600, { top: true });
    await pause(2000);
});

await title('m7', 'WBro Jordan Lee, Treasurer', 'Checks the payments against the bank statement.');

// 5. The Treasurer matches the bank line.
const statement = `${OUT}/statement.csv`;
const today = new Date();
const d = `${String(today.getDate()).padStart(2, '0')}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
fs.writeFileSync(statement, `Date,Description,Reference,Amount,Balance\n${d},BACS T HARTLEY,XMAS-${meetingId}-HARTLEY,32.00,2482.00\n${d},TESCO STORES,DEBIT,-18.40,2450.00\n`);
const treasurer = await phone(browser, 'treasurer@example.com');
await go(treasurer, '/members/lodge-of-fraternity/accounts/import');
await step(treasurer, 'm8', async () => {
    await caption(treasurer, 'WBro Jordan Lee, Treasurer', 'Downloads the statement from the bank and opens it here.');
    const input = await treasurer.$('#statement-file');
    await input.uploadFile(statement);
    await pause(800);
    await tap(treasurer, 'Read the statement');
    await settle(treasurer, 1500);
    await caption(treasurer, 'WBro Jordan Lee, Treasurer', 'Two new lines, including Bro Thomas\'s £32.', 2200);
    await tap(treasurer, 'Import 2 lines');
    await settle(treasurer, 1200);
});
await go(treasurer, '/lodge-of-fraternity/admin/reconciliation-workspace');
console.log(await treasurer.evaluate(() => { const b = [...document.querySelectorAll('button')].find((x) => x.innerText.includes('Reconcile and mark paid')); return b?.closest('div.rounded-2xl,div.rounded-xl,li,article')?.innerText.replace(/\s+/g, ' ').slice(0, 400); }));
await step(treasurer, 'm9', async () => {
    await caption(treasurer, 'WBro Jordan Lee, Treasurer', 'The app has matched the £32 to Bro Thomas\'s dining by its reference.', 2600);
    await scrollTo(treasurer, 'Reconcile and mark paid');
    await pause(1500);
    await tap(treasurer, 'Reconcile and mark paid');
    await settle(treasurer, 1500);
    await caption(treasurer, 'WBro Jordan Lee, Treasurer', 'One tap: reconciled, the books updated, and the dining marked paid.', 2600);
});

await go(tom, `/members/lodge-of-fraternity/meetings/${meetingId}/summons`);
await step(tom, 'm10', async () => {
    await scrollTo(tom, 'Bank transfer details');
    await caption(tom, 'Bro Thomas Hartley', 'His summons now shows his dining as paid.', 2800);
});

await title('m11', 'Done', 'Summons out, replies in, dining paid and in the books. Nobody chased anybody.');
console.log('treasurer at', treasurer.url());
console.log(await treasurer.evaluate(() => [...document.querySelectorAll('button,a,label')].map((e) => e.innerText.trim().replace(/\s+/g, ' ')).filter(Boolean).slice(14).join(' | ')));

if (!DRY) console.log('video', join(files, `${OUT}/meeting-to-bank.mp4`));
await browser.close();
