# Usability walkthrough: ordinary lodge member on a phone

Date: 6 Nov 2026. App: e2e (`127.0.0.1:8001`, PostgreSQL `club_manager_e2e`), lodge `lodge-of-fraternity`, built-in browser at 375 × 812 (mobile preset), Mailpit for mail.
Persona: Bro Walter Phoneson (`member.walk@e2e.test`), a new, non-technical member. He only used what the emails and screens offered.

## Setup (admin side, done with tinker and stated here)

- Created a lodge member record for Walter and invited him through `MemberInvitationService::invite()` (what the Members screen does). He set his password from the invite email.
- The November summons had already been published before Walter joined. **Nothing in the app sends a summons to someone who joins afterwards** (publishing again only says "members keep the links they were already sent"). So I sent his summons with the same code `publishSummons` uses (`RsvpTokenService` + `MeetingSummonsMail`).
- The lodge had **no payment options at all** (no `club_payment_methods`). The first booking therefore never asked how to pay. Halfway through I added "Bank transfer" (with bank details) and "Pay later" as a lodge admin would, using `ClubPaymentMethod::offerOnUpcomingEvents()`. The edit step was tested with them. I deleted both afterwards.
- Cleanup: Walter's event booking and meeting RSVP and the two payment options are deleted. His account is still there (user 127, member 5688, active).
- Browsing note: `http://member.localhost:8001` cannot be used here. Asset URLs are absolute to `127.0.0.1`, so every script is blocked by CORS and the page is blank. I used `127.0.0.1` in this session's own browser pane.
- Environment note: the booking email subject starts with `[LODGE]` and its body ends with `LODGE-OVERRIDE-MARKER`. Another session's lodge email-template override test left these behind. This is test data, not a product bug, but it should be removed before anyone judges the email wording.

## Step by step

### 1. Invitation email and activating the account
- **Email arrived** within seconds. Subject: "Invitation to Join The Lodge of Fraternity Portal". The body opens "Dear Walter Phoneson" with no "Bro", although the summons says "Dear Brother". It has one big button, "Accept Invitation & Setup Password" (Title Case, and "Setup" should be "Set up"), and says the link expires in 14 days. It is short and clear.
- Invite page: clean and readable at 375 px, and it shows his name and email so he knows it is meant for him. Logo tile shows **"TH"**: the initials of "**Th**e Lodge…" (should be "LF" or the lodge badge). Browser tab title ends in "**- Laravel**" on every page.
- Too-short password: a clear red box says "The password field must be at least 8 characters." The wording is a little technical ("field"), and it **clears both password boxes**, so he has to type both again.
- The right password signs him straight in, with the message "Welcome to The Lodge of Fraternity! Your access has been confirmed." Good: no second sign-in.

### 2. First look at the member area (Overview)
- Header: a "CM" tile, a lodge dropdown, a bell and "WP". These are fine to tap. The tab bar (Overview, Calendar, Events, Meetings, …) **scrolls sideways**. The 5th tab shows only as a sliver, and **News, Dues and My details are hidden off-screen** with no "more" hint. A member looking for "Dues" will not find it without swiping the tab bar.
- "Member standing: **MEMBER**" (all caps, and it says nothing a member needs to know). "Attendance rate **100%** – 0 of 0 sessions attended" is wrong-looking for someone who has attended nothing. Show "No meetings recorded yet".
- "Meetings and summonses" heading and its "3 meetings invited" counter **collide** on one line at 375 px.
- **Club broadcasts shows raw HTML**: the digest preview starts `<div style="font-family: Arial…`, and others show `<p>Mandatory circular.</p>` and `<b>November</b>`. This is the most visibly broken thing a member sees on the home page. Strip the tags for the preview.
- The "Membership dues" box says "Senior Rower Tier, **£35.00 a month**". (This is seed data from a rowing club, but see step 7.)

### 3. Summons email → private RSVP link (no sign-in)
- **Email arrived**. Subject: "The Lodge of Fraternity - Official Summons for November Regular Meeting (Monday 23 November 2026)". It is long but complete. The body gives the date, the time and the venue.
- **Missing from the email**: dress code, rehearsal time, dining price, **the reply-by date** (18 Nov) and any business or agenda. "Please record your attendance and dining RSVP in the member portal" comes just before a link that does *not* need the portal, which is mildly confusing. The link text "Reply to the summons" is good, and so is "This link is private to you".
- RSVP page: good. One card shows date, start time and rehearsal, venue and dress code, followed by three big tiles (dining / meeting only / apologies, each at least 60 px tall). **Venue is cut off** ("Masonic Hall, High Stree…") and cannot be expanded. The reply deadline is not shown here either.
- "Attending meeting and dining (£20.00)" is **already selected** when the page opens. A member who taps Submit without looking has booked and owes for dinner.
- Add a guest: only name and "Home club or lodge". You **cannot say whether the guest dines or give their dietary needs**, yet the guest was saved as dining at £20 (`meeting_rsvp_guests.attending_dining = true, dining_fee 20.00`). Nothing on screen says the guest costs £20 or that the total is **£40**.
- "Bank transfer details" shows sort code and account number but **no account name, no amount and no reference**. The "Copy ref" button copies a reference (`SUMMONS-1-PHONESON`) that is never shown on screen.
- Submit: a green "Your RSVP response has been submitted successfully." appears at the top and the form keeps its values. Good. But the button still says "Submit my RSVP" and there is no summary ("You're attending and dining with 1 guest: £40 to pay by …").
- **No confirmation email** for the RSVP.

