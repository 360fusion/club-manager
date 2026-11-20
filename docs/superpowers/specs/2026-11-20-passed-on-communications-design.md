# Passing on summonses and communications from other lodges, Province and Grand Lodge

## Why

A lodge Secretary today forwards by email every summons of interest from the sister lodges that meet in the same
Masonic hall and from the lodges his lodge has reciprocal fraternal visits with, and every communication from
Provincial and Grand Lodge. These arrive with him by email or as PDFs: the other lodges and Province are not on Club
Manager. Members get all of it, whether they want it or not, and the Secretary does it all by hand.

The aim: the Secretary passes each item on once, in a minute, and each member gets only the kinds they asked for, the
way they asked for them, on the website and in the app alike.

## What already exists (build on it)

- Following a lodge in the directory (`LodgeFollowController`, `lodge_follows` with `in_calendar` and
  `notify_summons`; private: a lodge never sees who follows it). `ClubNotifier::toFollowers` tells followers when a lodge
  on the platform publishes a summons.
- The Secretary's "send a notice" page (`NoticeAdminController`, `ClubNotice`, `SendClubNotice`, `NoticeAudience`):
  own lodge's members only, by bell, phone and email.
- The bell and notifications page (`ClubNotification`, categories, `NotificationController`) and phone pushes
  (`PushDatabaseNotification`, which skips categories in `users.push_muted`, set on `Members/AppSettings`).
- The directory's halls: `lodges.masonic_hall_id`, `Club::lodge()`; `Lodge::isManaged()` / `Lodge::club()` say a lodge
  is on the platform.
- The app is the website in a Capacitor shell (`server.url` is the site), so every page and setting is shared: building
  it on the website builds it in the app. Nothing needs syncing separately.

## Decided with the user

**Four kinds**, each a separate choice for members:
1. Sister lodges in our hall
2. Reciprocal visit lodges
3. Provincial communications
4. Grand Lodge communications

The lodge's own summonses and notices are unchanged and are not one of these choices.

**The lodge's lists.** Sister lodges start as every directory lodge in the same hall as the club's own lodge. The
Secretary can untick any of them, and can add or remove other lodges (picked from the directory) on either list. The
hall list follows the directory: a lodge added to the hall later appears unless unticked.

**Passing something on.** The Secretary (or an owner or admin; lodges can change this in their permission matrix like
any section) picks the kind; for kinds 1 and 2, the lodge from the lodge's lists. They give a title, an optional note,
and attach the PDF or paste the text. For a summons, they can give the meeting date, time and venue. Then they send it.
What the member sees says who passed it on ("Passed on by WBro A Smith, Secretary of Lodge X").

**Who receives it.**
- Active members of the sending lodge (not visitors), according to their choices for that kind.
- For kinds 1 and 2: also anyone on the platform who follows that lodge and asked to hear about its summonses
  (`notify_summons`), even if they are not in the sending lodge. Followers stay private: nobody, the Secretary
  included, sees who they are or how many there are.
- Kinds 3 and 4 only go to the sending lodge's own members.

**Members' choices** (one settings page, on the website and in the app):
- For each kind: app notice, email, both, or off. A member who has changed nothing gets **both** (as now).
- Mute one lodge: a member can mute a particular lodge so nothing passed on about it reaches them, whatever the kind.
  Also offered from the item itself ("Stop hearing about Lodge Y").
- Calendar: an option for whether meetings from passed-on summonses go into their calendar (website and app calendar,
  with the existing "ask to visit" link where the lodge is on the platform).

**Members who have never signed in.** Members on the lodge roll (`club_acc_members`) with an email but no account get
every kind by email. Each email has a link to change their choices or unsubscribe without signing in. Their choices
are email or off (they have no app).

**Lodges already on the platform.** If a lodge on the lists is on Club Manager and publishes its own summons, it
reaches the members of every lodge that lists it, under the right kind, respecting their choices. What they get:
- **The key facts:** date, time, venue, dress and dining, which visitors already see.
- **The full summons:** only where that lodge lets visitors see it (`VisitorSummons`).

