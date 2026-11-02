# Candidates, officers and committee extras: end-to-end results

Run 2 Nov 2026 against the e2e app (Lodge of Fraternity, club 1; Bath RFC for isolation), PostgreSQL `club_manager_e2e`, real queue worker, Mailpit. Three subagents drove it, mostly over HTTP and the Livewire update protocol (the update URL here is `/livewire-518b5e38/update`, read it from `data-update-uri`), with one visual pass of the candidate board in the browser pane. No application code, tests or docs were changed and nothing was committed. Full per-area notes with more repro detail: `side-session-notes/local_a0cb9e9d-.../{cand-pipeline,officers,committee}.md`.

Environment notes (not app bugs): another session set the seeded club-1 accounts (member.a3, coach, treasurer, admin@example.com) to `club_user.status = past` at about 22:03, so role tests used throwaway users; they were `active` again by 22:20. Port 8001 went down once around 22:00 and was restarted (`PHP_CLI_SERVER_WORKERS=4 php artisan serve --env=e2e --port=8001 --no-reload`, still running). Login and `/sign/{token}` are throttled per IP and shared by all sessions (429s).

## Results

### Candidate pipeline

| ID | Result | Note |
|---|---|---|
| C1 Add form validation | PASS with F90 | Empty, over-long, bad email/source, over-long notes refused; duplicate email shows "already on the board"; future/0001 DOB, letters in phone accepted, names untrimmed. |
| C2 Happy path enquiry to initiated | PASS | One step at a time; committee needs proposer and a different seconder from this lodge; proposed needs recommendation, signed Form P, proposal date; ballot before proposal date and initiation before ballot or after the year refused; initiation creates a "Bro" member with the right club, status and dates; second click makes no duplicate. |
| C2b Not elected | PASS | Ballot not elected writes `ballot` + `reject` events and rejects; no vote counts stored (note field is free text, F96). |
| C2c Crafted jump | FAIL (F62) | `openInitiationModal` + `confirmInitiation` takes an Enquiry candidate straight to Initiated and creates a Member. |
| C2d Existing member email | FAIL (F63) | A second candidate with the same email initiated into a second identical Member. |
| C3 Reject, close, hold, reopen | PASS with F85 | Owner/admin only; reasons and notes required; reopen returns to the stage left; coach, treasurer, member refused on the page and on crafted Livewire calls. |
| C4 History immutability | PASS with F89 | Stage-change events cannot be removed; other candidate's events 404; own notes removable. Notes cannot be edited in the UI. |
| C5 Form P signatures | PASS with F88 | Emails reach proposer and seconder, no unreplaced placeholders, link works; resend invalidates the old link; cancel, decline, sign, double-sign blocked; signature PDF 200 for admin, 403 for other roles, 404/403 for another lodge. |
| C6 Isolation and roles | PASS | Anonymous, coach, treasurer, member, Bath admin all refused on board, detail, PDF and crafted calls. |
| C7 Stale tab, double submit | FAIL (F61) | Stale "Move on" skipped a stage; two parallel advances wrote duplicate events. |
| C8 Board, filters, closed strip | PASS with F87 | Six active columns; Initiated/Rejected/Closed in the strip; held shows a badge; search works (`%` and `_` are wildcards, F87). |
| C9 Rule 159 vetting panel | PASS with F84 | Lists only this club's committee-stage candidates; sign-off writes agenda item and event; no stage check or dedupe. |
| C10 XSS, unicode, 500s, jobs | PASS | Hostile text rendered escaped; unicode fine; no failed jobs; only crafted locked/tampered payloads give 500 (F95). Board badge overflow at 1500px (F86). |

### Annual officers roster and officer election

