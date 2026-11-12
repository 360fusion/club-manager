# Walkthrough: a visiting Mason (task-based usability)

Run 6 Nov 2026 against the e2e app (PostgreSQL `club_manager_e2e`, queue worker, Mailpit), lodge `lodge-of-fraternity`, in the built-in browser pane at desktop width and the 375 px mobile preset. Test only: no code changed, nothing committed.

**People used (all removed afterwards):**
- `wt.visit.sec@e2e.test`: test lodge admin I created (role admin, active) because `member.a3` was in use. Password set through Forgot password and Mailpit.
- `wt.visitor1@e2e.test` "Bro Walter Visitor, Apollo Lodge No. 357": self-registered visitor, approved.
- `wt.visitor2@e2e.test` "Victor Second": ordinary account (made in tinker with no usable password, then reset via Mailpit) who asked to visit one meeting from the directory.
- `wt.visitor3@e2e.test` "Mobile Third": registered on mobile, then rejected.

**Harness notes.** On `visitor.localhost:8001` every page is blank: assets load from `APP_URL` (`127.0.0.1:8001`) and the browser blocks them as cross-origin. I ran two extra `php -S` servers on the same e2e database, each with its own `APP_URL`: `visitor.localhost:8043` and `lodgeadmin.localhost:8044`. Both are stopped now. `php artisan serve` does not pass `APP_URL` through to the server, so `php -S` with `server.php` was needed.

No lodge in the directory is linked to club 1. To test the directory part of the journey I linked directory lodge 1028 ("Lodge of Fraternity No. 1418", Durham) to club 1 for the run, then put it back (`club_id` and `claimed_at` both null again). Because that listing belongs to a different real lodge, its Durham hall and "4th Thursday" pattern do not match the club's Monday meetings in Oxford. I have not reported that mismatch as a finding.

---

## 1. Finding the lodge (visitor, logged out)

| Step | What happened |
|---|---|
| `/lodges` | Clear page: "Find a lodge", 9688 lodges, search box first. Good. Tab title is "Find a lodge - ClubManager - Laravel" (the "Laravel" suffix comes from `APP_NAME`). |
| Typed "Fraternity" | Results update as you type and the URL becomes `?q=Fraternity&within=10`. 9 matches, each with order, number, province, hall and "Next expected" date. Good. The header still says "9688 lodges and chapters", with no "9 results" count. |
| Opened "Lodge of Fraternity No. 1418" (before linking) | A strong page: when it meets, the province's own wording, eight predicted dates with the Installation marked, .ics/webcal links, hall with a maps link, and sources (UGLE, province, OnTheSquare). |
| **Dead end** | The unlinked listing has no way to reach the lodge's website and no way to ask to visit. With no directory row for club 1, a real visitor can never get from the directory to `/site/lodge-of-fraternity`. |
| After linking | An "On ClubManager" badge, "This lodge shares its summonses with: Members only.", and "Visit this lodge: Sign in to ask to visit a meeting." There is still **no link to the lodge's website and no link to the visitor sign-up form**. The right column has an empty card under "Where". "Bulletins you can subscribe to" lists two bulletins with no subscribe button while logged out. |
| "Sign in" | Goes to `/login`, which has no "Create an account" link. `/register` exists but nothing links to it, and its heading says "Create a member account". A visitor with no account stops here. |

## 2. The lodge website (`/site/lodge-of-fraternity`)

- The nav has Home, About, Join Us, News, Contact and two test pages. **No page or link mentions visiting.**
- On Join Us, "complete our visitor registration form" is plain text, not a link. "Subscribe →" links to `/lodge-of-fraternity/admin/subscriptions`, an admin page a visitor cannot open (seed data, but a builder block can link there).
- Contact shows "Email: Not configured / Meeting times: Not configured / Location: Not configured" to the public. A visitor reads that as an abandoned site.
- Nothing in the code links to `clubs.visitor.register` (`/{slug}/visitor-register`). Only `VisitorRegister.vue` itself refers to it. **The only way in is typing the URL.**

## 3. Visitor sign-up (`/lodge-of-fraternity/visitor-register`)

