Here is the complete, self-contained implementation specification prompt. You can save this file directly as `.tasks/candidate-pipeline-and-compliance-spec.md` or paste it straight into your coding session:

---

```markdown
# TASK SPECIFICATION: Candidate Pipeline, Compliance & Mentorship Domain

## 1. Architectural & Isolation Mandate
We are continuing the build of the decoupled lodge management suite within `app/Domains/ClubAccounting/` (or `app/Domains/LodgeAdministration/`), integrated as an upstream-safe client of **Liberu Accounting ERP**.

### Hard Rules:
- **Zero Core Tampering:** Do NOT edit or modify Liberu core models, migrations, or vendor folders.
- **Prefix Consistency:** All new database tables must use the `club_acc_` prefix.
- **Upstream Separation:** Candidate invoicing must be executed via `CandidateInvoiceService`, calling Liberu's public `Invoice` and `JournalEntry` models without hardcoded foreign keys on core Liberu tables.
- **Constitutional Guardrails:** Strictly enforce United Grand Lodge of England (UGLE) constitutional rules:
  - Minimum 28-day statutory interval between Initiation (1st) and Passing (2nd), and between Passing (2nd) and Raising (3rd) (Rule 172).
  - Maximum 3 black balls for exclusion during open lodge ballot (Rule 165).
  - Candidate notice must be generated for the lodge summons prior to the ballot meeting (Rule 159).

---

## 2. Domain Directory Layout to Scaffold

```text
app/Domains/ClubAccounting/
├── Enums/
│   ├── CandidateStage.php           <-- Pipeline stages
│   ├── InterviewRecommendation.php  <-- 'proceed', 'reject', 'defer'
│   ├── BallotResult.php             <-- 'approved', 'excluded_blackballed'
│   └── MentorshipDegreeLevel.php    <-- '1st_degree', '2nd_degree', '3rd_degree'
├── Livewire/
│   ├── CandidatePipelineKanban.php  <-- Visual stage board & drawer
│   ├── CandidateFormPModal.php      <-- Digital UGLE Form P registration
│   ├── CoffeeInterviewModal.php     <-- 2-Mason vetting sign-off
│   └── MentorshipChecklistModal.php <-- Post-degree mentoring tracker
├── Models/
│   ├── ClubCandidate.php
│   ├── ClubCandidateInterview.php
│   ├── ClubCandidateFormP.php
│   └── ClubCandidateMentorshipTask.php
├── Notifications/
│   ├── CandidateInterviewAssignedNotification.php
│   └── CandidateBallotPassedNotification.php
└── Services/
    ├── Candidate/
    │   ├── CandidateWorkflowService.php     <-- State transitions & UGLE validations
    │   ├── FormPExportService.php           <-- Generates UGLE/Provincial Form P PDF
    │   └── CandidateFeeGateService.php       <-- Syncs with Liberu joining invoice
    └── Summons/
        └── CandidateSummonsNoticeAdapter.php <-- Feeds candidate data to Summons Builder