| ID | Result | Note |
|---|---|---|
| O1 Create roster, validation | FAIL (F69, F73) | Empty/too long refused; any text accepted as the year; same year silently replaced; look-alike spellings make duplicate rosters. Same year in another lodge allowed. |
| O2 Assign officers | FAIL (F70, F71, F72, F68) | One member two progressive refused, other-lodge/unknown member refused; but two members for one single office, resigned/deceased members, silently dropped unknown offices, and a 500 on a duplicate administrative office. |
| O3 Quick-add member | PASS with F83 | Creates a Member, XSS name escaped everywhere; validation gaps for crafted requests. |
| O4 Install and status | FAIL (F60, F64, F65, F82) | Install leaves old officers and has no checks; badges and confirmed status inconsistent. |
| O4c Auto-install | FAIL (F66) | Runs only when the officers page is opened, in meeting-id order. |
| O5 Officers on summons PDF, directory | FAIL (F64, F67) | Summons PDF "OFFICERS" block empty with an installed roster; profile badges change when installing. |
| O6 Officer election endpoints | FAIL (F74) | No screen calls them; status accepts any text; confirm applies a future year at once. |
| O7 Permissions | PASS | Admin and Secretary (coach) allowed; treasurer, member, Bath admin 403; cross-lodge roster/meeting ids 404; matches `route_map` (`officers` = `manage_meetings`). |
| O8 500s, XSS, names | PASS with F71, F83 | Names follow `formatted_rank_name`; escaped. |

### Committee extras

| ID | Result | Note |
|---|---|---|
| M1 Schedule and statuses | FAIL (F77, F91) | Edit on a finalized meeting resets it to draft; sending the pack resets to scheduled; "invites sent" sends none; `99:99` and past dates accepted; double submit makes two meetings. Another lodge's meeting: 404/403 incl. tampered snapshots. |
| M2 Roll call | PASS | Select all committee, roster officers, batch add; other-lodge users ignored. |
| M3 Minute taker, mentions, parser | FAIL (F75, F76, F78) | Mention search case-sensitive and lists past/pending; unmatched @tag assigns a random member; edited or removed lines leave stale tasks. Repeat commit does not duplicate; HTML/emoji safe. |
| M4 Tasks and notification | PARTIAL (F92) | Only tick/untick and "Email" exist in the UI (no create/edit/reassign/delete); notification works; email has no link; each click re-sends. |
| M5 Notices of motion | FAIL (F79) | Only "approve for summons" (status text); bridge service has no caller. |
| M6 Minutes page, pack PDF, pack email | PASS | Admin and Secretary 200 with valid PDF; treasurer, member, Bath admin 403; unauthenticated redirected; pack email reached 3 attendees with the PDF attached through the real queue, no failed jobs, no placeholders (agenda pack still sends). |
| M7 Committee roles | PASS with F81 | Admin only (`manage_members`); invalid values 422; other-lodge users 404; a roster save wipes hand-set roles. |
| M8 Performance, log, vetting modals | PASS | 40 tasks fine; modals open and are refused for another lodge's meeting. |
| X-memberpdf Member summons PDF | FAIL (F59) | See F59. |

## Findings (not fixed)

Numbered from F59 (F59 is the member PDF found outside the target area; F60 onwards are in order of severity within area).

**F59 High (outside the tested area).** `GET /members/lodge-of-fraternity/meetings/{id}/pdf` returns HTTP 500 for every published meeting. `MemberMeetingController::pdf` (line ~88) calls `app(MeetingAdminController::class)->pdf($slug, $id)` and `MeetingAdminController` has no `pdf` method (the only `pdf` is in `MeetingSummonsController`). Steps: sign in as an active member, open a published meeting's summons, click download. Seen: "Call to undefined method MeetingAdminController::pdf()". Route `member.meetings.pdf`.

**F60 High. Installing a roster does not clear last year's officers.** `AnnualOfficerRosterService::confirmRoster` only sets `current_office` for the members on the roster being installed. Steps: roster 2027-2028 (A=WM, B=SW, C=Secretary, D=Almoner), `POST /lodge-of-fraternity/admin/officers/{id}/install`; then roster 2028-2029 with only A and B, install. C and D keep `current_office` Secretary and Almoner in `club_acc_members` and on their profile, and two Worshipful Masters can exist. The "Lodge officers" count and the office filter use `current_office`, so they count past officers forever.

**F61 Medium. Stale tab or double click skips a candidate stage; parallel calls write duplicate history.** `HandlesCandidateActions::advance($id)` moves to `stage->next()` with no check of the stage the page showed. Steps: open the same candidate in two tabs at Enquiry; "Move on" in tab B (First interview); "Move on" in tab A: the candidate ends at Pending: informal meeting with two stage-change events. Two simultaneous `advance` calls from one snapshot wrote two identical "Moved from Enquiry to First interview" events (no lock or `where stage = from`). Fix: pass the expected stage or `stage_entered_at` and refuse if different.

