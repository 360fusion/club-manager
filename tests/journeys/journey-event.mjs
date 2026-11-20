// Journey: an event with tickets and dishes, booked with a guest, and the door on the night. DRY=1 takes screenshots.
import { start, phone, go, tap, find, fill, field, choose, caption, shot, settle, pause, scene, card, join, OUT } from './lib.mjs';

const DRY = process.env.DRY === '1';
const browser = await start();
const files = [];
const step = async (page, name, fn) => {
    if (DRY) { await fn(); await shot(page, name); console.log('ok', name); } else { files.push(await scene(page, name, fn)); }
};
const title = async (name, heading, line) => { if (!DRY) files.push(await card(browser, name, heading, line)); };
const scrollTo = async (page, text) => { const e = await find(page, text, 'h1,h2,h3,h4,p,button,a,label,strong,summary,th,td,span,li,div'); await e.evaluate((x) => x.scrollIntoView({ block: 'center', behavior: 'smooth' })); await pause(900); };
const fields = (page) => page.$$eval('input,select,textarea', (els) => els.filter((e) => e.offsetParent && e.type !== 'hidden').map((e) => `${e.type}|${e.labels?.[0]?.innerText.trim().replace(/\s+/g, ' ').slice(0, 50) || e.placeholder || e.getAttribute('aria-label')}`));

await title('e0', "Ladies' Festival", 'An event with tickets and a choice of dishes, booked with a guest, and the door on the night.');

const sec = await phone(browser, 'admin@example.com');
await go(sec, '/lodge-of-fraternity/admin/events/create');
await step(sec, 'e1', async () => {
    await caption(sec, 'WBro Alex Morgan, organiser', 'Sets up the Ladies\' Festival: date, venue, places and price.');
    await fill(sec, 'Event status', 'upcoming');
    await fill(sec, 'Title', "Ladies' Festival 2027");
    await fill(sec, 'Description', 'A black tie dinner and dance for members, partners and friends.');
    await fill(sec, 'Venue or building', 'The Grand Hotel');
    await fill(sec, 'Town or city', 'Canterbury');
    await fill(sec, 'Starts', '2027-03-13T19:00');
    await fill(sec, 'Total places', '80');
    await fill(sec, 'Guests per booking', '3');
    await fill(sec, 'Ticket price', '55');
    await caption(sec, 'WBro Alex Morgan, organiser', 'Bank transfer and card are on, from the lodge\'s payment options.', 2000);
    await caption(sec, 'WBro Alex Morgan, organiser', 'A dinner, with a choice of dishes.', 1500);
    await tap(sec, 'There is a dinner', 'label');
    await tap(sec, 'People choose their dishes', 'label');
    await fill(sec, 'Starter 1', 'Smoked salmon');
    await fill(sec, 'Starter 2', 'Leek and potato soup');
    await fill(sec, 'Main course 1', 'Beef Wellington');
    await fill(sec, 'Main course 2', 'Wild mushroom risotto');
    await fill(sec, 'Dessert 1', 'Sticky toffee pudding');
    await fill(sec, 'Dessert 2', 'Lemon tart');
    await tap(sec, 'Save event');
    await settle(sec, 1500);
    await caption(sec, 'WBro Alex Morgan, organiser', 'Published: members see it and can book.', 1800);
});

await title('e2', 'Bro Thomas Hartley', 'Books for himself and his wife.');
const tom = await phone(browser, 'member@example.com');
await go(tom, '/members/lodge-of-fraternity/events');
await step(tom, 'e3', async () => {
    await caption(tom, 'Bro Thomas Hartley', 'The Ladies\' Festival, £55 a head.');
    await tap(tom, 'Book a place');
    await pause(800);
    await tap(tom, "I'm dining", 'label');
    await caption(tom, 'Bro Thomas Hartley', 'He chooses his dishes...', 1400);
    await choose(tom, 'Starter', 'Smoked salmon');
    await choose(tom, 'Main', 'Beef Wellington');
    await choose(tom, 'Dessert', 'Sticky toffee pudding');
    await caption(tom, 'Bro Thomas Hartley', '...adds his wife as his guest...', 1400);
    await tap(tom, 'Add a guest');
    await pause(700);
    await fill(tom, "Guest's full name", 'Mrs Sarah Hartley');
    await tap(tom, 'Dining', 'label');
    await choose(tom, 'Starter', 'Leek and potato soup', 1);
    await choose(tom, 'Main', 'Wild mushroom risotto', 1);
    await choose(tom, 'Dessert', 'Lemon tart', 1);
    await caption(tom, 'Bro Thomas Hartley', '...and how to pay: card now, or bank transfer.', 1800);
    await tap(tom, 'Bank transfer', 'label');
    await tap(tom, 'Confirm booking');
    await settle(tom, 1500);
    await caption(tom, 'Bro Thomas Hartley', 'Booked. Two places, dishes chosen.', 1800);
});
const ticketHref = await (await find(tom, 'Show ticket')).evaluate((e) => e.getAttribute('href'));
const eventId = ticketHref.match(/events\/(\d+)/)[1];
await step(tom, 'e4', async () => {
    await tap(tom, 'Show ticket');
    await settle(tom, 1200);
    await caption(tom, 'Bro Thomas Hartley', 'His ticket, with a code for the door. It works without signal too.', 2600);
    console.log((await tom.evaluate(() => document.body.innerText)).replace(/\n+/g, ' | ').slice(0, 700));
});

await go(sec, `/lodge-of-fraternity/admin/events/${eventId}/catering`);
await step(sec, 'e5', async () => {
    await caption(sec, 'WBro Alex Morgan, organiser', 'The numbers for the hotel: how many of each dish, and dietary needs.', 2800);
    console.log((await sec.evaluate(() => document.querySelector('main')?.innerText || document.body.innerText)).replace(/\n+/g, ' | ').slice(0, 600));
});
await go(sec, `/lodge-of-fraternity/admin/events/${eventId}/checkin`);
await step(sec, 'e6', async () => {
    await caption(sec, 'On the night', 'At the door, he ticks people in, or scans their ticket.', 2400, { top: true });
    for (const name of ['Thomas Hartley', 'Mrs Sarah Hartley']) {
        const box = await sec.evaluateHandle((name) => {
            const row = [...document.querySelectorAll('li, div')].filter((e) => e.innerText?.trim().startsWith(name)).sort((a, b) => a.innerText.length - b.innerText.length)[0];
            let el = row;
            while (el && !el.querySelector('button, input[type=checkbox]')) el = el.parentElement;
            return el?.querySelector('button, input[type=checkbox]') || null;
        }, name);
        await tap(sec, box.asElement());
        await pause(700);
    }
    await caption(sec, 'On the night', 'Both arrived. The Hartleys still owe £110: he can take it at the door.', 2600, { top: true });
});

await title('e7', 'Done', 'Booked with dishes chosen, the hotel told the numbers, and the door ticked off on a phone.');

if (!DRY) console.log('video', join(files, `${OUT}/event-booking.mp4`));
await browser.close();
