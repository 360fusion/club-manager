<script setup>
// Subscriptions tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
defineProps({
    form: { type: Object, required: true }
});
</script>

<template>
    <div class="space-y-6">
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">💳 Subscriptions & Billing Settings</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure grace periods, automated dues invoicing, and payment receipt footers.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Overdue Grace Period (Days)</label>
            <input v-model.number="form.dues_grace_period_days" type="number" min="0" max="180" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            <p class="text-[11px] text-slate-400 mt-1">Days after due date before member status defaults to overdue.</p>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Auto-Invoice Lead Time (Days Before Cycle)</label>
            <input v-model.number="form.auto_invoice_days_before" type="number" min="0" max="90" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            <p class="text-[11px] text-slate-400 mt-1">Days prior to membership renewal to dispatch automated invoices.</p>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Membership Year Start Date</label>
            <input v-model="form.membership_year_start" type="date" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            <p class="text-[11px] text-slate-400 mt-1">Official annual start date for club membership dues and subscription billing.</p>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">VAT / Tax Registration Number</label>
            <input v-model="form.tax_registration_number" type="text" placeholder="GB 987 6543 21" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono" />
          </div>

          <div class="sm:col-span-2">
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Receipt & Invoice Footer Notes</label>
            <textarea v-model="form.receipt_footer_notes" rows="3" placeholder="Thank you for supporting our club." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500"></textarea>
          </div>
        </div>
      </div>
    </div>
</template>