**F62 Medium. A crafted call jumps a candidate to Initiated and creates a Member.** Livewire `openInitiationModal [id]` then `confirmInitiation` with an `initiation_date` on a candidate at Enquiry: the component supplies its own skip reason ("Initiated without the usual steps recorded"), so no proposer, seconder, Form P, committee or ballot is needed and `initiate_by` is not set. Owner/admin only and not reachable from the UI, but it defeats the pipeline rules.

**F63 Medium. Initiating a candidate whose email already belongs to a member creates a duplicate member.** Two candidates with the same email initiated into two active Members with identical email in the same club, no warning (the add form only warns about duplicate candidates). Date of birth and occupation are not carried over.

**F64 Medium. An installed roster is ignored when showing offices.** `Member::loadActiveOffices()` / `getActiveOfficesAttribute()` read only the latest `confirmed` roster. After `install` the roster is `installed`, so badges on the member profile and members list fall back to the single `current_office`. Steps: roster with C = Secretary + Treasurer; status `confirmed`: badges show both; `install`: badges show only Secretary. A member's second office disappears.

**F65 Medium. "Confirmed" has two meanings.** Setting a roster to `confirmed` through the store route ("Approve in committee") or the status route does not call `confirmRoster()`: no `confirmed_at`, no `current_office`. Badges change at once, while the profile "Current office", the officers count and the office filter still show Member.

**F66 Medium. Opening the officers page writes data and can install the wrong year.** `checkAndAutoInstallPassedInstallationMeetings` runs on every GET, loops passed October meetings in id order (not date order) and installs the roster for each year. With an Oct 2026 meeting created before an Oct 2025 one and both rosters `confirmed`, one page view left 2025-2026 installed over 2026-2027; every view repeats it.

**F67 Medium. Officers do not reach the summons.** `summons/pdf.blade.php` takes officers from `meeting.officers_roster`, the meeting's `officerAssignments`, then `club.settings.officers_roster`; the annual roster is never read. `GET /lodge-of-fraternity/admin/meetings/{id}/pdf` with an installed roster shows "OFFICERS FOR 2026" with an empty table, and the member summons page has no officer names. The secretary retypes the officers.

**F68 Medium. Resigned, deceased and historical members can be given offices.** `assertMembersBelongToClub` checks only `club_id`. A roster with a resigned member as WM, deceased as SW, historical as Secretary saves and installs; the profile shows `Resigned` + `Worshipful Master`.

**F69 Medium. The masonic year is free text.** Rule is `required|string|max:20`. Accepted: `hello`, `abc-def`, `1700-1701`, `2099-2100`, `2027- 2028`, `2027-02028`, `2027.0-2028.0` (the last three are duplicate rosters for the period of `2027-2028`). Rosters sort by the text descending, so `hello` sorts first and the page opens on it. Only the Add-a-year modal limits the range.

**F70 Medium. Two members can hold the same single office.** `POST /lodge-of-fraternity/admin/officers` with `[{member_id:A,office:wm},{member_id:B,office:wm}]` saves; install gives two Worshipful Masters. Only the one-progressive-office-per-member rule exists.

**F71 Medium. Duplicate administrative office for one member is an HTTP 500.** `[{member_id:X,office:almoner},{member_id:X,office:almoner}]` hits the unique index `officer_assignments_roster_member_office_unique`. A duplicate progressive office gives a 422 with the misleading text "cannot hold more than one Progressive Office". Roster unchanged.

**F72 Medium. Unknown offices and empty members are silently dropped.** `office:"king"` or `member_id:0` returns success "Officer roster saved" with that entry missing; a typo can erase the year's assignments with a success message.

**F73 Medium. Saving a year is a blind overwrite and downgrades status.** `saveRoster` is `updateOrCreate` + delete all assignments, status defaults to `draft`. The UI's "Save draft" / "Approve in committee" on an installed year turn it into draft/confirmed without touching members; posting `assignments: []` wipes the year; no warning or version.