- A clean form: "Visiting brethren sign-up". Rank is free text and **pre-filled with "WBro"**, so a Bro who skims it signs up as WBro (CLAUDE.md says ranks come from `Member::MASONIC_RANKS`). The helper text "passwordless RSVP links will be sent here" is not true today (see section 5).
- Empty submit gives the browser's own "Please fill in this field". Fine.
- After submit, a green box titled **"Registration complete"** says "Thank you! Your visitor request has been sent… once approved, you will receive meeting summonses and RSVP invites via email." The title says complete but the body says pending. The form stays filled with the Register button still active.
- On mobile (375 px) the page scrolls so the message is in view. Good.
- **Emails: none.** The visitor gets no "we have your request" email and **the lodge gets no alert**. The pivot row is `role=visitor, status=pending, home_club_name=Apollo Lodge, rank=Bro`. An account is created with a random password the visitor never learns.

## 4. Lodge side: finding and approving the request (busy secretary)

| Step | What happened |
|---|---|
| Sign in | "Reset password and log in" drops you on the login page with no "password changed" message, so you sign in again (seen 3 times). |
| Member dashboard | Under "Needs your attention": **"Admin · 2 members are waiting for approval · Open"**. A good prompt, but it calls the visitor a member. |
| Clicked "Open" | **A broken overlay**: an unstyled page with giant blue icons inside a modal. The link is an Inertia `<Link>` to `/lodge-of-fraternity/admin/members`, a Livewire page, so Inertia shows the raw HTML in its error modal. |
| Loaded that page directly | "Lodge Member Directory" (Rule 153 roster). **It has no pending list and no visitor.** This is where the dashboard item points (`MemberInbox::pendingApprovals`). |
| Admin Dashboard | A "Pending Users 2" tile. It is not clickable. |
| Meetings → Visitors tab | "Visiting brethren… Requests to visit a meeting: No one has asked to visit yet" and "Requests to receive your summonses: No requests yet." **Walter is not here**, although this is the page named Visitors. |
| Finally | The "Waiting for approval" list exists only on `/lodge-of-fraternity/overview` (the old "Workspace" page, "Rowing & Water Sports", Member roster tab). The only links to it are "Club admin" in the public site's account menu and on `Welcome.vue`. Nothing in the admin sidebar leads there. I only found it by grepping the code. |
| The list | "Walter Visitor · wt.visitor1@e2e.test · #", then **Approve member / Reject**. Nothing says this is a visitor. The home lodge, number and rank the visitor typed are not shown, so the secretary has nothing to check against. |
| Approve | One click, no confirmation, no success message. The row moves into the roster as "Visitor · Active". |
| Reject (tested on mobile) | One click, no confirmation, no reason, no message. The pivot row is deleted, the **visitor gets no email**, and the user account stays. |

Mobile: the overview page fits in 375 px. The tab strip scrolls sideways with its first tab cut off, and the approval list is about a screen and a half down.

## 5. Visitor after approval

- **Email: none.** Approval sends no email. It only creates an in-app notification ("Welcome to The Lodge of Fraternity · Your membership has been approved", which again says membership), and the visitor cannot see it because he has never had a password.
- **Summonses: none, ever.** `MeetingSummonsController::publishSummons` filters `role != visitor`, so "you will receive meeting summonses and RSVP invites via email" is never true. The three meetings were already published, and approval does not send them either.
- The only way in is guessing "Forgot your password?" for an account he does not know exists. The login page has no "email me a sign-in link". The reset email comes from "Laravel": "Dear Walter Visitor, We received a request to reset the password".
- Once signed in, the dashboard is the full member dashboard:
  - "Reply to November/December/January Regular Meeting" with **Going, dining / Going, no dining / Apologies**. Apologies is offered to a visitor.
  - **"Admin · 1 member is waiting for approval · Open"** and an **"Admin" button** in the club header and in "Your clubs". Both lead to 403 "You cannot open this page", so access is safe, but the pending count leaks and the links are dead ends. Cause: `MemberScope::isStaffIn()` returns `role !== 'member'`, which is true for `visitor`.
  - The nav has Dues and Subscriptions. The club overview shows "**Active #MEM-1001**" (the same number the admin sees, so it looks like a placeholder), "Attendance rate 100%, 0 of 0 sessions", and "**Membership dues: Senior Rower Tier £35.00 a month, Good standing**".