### 4. The meeting reply in the member area
- The Overview card now says "Attending with dining · **Unpaid**", with no amount. The Events tab card is the same plus "Replies close 18 Nov 2026, 23:59". That deadline appears only here.
- "Edit response" opens a *different* form from the emailed one. It has tiles "Meeting and dining / Meeting only / Apologies", guest name and **guest dietary** (no home lodge). It shows **no prices and no payment details at all**. The two forms for the same reply ask for different things.

### 5. Booking the dinner event (self + guest, menus)
- Found it on Overview ("RSVP & select menu") and on Events. The Events card says "**£25.00** · 4 places left" and "3-course dinner included with ticket". But the booking form charges **£35 General Admission + £25 meal** per person. The advertised price, the description and the form disagree.
- The booking form opens as a dialog that **scrolls inside itself** (sticky header and footer, about 470 px of form between them). "I'm going / Maybe / Can't go" tiles are fine to tap. There are 6 places left.
- Ticket type dropdown starts at "Choose a ticket type", yet **the total already reads £35.00**. Early Bird (£25) is still offered 10 days before the event, and its cut-off is not stated.
- "I'm having the meal (£25.00)" is a **16 px checkbox**. The label is tappable, which is fine, but the box itself is small. Ticking it shows Starter / Main / Dessert dropdowns. Each has **only one dish** and still has to be chosen by hand ("Choose" → the only option).
- Guest: "Guest's full name" is squeezed beside a "Remove" link. The email placeholder is cut off ("We'll email them a confirmation of t…").
- Submitting with no guest ticket type gives an inline red "Please choose a ticket type." under the right field. Good. **The error stays on screen after a ticket is picked.**
- The total updates (£95 → £120) after a delay of about 1–2 s while the server re-prices. During that delay the old number shows, so a fast tapper sees the wrong total.
- **No "how would you like to pay?" step**, because the lodge had no options. The booking saved with no payment method.
- After "Confirm booking" the dialog closes and the page shows **no confirmation message**. The card changes to "RSVP confirmed".
- **Booking email arrived**. It lists each person with their three courses and dietary note, then "Total: £120.00" and "Manage booking". It has **no payment reference, no bank details, no due date and no way to pay**. The guest email was left blank, so the guest got nothing, as expected.

### 6. Changing my mind: edit, then cancel
- "Edit RSVP & dining" reopens the same dialog, still titled "**Book your place**". At the top it now shows a "Payment: NOT PAID YET – Total £120 – Still to pay £120" box. At the bottom it shows "How would you like to pay?" (Bank transfer / Pay later, **bank pre-selected**) and the new total.
- Unticking the guest's meal **cleared the guest's three menu choices**. Re-ticking means choosing them all again. While editing, the box at the top still says £120 and the one at the bottom says £95. **Two different totals are on screen at once.**
- Bank transfer says "You'll get the bank details and your payment reference once you've booked." After "Update booking" the dialog closes with **no message and no email** (a change of total and payment method is not confirmed anywhere). The bank details (account name, sort code, account number, reference `EVT-1-00075`, "use the reference exactly") turn up **only inside the edit dialog**, mid-form, if you reopen it. The lodge's reference prefix ("LOF") was not used.
- Cancel: there is no "Cancel booking" button. You tap "Can't go", which collapses the dialog to "Your reply" with "Save reply". There is **no warning that the guest's place goes too**, and nothing about money. After saving, the "RSVP confirmed" badge simply disappears. The card shows no "Not going" state, and the button still says "Edit RSVP & dining". **No cancellation email.** In the database the booking is `declined` but still `total 95.00, payment_status unpaid`. The treasurer may see it as money owed.

### 7. Checking what I owe and my dues
- **There is no single "what I owe" place.** Meeting dining shows "Unpaid" with no amount. The event amount and bank details are only inside the booking dialog. Dues (hidden tab) shows nothing about either.
- Dues page: "Available membership plans: **Senior Rower (Full Access) £45.00 monthly**, Active subscription tier". The Overview said **£35.00 a month**. The two pages disagree, and "Available plans" reads like a shop rather than "your dues". The receipts table **runs off the right edge** (STATUS and later columns are cut off) and says "No billing receipts found for this club."