**F74 Medium. The three officer-election endpoints are not used by any screen and take any input.** `GET/POST /admin/meetings/{id}/officer-election` and `POST .../confirm`: `status` accepts any text up to 50; the year need not match the meeting; POST on an existing year overwrites it; `confirm` applies the roster to members at once (a March meeting can put next year's officers in office), works on an empty roster, and downgrades an installed roster to `confirmed`. Meeting-to-year ignores the `installation_month` setting.

**F75 Medium. @mention search is case-sensitive on PostgreSQL.** `MemberMentionSearchService` uses `like`: "Taylor" finds a member, "taylor", "swift", "zo" find nobody. It also lists pending and past members, and `%` / `_` act as wildcards. Use `ilike`, escape wildcards, filter active.

**F76 Medium. A task whose @tag matches nobody is assigned to a random member.** `CommitteeNotesParserService` falls back to `chair ?? secretary ?? $clubMembers->first()`. `[ ] @brother unknown tag task` and a tag naming a user of another lodge both got `assigned_to_user_id` = the first club user, and the "Email" button mails them. Short tags (`@a`, `@.`, `@-`) match the first member containing that character.

**F77 Medium. Meeting status is reset by edit and by sending the pack, and finalized minutes can diverge.** (a) Open "Edit meeting" on a finalized meeting and save: status becomes `draft` (`finalized_at` left set). (b) "Send pack" sets any meeting, even finalized, to `scheduled`. (c) After `finalizeMinutes` more notes can be typed: `notes_raw` changes while `minutes_final` keeps the old text; Finalize again silently overwrites it.

**F78 Medium. Edited or removed note lines leave stale tasks and motions.** The commit only inserts when no task with the same title exists: editing "Send the letter" to "Send the LETTER (edited)" creates a second task and keeps the first; deleting a line never removes its task or motion; two lines with the same title give one task. Titles over 255 characters are dropped silently with a "Successfully committed 0 tasks" toast; `by 2026-02-30` becomes 2 March.

**F79 Medium. Notices of motion never reach a summons.** The only action is "approve for summons" (status text). `NoticeOfMotionBridgeService::exportToSummons` has no caller. Called by hand it works but has no club check (a Bath RFC motion was exported onto a lodge meeting and showed on its summons), no status check, and can export the same motion twice.

**F80 Low to medium. Reopening a candidate rejected at the ballot keeps the failed ballot.** Ballot not elected, Rejected, Reopen: back at Proposed with `ballot_result = not_elected` and `ballot_at` set, so the checklist ticks "Ballot result recorded" before any new ballot.

**F81 Low to medium. Saving any roster rewrites live committee roles.** `saveRoster` syncs `club_user.committee_role` from the roster being saved whatever its year or status: a draft for 2041-2042 naming a plain member set their role to `member` at once; saving an unrelated empty draft cleared it, and clears hand-set `member` roles (also seen disappearing in the committee run).

**F82 Low to medium. Install has no checks.** Installing a future roster (2030-2031), a past one (1700) or an empty one all succeed; an empty one leaves nobody in office.

**F83 Low. Quick-add member.** A request missing `masonic_rank`, `grand_rank` or `provincial_rank` is a 500 (`Undefined array key`, `OfficerRosterAdminController` lines ~158 and ~168; the modal always sends them). `membership_status` is not validated (`zzz` becomes historical; an active quick member has no email). Duplicate names accepted. A Secretary (`manage_meetings`) can create members although `manage_members` is owner/admin only.

**F84 Low. The vetting panel does not check stage and does not dedupe.** Crafted `loadCandidate` + `signOffCandidate` on an Initiated and a Closed candidate set `committee_recommended_at` and `rule_159_cleared` and add an agenda item; repeated sign-offs add one agenda item each; "Deferred" does not clear `rule_159_cleared`; `candidateId` falls back to a club user id.

**F85 Low. Hold/resume history is misleading.** Hold on an already-held candidate writes a second event and overwrites the date; `resume` on a candidate not on hold still logs "Taken off hold"; `advance` is not blocked while held.

**F86 Low. Board next-action badges overflow their cards at 1500px.** Cards ~162px wide, badge `white-space: nowrap` 188-193px ("Choose proposer and seconder", "Needs Form P and proposal date") overlapping the next column (screenshot taken in the browser pane).

**F87 Low. `%` and `_` act as wildcards in search.** `Candidate::scopeSearch` does not escape them; searching `%` matches every candidate. (Same in the mention search, F75.)

**F88 Low. Form P signature messages and silent no-ops.** `requestFormPSignatures` says "Signature requests sent." even when only one or none was newly sent; resend/cancel with nothing pending return silently.

**F89 Low. Candidate notes cannot be edited and a second removal is a 404.** CLAUDE.md says plain notes can be edited or removed; the component has only `deleteNote`; removing leaves no history; removing an already removed note returns an error page instead of a message.

**F90 Low. Add-candidate form is looser than the detail form.** `saveCandidate` accepts `date_of_birth` 2999-01-01 and 0001-01-01 (the detail page uses `before:today`); phone accepts "abc-xyz !!"; names are stored untrimmed.

**F91 Low. Committee meeting create/edit gaps.** Time `99:99` accepted (overflowed to a different date); past dates accepted; the modal creates `scheduled` and redirects, the index "new meeting" creates `draft`; "Scheduled (invites sent)" sends no invites; two simultaneous saves create two meetings; chair and secretary of a meeting can never be set (pack always says "Worshipful Master / Chairman" and "Lodge Secretary"). Notes over about 1 MB cannot be saved (500 "payload too large").

**F92 Low. Task assignment email and notification.** The mail has no link to the task or meeting (sender and footer say "Laravel" from the e2e `.env`, F4 area); the in-app notification has no `url`/`category`, so opening it goes to the dashboard; every "Email" click sends another mail and notification; `notifyAssignedMember` does not check the person is still in the club. Tasks cannot be created, edited, reassigned or deleted from the UI at all.

**F93 Low. Committee redirect routes leak a club's slug.** `/committee/meetings/{id}` and `.../minutes` redirect any signed-in user to the club's admin URL (treasurer, plain member and a Bath admin all got 302 to `/lodge-of-fraternity/admin/committee/2`); ids are sequential. The admin pages then refuse correctly.

**F94 Low. A task row with an unknown status breaks the whole pack PDF.** Rows in `club_acc_committee_tasks` with status `open` (another session's fixtures) made the pack PDF 500 with `"open" is not a valid backing value for enum TaskStatus`. Not reachable through the app but one bad row blocks the whole pack.

**F95 Info. Crafted Livewire payloads return 500 and log ERROR.** Setting a locked property (`candidateId`, `clubSlug`), tampering with a snapshot, `setStatus('bogus')`, `updateAttendance(id,'nonsense')` or a bad `agendaItemType` give 500; protection holds and nothing changes.

**F96 Info. Candidate ballot and reject notes are free text and accepted vote counts** ("Blackballed 3 against", "Elected unanimously 12-0" copied into both events). The result is stored as documented; nothing discourages counts in the note. The candidates signature-PDF route also serves other signable types of the same club (an accounting year-audit signature id) to owner/admin; same club only.

## Not run

Drawn (image) signatures, expired signature requests, signature requests to signers with a linked login; PDF text of the signed Form P certificate; real browser clicking of the Livewire modals and the Vue officers page (only source read); a truly parallel double submit on the officers store and on initiation (the dev server serialized requests; the candidate advance race was reproduced with 4 workers); a custom `installation_month`; setting a roster to `installed` directly through the status route; actual vetting sign-offs on other sessions' data; task reassign, delete and due-date editing (no such screens); mail to the seeded `member@example.com`.

## Cleanup

All rows created were removed via tinker (prefixed `E2E Cand` / `E2E Off` / `E2E Comm`, users `cand-`, `off-`, `comm-` `@e2e.test`, their rosters, assignments, meetings, tasks, motions, signature requests, candidate events, notifications); candidate events are append-only in the app, so those were removed by `candidate_id` of the prefixed candidates only. Verified afterwards: 0 "E2E Cand" candidates, 0 `cand-`/`off-`/`comm-` users, 0 "E2E Comm" committee meetings. One changed value (`club_user.committee_role` of user 9) was restored to null. Mailpit messages to the throwaway addresses remain (the inbox is shared). The server on port 8001 was restarted once by the candidate agent and is still running.
