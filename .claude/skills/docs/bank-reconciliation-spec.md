Act as a Principal Full-Stack Engineer and Laravel / Livewire / Tailwind Architect.

I am building a multi-tenant club and Masonic lodge management SaaS platform with an embedded accounting module (integrated with Liberu Accounting ERP).

I need you to implement an end-to-end **Bank Reconciliation Module** that accurately replicates Xero’s side-by-side reconciliation interface, tailored specifically for club operations.

---

### 1. Functional & Accounting Requirements

1. **Transaction Matching Scenarios:**
   - **Scenario A: Direct Debit Subscriptions in Advance (Prepayments)**
     - Reference pattern: e.g. `DD 1418 LORD` or `SUBS <SURNAME>`.
     - Do NOT link to an invoice. Credit to Balance Sheet Liability account `2150 - Subscriptions in Advance` (Member Deferred Income).
     - Link to `member_id` and update their running credit balance toward the upcoming April 1st renewal.
   - **Scenario B: Open Invoice Match**
     - Matches incoming lump-sum BACS transfers directly to an open invoice (e.g., `INV-2026-0001`) and marks it as `PAID`.
   - **Scenario C: Festive Board Dining Fees & Guest Splits**
     - References containing meeting tags, `DINING`, or `BURNS`.
     - Can be paid before or after meetings, or as a lump sum covering multiple diners (e.g., £40.00 covering member + guest).
     - Credited to `4100 - Festive Board Dining Income`, and marks the corresponding `rsvps` / `rsvp_guests` rows as `payment_confirmed = true`.
   - **Scenario D: SumUp / Card Reader Batch Payouts**
     - Single net deposit (e.g., `SUMUP PAYOUT £196.62`).
     - Clears the gross total (£200.00) from `1220 - SumUp Clearing Account` and posts the fee (-£3.38) to `7500 - Merchant / Bank Fees`.
   - **Scenario E: Internal Bank Transfers**
     - Quick movement between Current, Savings, and Benevolent/Charity accounts.

2. **Auto-Suggest vs. Fallback:**
   - Run incoming lines through `BankMatcherService`.
   - If confidence is high (>90%), display the green pre-filled suggestion with the center blue **[OK]** button.
   - If unmatched or manually edited, fall back to three streamlined tabs: `[Allocate / Create]`, `[Match Invoice]`, and `[Transfer]`.
   - Include a checkbox: `[x] Remember this reference alias for future imports` (saving to `bank_rules`).

---

### 2. Required Design System & HTML Layout

You MUST use the exact 3-column CSS Grid structure (`grid-cols-[1fr_52px_1.25fr]`) with negative margin alignment (`-mx-[1px]`) so the center OK button bridges the left and right cards seamlessly.

Embed and adapt this proven markup into the Livewire Blade component:

```html
<!-- Single Reconcile Row (Auto-Matched Direct Debit Example) -->
<div class="grid grid-cols-[1fr_52px_1.25fr] items-stretch mb-4">
  
  <!-- Left: Statement Line -->
  <div class="bg-white border border-slate-300 rounded-l p-4 flex flex-col justify-between shadow-xs">
    <div>
      <div class="flex justify-between items-start text-slate-400 text-[11px] mb-1">
        <span>{{ $row->transaction_date->format('d M Y') }}</span>
        <button class="text-[#008ab3] hover:underline font-normal text-[11px]">Options ▾</button>
      </div>
      <div class="font-bold text-slate-900 text-[13px] tracking-tight">{{ $row->reference }}</div>
      <div class="text-slate-400 text-[10px] font-semibold tracking-wider mt-0.5 uppercase">{{ $row->transaction_type }}</div>
      <a href="#" class="text-[#008ab3] hover:underline text-[11px] inline-block mt-2">More details</a>
    </div>
    <div class="pt-3 border-t border-slate-100 grid grid-cols-2 text-right">
      <div class="text-slate-400 text-[11px]">Spent {{ $row->spent ? number_format($row->spent, 2) : '' }}</div>
      <div>
        <span class="text-slate-400 text-[11px] block">Received</span>
        <span class="font-bold text-slate-900 text-[15px]">{{ number_format($row->received, 2) }}</span>
      </div>
    </div>
  </div>

  <!-- Center: Gutter & OK Action Bridge -->
  <div class="relative flex items-center justify-center bg-transparent z-10 -mx-[1px]">
    @if($row->is_matched)
      <button wire:click="reconcile({{ $row->id }})" class="bg-[#008ab3] hover:bg-[#007496] active:bg-[#005f7a] text-white font-bold text-xs py-3 px-3 rounded shadow transition-transform active:scale-95">
        OK
      </button>
    @endif
  </div>

  <!-- Right: Ledger Allocation / Match -->
  <div class="bg-white border border-slate-300 rounded-r p-3.5 flex flex-col justify-between shadow-xs">
    <div>
      <!-- Streamlined Tabs -->
      <div class="flex justify-between items-center border-b border-slate-200 pb-2 mb-3 text-[12px]">
        <div class="flex gap-5">
          <button type="button" class="text-slate-500 hover:text-slate-800">Match</button>
          <button type="button" class="text-[#008ab3] font-bold border-b-2 border-[#008ab3] pb-2 -mb-[9px]">Create</button>
          <button type="button" class="text-slate-500 hover:text-slate-800">Transfer</button>
        </div>
        <button type="button" wire:click="openSplitModal({{ $row->id }})" class="text-[#008ab3] hover:underline text-[11px] font-medium">Split Payment</button>
      </div>

      <!-- Compact Form Grid -->
      <div class="space-y-2 text-[12px]">
        <div class="grid grid-cols-[45px_1fr_40px_1fr] gap-2 items-center">
          <label class="text-slate-500 text-right font-medium">Who</label>
          <input type="text" wire:model.defer="rows.{{ $row->id }}.contact_name" class="border border-slate-300 rounded px-2 py-1 text-slate-800 text-[12px] bg-[#f9fcff]">
          <label class="text-slate-500 text-right font-medium">What</label>
          <select wire:model.defer="rows.{{ $row->id }}.account_code" class="border border-slate-300 rounded px-2 py-1 text-slate-800 text-[12px] bg-white">
            <option value="2150">2150 - Subs in Advance</option>
            <option value="4100">4100 - Dining / Festive Board</option>
            <option value="4000">4000 - Subscription Income</option>
          </select>
        </div>

        <div class="grid grid-cols-[45px_1fr] gap-2 items-center">
          <label class="text-slate-500 text-right font-medium">Why</label>
          <input type="text" wire:model.defer="rows.{{ $row->id }}.description" class="border border-slate-300 rounded px-2 py-1 text-slate-800 text-[12px] bg-white">
        </div>
      </div>
    </div>

    <!-- Bottom Options -->
    <div class="flex justify-between items-center pt-2.5 mt-2 border-t border-slate-100 text-[11px]">
      <label class="text-slate-600 flex items-center gap-1.5 cursor-pointer">
        <input type="checkbox" wire:model.defer="rows.{{ $row->id }}.remember_rule" class="rounded text-[#008ab3]"> Remember rule for future imports
      </label>
      <button type="button" wire:click="reconcile({{ $row->id }})" class="text-[#008ab3] hover:underline font-semibold">Save & Reconcile</button>
    </div>
  </div>

</div