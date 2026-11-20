// Journey: the Secretary passes on a sister lodge's summons; a member gets it as they chose. DRY=1 takes screenshots.
import { start, phone, go, tap, find, fill, caption, shot, settle, pause, scene, card, join, OUT } from './lib.mjs';

const DRY = process.env.DRY === '1';
const browser = await start();
const files = [];
const step = async (page, name, fn) => {
    if (DRY) { await fn(); await shot(page, name); console.log('ok', name); } else { files.push(await scene(page, name, fn)); }
};
const title = async (name, heading, line) => { if (!DRY) files.push(await card(browser, name, heading, line)); };
const scrollTo = async (page, text) => { const e = await find(page, text, 'h1,h2,h3,h4,p,button,a,label,strong,summary,th,td,span,li,div'); await e.evaluate((x) => x.scrollIntoView({ block: 'center', behavior: 'smooth' })); await pause(900); };

await title('p0', 'Passing on other lodges\' summonses', 'No more forwarded emails: the Secretary passes it on once, and each member gets it as they chose.');

const sec = await phone(browser, 'admin@example.com');
await go(sec, '/members/lodge-of-fraternity/pass-on/lists');
await step(sec, 'p1', async () => {
    await caption(sec, 'WBro Alex Morgan, Secretary', 'The other lodges in Riverside Masonic Hall are his sister lodges, ready to go.', 2800);
});
await go(sec, '/members/lodge-of-fraternity/pass-on');
await step(sec, 'p2', async () => {
    await caption(sec, 'WBro Alex Morgan, Secretary', 'Lodge of Unity\'s summons has come in by email. He passes it on.', 2400);
    await fill(sec, 'Which lodge', 'lodge-of-unity-4421');
    await fill(sec, 'Title', 'Installation meeting');
    await fill(sec, 'Meeting date', '2027-01-12');
    await fill(sec, 'Time', '18:00');
    await fill(sec, 'Venue', 'Riverside Masonic Hall, Ashford');
    await fill(sec, 'Dress', 'Dinner jacket');
    await fill(sec, 'Text or a note', 'Brethren of Fraternity are warmly invited. Dining £35; tell me by 5 January.');
    await caption(sec, 'WBro Alex Morgan, Secretary', 'He could attach their PDF too.', 1600);
    await tap(sec, 'Pass it on');
    await settle(sec, 1200);
    await scrollTo(sec, 'Passed on lately');
    await caption(sec, 'WBro Alex Morgan, Secretary', 'Sent: to the members who want sister lodges\' summonses, by app or email as each chose.', 2800);
});

const tom = await phone(browser, 'member@example.com');
await go(tom, '/members/notifications');
await step(tom, 'p3', async () => {
    await caption(tom, 'Bro Thomas Hartley', 'It arrives in his inbox and on his phone.');
    await tap(tom, 'Installation meeting', 'a, button, p, span, div');
    await settle(tom, 1000);
    await caption(tom, 'Bro Thomas Hartley', 'Date, venue, dress and the note. He can ask to visit, or stop hearing about this lodge.', 3000);
});
await go(tom, '/members/calendar?month=2027-01');
await step(tom, 'p4', async () => {
    await scrollTo(tom, 'Installation meeting');
    await caption(tom, 'Bro Thomas Hartley', 'And it is in his calendar.', 2200);
});
await go(tom, '/members/passed-on-choices');
await step(tom, 'p5', async () => {
    await caption(tom, 'Bro Thomas Hartley', 'He chooses what he hears about, and how: in the app, by email, or not at all.', 2800);
    await tap(tom, 'Provincial communications by email', '[role=switch], button');
    await pause(1500);
});

await title('p6', 'Done', 'One pass-on by the Secretary. Each member gets only what he wants.');
if (!DRY) console.log('video', join(files, `${OUT}/passing-on.mp4`));
await browser.close();