### 8. Updating my profile
- Two profile pages: "Profile and photo" in the avatar menu (`/members/profile`: name, email, photo, password) and "My details" (hidden tab, `/members/{club}/profile`: name, email, photo, phone, emergency contact, dietary notes). Both have a photo upload. The "WA" initials there disagree with "WP" in the header. The avatar menu also lists "**API tokens**", which means nothing to an ordinary member.
- The dietary notes box was **empty**, although Walter had typed "No shellfish" in both RSVPs. Saving phone, emergency contact and dietary note worked. The success message appears **at the top of the page**, out of sight of the Save button at the bottom. A member would think nothing happened.
- **The new phone number did not reach the lodge's member record.** It was saved on `club_user.phone`, while the Secretary's member record (`club_acc_members.phone`, used by the Members screens) stayed empty.

## What worked well
- The invite takes him straight to a signed-in portal, with no second login.
- The emailed RSVP link needs no password and has large, clear choice tiles. Values are kept after submit, and the success message is plain.
- Inline booking validation points at the right field.
- The booking email lists every person's dishes and dietary notes. The organiser-facing content is right.
- The server re-prices the booking, so the final saved totals were correct (£120, then £95).
- Buttons and tiles are mostly 44 px or more. Text sizes are readable and nothing scrolled the whole page sideways.

## Top 10 friction points (ranked)

| # | Screen | What happened | Suggested change |
|---|---|---|---|
| 1 | Booking: after confirm or update; booking email | Bank transfer promises "bank details and your payment reference once you've booked", but they appear nowhere after booking: no message, no email, only buried inside the edit dialog. | After saving, show a confirmation panel with amount, payee, sort code, account number and reference, and put the same block plus a due date in the booking email. Send an email on update too. |
| 2 | Member area (all) | There is no place that answers "what do I owe?". Meeting dining shows only "Unpaid", event money hides in the dialog, and Dues ignores both. | Add an "Amount to pay" list on Overview and Dues: each meeting or event, amount, how to pay, reference. |
| 3 | Overview: Club broadcasts | Raw HTML (`<div style=…`, `<p>…</p>`) shown as text. | Strip tags (or render the sanitised HTML) for the preview, and link to the full newsletter. |
| 4 | Emailed RSVP page | Guest is silently charged £20 dining. There is no guest dining choice, no total, and "Attending and dining" is preselected. | Add a "dining" toggle per guest, show a running total ("You + 1 guest dining: £40"), and start with nothing selected. |
| 5 | Event card vs booking form | Card says "£25.00", the text says dinner is included, the form charges £35 ticket + £25 meal. | Show "From £X" built from `EventPricing`. Make the meal line say "included" or show the extra price on the card. Lodges need help wording this. |
| 6 | Member tab bar | Dues and My details are hidden off the right of a sideways-scrolling bar at 375 px. | Wrap to two rows, add a "More" menu, or put Dues and My details in the avatar menu. |
| 7 | Cancel booking | Cancelling is "Can't go" → "Save reply". There is no warning that guests are removed and no email afterwards. The card loses its badge but shows no "Not going" state. The booking stays `unpaid £95`. | Add a "Cancel my booking" button with a confirm step naming the guests. Send a cancellation email. Show "You're not going". Zero or close the amount owed. |
| 8 | My details | The phone number saves to `club_user` but not to the lodge member record the Secretary uses. The success message is off-screen. Dietary notes typed in RSVPs are not reused. | Write phone to `club_acc_members` as well (or read one place). Show the save message near the button or as a toast. Pre-fill dietary notes from the profile. |
| 9 | Summons email | Missing the reply-by date, dress code, rehearsal time and dining price. Nothing re-sends it to members who join after publishing. | Add those lines (they are on the RSVP page already) and add a "send to new members" action on the meeting. |
| 10 | Booking dialog details | The stale "Please choose a ticket type" stays after a pick. The total shows £35 before any ticket is chosen and lags 1–2 s. Unticking a meal wipes menu choices. One-dish courses must be chosen by hand. The title stays "Book your place" when editing. Two totals show while editing. | Clear the error on change. Show "—" until a ticket is picked and a spinner while repricing. Keep menu choices. Auto-select single options. Title it "Change your booking". Show one total. |

Also noted (lower): the "TH" initials logo; "- Laravel" in tab titles; "MEMBER" standing card and "100% (0 of 0)" attendance; the heading colliding with its counter on Overview; the invite email misses "Bro" and uses Title Case; the password error clears both boxes; the RSVP venue is cut off; the "Copy ref" reference is never shown; the two RSVP forms differ (home lodge vs guest dietary); Dues shows £45 vs £35 on Overview and its table runs off the edge; two profile pages, with "WA" vs "WP" initials; "API tokens" in a member's menu; the lodge's bank reference prefix is not used for event references.

## Not covered
- Card or PayPal payment (switched off in e2e). Paying, or a treasurer marking the booking paid. Payment reminder emails.
- The guest's own confirmation email (no guest email given). The waiting list. Promo codes.
- Calendar, News and Meetings tabs beyond the summons cards. Photo upload. Password or 2FA pages. Light mode (the pane was in dark mode).