- Summons page: the full summons, including agenda, "Charitable donation proposals… £100 Oxford Hospice", "By command of the Worshipful Master: Worshipful Master" (placeholder name), secretary name and email. The admin Visitors page promises the opposite: "Approved visitors see only the date, time, venue, dress and dining… They never see… the rest of the summons." The two visitor systems disagree.
- RSVP: "Respond to summons" opens a clean dialog (Meeting and dining / Meeting only / Apologies, dietary, Add a guest). The dietary box is **empty** although he gave "Vegetarian" at sign-up. Saved, then "Your RSVP response has been saved." and "Attending dining". **No confirmation email.** The dashboard then shows "Pay for dining at November Regular Meeting £20.00 · Open". Mobile dialog fits well.
- Secretary dashboard for that meeting: Walter appears under **Visitors (1)** as "Bro Walter Visitor · Home lodge or club: **Visitor**" (should say Apollo Lodge No. 357) and in the caterer's dietary list. The "All responses" table does not list him. Before he replied, an approved visitor appeared nowhere on the meeting page.
- Directory page while signed in as Walter: "Confirmed meetings, Published by the lodge" with venue, dress, festive board and "Visitor's dining cost £20.00". **Very good.** But the "Visit this lodge" box disappears for him (approved visitors count as members in `visitorState`).

## 6. Asking to visit one meeting (directory, `wt.visitor2`, not linked to the lodge)

- "Visit this lodge: Ask the secretary if you can attend a meeting. They will see your name and email address." This is honest wording. The fields are Meeting date (a free date picker), Your lodge, Its number, Your rank, "I would like to dine" (its checkbox is announced only as "on"), and Anything else.
- The date is free: no list of the lodge's real meetings (they are members-only here), so the visitor picks a predicted date. Thu 26 Nov was accepted although the lodge meets Mon 23 Nov.
- After sending: "Your request has been sent to the lodge secretary. You will be told when they answer." and "Thu, 26 Nov 2026: Waiting for the secretary · Withdraw". **The clearest waiting state in the app.**
- Lodge side: within seconds all four staff got "Victor Second asks to visit on 26 Nov 2026 / From Apollo Lodge No. 357, WBro. He would like to dine. / Answer the request" (that includes `coach@example.com` and `admin@example.com`). The Visitors page lists the request with a Reply box and Accept/Decline. Dates are ISO ("2026-11-26", "asked 2026-11-06"), and there is no warning that the lodge has no meeting that day.
- Accepted with the reply "Our next meeting is actually Monday 23 November…": "Accepted. He has been told." The visitor got "**The Lodge of Fraternity looks forward to your visit on 26 Nov 2026**" with the reply as the body, and the email has no time, venue, dress or dining. The subject keeps the wrong date while the body corrects it.
- After acceptance: the directory page shows "Accepted ("…")". **His dashboard still says "You are not a member of any club yet"**, the visit is not in his calendar, and no RSVP is created, so he is missing from the meeting headcount and dining list (matches M15 in `mtg.md`).

## 7. Mobile (375 px)

- `/lodges`: **the header overlaps** ("ClubManager", "Find a lodge" and "Dark mode" are drawn on top of each other). The six filters stack, so the first result starts about 80% down the screen. No sideways scroll.
- Lodge page, sign-up form, member dashboard, summons dialog and admin approval list all fit with no horizontal scroll. Touch targets are a good size.

## Good things worth keeping

- The directory: fast search as you type, honest "worked out from the pattern… confirm with the lodge", source links, the Installation marked, calendar feed.
- For an approved visitor, the confirmed-meetings block shows exactly what a visitor needs: venue, dress, festive board, visitor's dining cost.
- The ask-to-visit flow: plain wording, visible status with Withdraw, instant staff email with a deep link, Reply box, "He has been told."
- The summons reply dialog and the "Your RSVP response has been saved." feedback, on both desktop and mobile.
- Visitors are refused (403) on admin URLs even where the UI wrongly links to them.

---

## Top 10 friction points (ranked)