The Secretary is not asked to pass on that summons. The form says the lodge publishes its own. He may still attach
the full PDF he received: that adds the PDF to the same item (same lodge and meeting date) and sends no second notice.
Nobody gets it twice.

## Goals

- The Secretary spends less time than forwarding an email.
- Members choose kinds, channels and lodges; one set of choices covers the website, the app and email.
- Nobody gets the same summons twice: not from two Secretaries passing on the same lodge's meeting (same lodge and
  meeting date), not as both a member and a follower, not as both a passed-on copy and the lodge's own.

## Non-goals (not now)

- Province or UGLE posting directly with their own accounts (each Secretary passes them on).
- Reading the Secretary's mailbox, or a forwarding email address.
- Reading dates out of the PDF (no PDF parsing dependency is approved).
- Telling the Secretary who opened what.

## Anti-goals (a failure even if it works)

- A member getting the same thing twice, or more email than today with no way to turn it down.
- Anything that reveals who follows a lodge.
- A way for one lodge to flood people across the platform: every item names the lodge and person who sent it, sending
  is rate-limited, and only lodges on the sender's own lists can be chosen for kinds 1 and 2.
- A form that is slower than forwarding an email, especially on a phone.

## Constraints

- Follow the project conventions in CLAUDE.md:
  - **Permissions:** a new admin section goes in `config/club_permissions.php` `route_map` with its own capability.
    The Secretary office counts as well as the roles, as `LodgeAccountsOverview` does for the Treasurer.
  - **Uploads:** `UploadRules`, PDFs only.
  - **Text:** `RichTextSanitizer` for the note.
  - **Mail:** emails queued on the right queue, with a one-click unsubscribe that leads to these choices.
  - **Tests:** factories.
  - **Pages:** phone-first.
- **Attachments:** PDFs are private: stored on the `local` disk and served only to people who received that item and
  to the sending lodge's staff.
- **Visibility:** for a lodge on the platform, only its key facts go to people who aren't allowed to see its full
  summons (`VisitorSummons`).

## Settled after review (my calls, check them)

- **Items for kinds 1 and 2** are a summons (meeting date required; that is what the duplicate check and the calendar
  use) or a plain notice (no date).
- **Followers get summonses only** (that is what `notify_summons` asked for), by bell and phone as today, never by
  email. The existing `followedSummons` path stays as it is.
- **Provincial and Grand Lodge duplicates.** The same circular passed on by several lodges reaches a person once: the
  same PDF (by file hash), or the same title and text, within 60 days.
- **Lodges on the platform.** For a lodge that publishes its own summonses, the Secretary can still pass on its other
  notices.
- **Key facts only from platform lodges.** On the platform no outsider ever sees a lodge's full summons, even an
  approved visitor (`VisitorSummons` shares only the key facts). So "full if shared" means the key facts go to those
  allowed to see them.
- **Members-only lodges.** A lodge that keeps its summonses to members only shares nothing with anyone outside. For
  this feature it counts as not publishing its own, so Secretaries pass on its summons by hand, as for lodges not on
  the platform.
- **Choices are one set per person.** They are not per lodge membership; muting a lodge covers the member who wants
  one hall's lodges and not another's.
- **Members with no account** keep their choices on their roster row (`club_acc_members`); signed-in members keep
  theirs on the user. A person with both uses the user's.

## Left to the builder

- **Data:** table and model names, and whether the kinds are new `ClubNotification` categories or one category with a
  sub-kind. Existing `push_muted` must keep working, and the new kinds must be mutable for phone pushes as well.
- **Where members' choices are stored:** a column on `users` or its own table.
- **Sending:** whether it reuses `SendClubNotice` or a sibling job, with one delivery per person on the `bulk` queue
  as newsletters do (my call: many recipients once followers are included).
- **The item page:** what the member opens from the bell or email (title, note, PDF, meeting details, who passed it
  on, mute link).
- **Calendar default:** my call is on, to match "both" being the default for kinds.
- **Rate limit:** the exact number.