```

---

## 3. Database Schema & Migrations (`club_acc_*`)

Generate standard Laravel migrations using the `club_acc_` prefix:

### 1. `club_acc_candidates`

The master record for enquirers advancing toward membership:

* `id` (ULID or bigint primary key)
* `club_id` (foreign key / tenant context)
* `member_id` (foreign key nullable to `members` once initiated)
* `first_name`, `last_name`, `email`, `phone`
* `date_of_birth` (date, must validate >= 21 years old, or >= 18 with dispensation flag)
* `occupation` (string)
* `residential_address` (text)
* `lead_source` (enum: `provincial_website`, `lodge_website`, `personal_referral`, `open_day`, `other`)
* `assigned_interviewer_id` (foreign key nullable to `members`)
* `proposer_member_id` (foreign key nullable to `members`)
* `seconder_member_id` (foreign key nullable to `members`)
* `mentor_member_id` (foreign key nullable to `members`)
* `stage` (enum: `enquiry`, `coffee_chat`, `lodge_committee`, `ballot_pending`, `balloted_approved`, `balloted_rejected`, `initiated`, `passed`, `raised`, `gl_cert_presented`, `withdrawn`)
* `target_ballot_meeting_id` (foreign key nullable)
* `target_initiation_meeting_id` (foreign key nullable)
* `target_passing_meeting_id` (foreign key nullable)
* `target_raising_meeting_id` (foreign key nullable)
* `initiation_date`, `passing_date`, `raising_date`, `gl_certificate_date` (nullable dates)
* `ugle_membership_number` (string nullable, indexed)
* `provincial_clearance_confirmed` (boolean default false)
* `joining_invoice_id` (foreign key nullable to Liberu `invoices`)
* `joining_fee_settled` (boolean default false)
* `timestamps`

### 2. `club_acc_candidate_interviews`

Records vetting meetings by assigned brethren and the Lodge Committee:

* `id`
* `candidate_id` (FK to `club_acc_candidates`)
* `interview_type` (enum: `informal_coffee`, `standing_committee`)
* `interviewer_member_id` (foreign key to `members`)
* `recommendation` (enum: `proceed`, `reject`, `defer`)
* `finances_and_dues_discussed` (boolean default false)
* `time_commitment_discussed` (boolean default false)
* `family_support_discussed` (boolean default false)
* `notes` (text nullable)
* `interview_date` (date)
* `timestamps`

### 3. `club_acc_candidate_form_p`

Statutory UGLE Registration data (Form P):

* `id`
* `candidate_id` (FK to `club_acc_candidates`)
* `full_name`, `previous_names` (nullable)
* `place_of_birth` (string)
* `residence_history_5_years` (json)
* `conviction_declaration` (boolean: confirms no indictable criminal convictions)
* `bankruptcy_declaration` (boolean: confirms never declared bankrupt/insolvent)
* `belief_in_supreme_being` (boolean)
* `signed_by_candidate_at` (timestamp nullable)
* `candidate_signature_data` (text nullable / digital signature hash)
* `proposer_signed_at`, `seconder_signed_at` (timestamps nullable)
* `timestamps`

### 4. `club_acc_candidate_mentorship_tasks`

* `id`
* `candidate_id` (FK to `club_acc_candidates`)
* `degree_level` (enum: `1st_degree`, `2nd_degree`, `3rd_degree`, `general`)
* `title` (string)
* `is_completed` (boolean default false)
* `completed_at` (timestamp nullable)
* `completed_by_member_id` (foreign key nullable)
* `timestamps`

---

## 4. Business Logic & Validation Engine

### A. Statutory UGLE Validation (`CandidateWorkflowService`)

1. **Age Verification:** Rejects initiation scheduling if under 21 years of age unless `dispensation_attached = true`.
2. **28-Day Degree Interval Enforcer (Rule 172):**
* When attempting to transition from `initiated` to `passed`: Throw validation exception if `passing_date < initiation_date + 28 days`.
* When attempting to transition from `passed` to `raised`: Throw validation exception if `raising_date < passing_date + 28 days`.


3. **Blackball Constitutional Rule (Rule 165):**
* If recorded black balls during ballot >= 3: Stage is forced to `balloted_rejected`. Flags candidate as ineligible for proposition in the lodge for 12 months.



### B. Accounting & Invoicing Gate (`CandidateFeeGateService`)

* When a candidate reaches `balloted_approved`, automatically trigger `CandidateInvoiceService::generateInitiationInvoice($candidate)`:
* Line 1: Lodge Joining / Initiation Fee
* Line 2: UGLE Registration & Grand Lodge Certificate Fee
* Line 3: Annual Lodge Subscription (per `club_acc_lodge_settings.mid_year_billing_policy`)


* **Ceremony Gate:** Prevent the candidate from being added to the Master's Initiation Summons agenda until the incoming bank reconciliation matches the invoice balance and sets `joining_fee_settled = true`.

### C. Automatic Mentoring Seeder

Upon advancing into `initiated`, `passed`, or `raised`, seed default checklist tasks:

* **Initiated:**
* "Deliver First Degree Tracing Board walk-through"
* "Explain Festive Board customs, firing, and toasts"
* "Assist member in setting up recurring monthly Standing Order for dues"


* **Passed:**
* "Review Second Degree Working Tools and Tracing Board"
* "Accompany Candidate on official fraternal visit to another lodge"
* "Rehearse test questions for the Third Degree"


* **Raised:**
* "Deliver Third Degree Traditional History explanation"
* "Introduce Holy Royal Arch (Chapter) representative"
* "Prepare for Grand Lodge Certificate investiture in open lodge"



### D. Summons Builder Integration (`CandidateSummonsNoticeAdapter`)

Provide helper methods callable by your existing Summons Builder:

* `getPendingBallotNotices()`: Returns full name, age, profession, home address, proposer, and seconder formatted specifically for summons circular printing.
* `getPendingCeremonyAgendaItems()`: Returns candidates cleared for Initiation, Passing, or Raising.

---

## 5. User Interface Specifications

### UI View: Interactive Candidate Pipeline (`CandidatePipelineKanban.php`)

Build a Livewire 3 / Tailwind CSS Kanban board featuring:

* **Columns (Swimlanes):**
1. *Enquiry & Triage*
2. *Informal Coffee Chat* (Requires 2-Mason sign-off badge)
3. *Lodge Board / Committee* (Requires Form P submitted badge)
4. *Pending Ballot* (Summons notice generated badge)
5. *Ballot Approved* (Financial payment gate indicator: Green if paid, Amber if awaiting bank clearance)
6. *1st Degree: Entered Apprentice*
7. *2nd Degree: Fellow Craft*
8. *3rd Degree: Master Mason & GL Cert*


* **Visual Card Elements:**
* Candidate Name and Lead Source badge.
* Assigned Mentor avatar/initials.
* Target Meeting Date pill.
* Mentorship Progress Bar (e.g. `2/4 tasks complete`).
* Invoice Status Tag (`£290.00 PAID` or `UNPAID LOCK`).


* **Interactive Modals:**
* **Coffee Interview Modal:** Checklist for the two assigned brethren to record character, financial understanding, and family commitment notes.
* **Form P Slide-Over:** View and print the completed statutory declarations and address history.
* **Mentorship Drawer:** Allows the appointed Lodge Mentor to check off tasks in real time as the brother learns his ritual and customs.



```

***

### How this connects with what you have built:
1. **The Summons Builder:** Feeds candidate names, addresses, proposers, and degree work directly into the monthly circular.
2. **Liberu Ledger & Reconciliation:** Automatically bills the initiation package and verifies bank clearance before the ceremony can go ahead.
3. **The Membership Officer & Mentor:** Provides a central dashboard to ensure no enquirer is forgotten, statutory UGLE intervals are enforced, and new initiates are supported through to Master Mason.

```