1. **Approved visitors never get summonses or any email** (`MeetingSummonsController::publishSummons` filters `role != visitor`; approval sends nothing). The sign-up page promises the opposite. *Change:* on approval, email the visitor a welcome with a sign-in or set-password link and the upcoming meetings, and include active visitors in summons emails with the visitor-limited summons and an attending-only RSVP link.
2. **The secretary cannot find the pending visitor.** The dashboard's "Open" goes to the Livewire roster, which has no pending list, and shows a broken Inertia overlay on the way. The Meetings → Visitors page says "No requests yet", and the real list is on the unlinked `/{slug}/overview`. *Change:* list self-registered visitors on the admin Visitors page (one place for all visitor requests), point `MemberInbox::pendingApprovals` there, and make that link a plain `<a>` (or Inertia `Link` only to Inertia pages). Make the "Pending Users" tile a link.
3. **No route from the directory or the lodge website to visitor sign-up.** Nothing links to `/{slug}/visitor-register`. *Change:* add "Visiting? Register as a visitor" to the directory's "Visit this lodge" box (logged out) and a standard site footer/Contact link, and turn Join Us' "visitor registration form" text into that link.
4. **Visitors are treated as staff on the dashboard** (`MemberScope::isStaffIn` is true for `visitor`): they see "N members are waiting for approval" and an "Admin" button that both lead to 403. *Change:* make `isStaffIn` check staff roles (`ClubRole` staff cases) rather than `!== 'member'`, and hide Admin, Dues and Subscriptions for visitors.
5. **The visitor portal shows member-only content**: the full summons (agenda, charity grants) against the admin page's promise, "Membership dues £35/month, Good standing", "#MEM-1001", and Apologies. *Change:* give visitors the limited summons view already described on the Visitors page, hide dues and attendance cards, and offer only attending options.
6. **No way to sign in without guessing "Forgot password".** The visitor never chose a password and the login page has no sign-in-link option or "Create account" link. *Change:* send a set-password or magic sign-in link on approval, add "Email me a sign-in link" to login, and add "New here? Create an account" to the login page.
7. **Approve and Reject give the secretary too little to judge and tell nobody.** The list shows no visitor label, home lodge, number, rank or date asked. Approve and Reject are instant with no confirmation or flash, and Reject deletes silently with no email. *Change:* show "Visitor from Apollo Lodge No. 357 (Bro), asked 6 Nov", label the button "Approve visitor", confirm Reject with an optional reason, and email the outcome.
8. **The visit-request date is free and its acceptance is a dead end.** Any date is accepted (no meeting that day), the acceptance subject repeats the wrong date with no time or venue, and no RSVP is created, so the visitor is not in the headcount or dining list and sees nothing on his dashboard. *Change:* offer the lodge's actual meeting dates as choices (date and time only, even when summonses are members-only), and on Accept create a visitor RSVP for that meeting and include time, venue, dress and dining in the email.
9. **Mixed wording about pending versus done:** "Registration complete" over a pending message, "membership has been approved" for a visitor, "N members are waiting", "Approve member", and the helper text "passwordless RSVP links will be sent here". *Change:* title it "Request sent: waiting for the lodge", and use "visitor" consistently in the inbox item, the approval button and the notification.
10. **Small gaps that add up:** the sign-up dietary note is not carried into the RSVP dialog, home lodge shows as "Visitor" on the secretary dashboard (`Club::users()` pivot lacks `home_club_name`/`home_club_number`), rank is pre-filled "WBro" as free text, "Reset password and log in" does not log in or say it worked, the mobile `/lodges` header overlaps, the dine checkbox is labelled "on", and the public Contact page shows "Not configured" three times. *Change:* prefill dietary notes from the pivot, add the pivot columns to `withPivot`, use a rank select from `Member::MASONIC_RANKS` with no default, log in after reset as the button says, fix the header wrap at 375 px, label the checkbox, and hide unset contact fields.

## Not covered

- What a visitor sees once summons visibility is "Visitors I approve" (the summons-access request path). Left alone so I did not change club settings during other sessions' runs; `mtg.md` 6c covers it at HTTP level.
- Dining payment for a visitor ("Pay for dining £20 · Open" was seen, not followed).
- Withdrawing a visit request, and declining one.
- Accessibility beyond what the browser's accessibility tree showed.

## Cleanup

Deleted users 126, 129, 130 and 131 with their `club_user`, `meeting_rsvps` (one, meeting 1), `club_visit_requests` (one), notifications (theirs, plus the three staff notifications about Victor's request), sessions and reset tokens. Directory lodge 1028 is restored to `club_id` null and `claimed_at` null. My two `php -S` servers on 8043 and 8044 are stopped. I did not delete the Mailpit messages to `wt.*@e2e.test` or the four "Victor Second asks to visit" emails to staff. Other sessions' data (Bob Baker pending, meetings, RSVPs) was not touched.
