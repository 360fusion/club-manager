<script setup>
// Subscriptions tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';

defineProps({
    form: { type: Object, required: true }
});
</script>

<template>
    <div class="space-y-6">
      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Subscriptions and billing</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Configure grace periods, automated dues invoicing, and payment receipt footers.</p>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <Input v-model.number="form.dues_grace_period_days" type="number" min="0" max="180" label="Overdue grace period (days)" hint="Days after due date before member status defaults to overdue." />
          <Input v-model.number="form.auto_invoice_days_before" type="number" min="0" max="90" label="Auto-invoice lead time (days before the cycle)" hint="Days prior to membership renewal to dispatch automated invoices." />
          <Input v-model="form.membership_year_start" type="date" label="Membership year start date" hint="Official annual start date for club membership dues and subscription billing." />
          <Input v-model="form.tax_registration_number" placeholder="GB 987 6543 21" label="VAT or tax registration number" />
          <Textarea v-model="form.receipt_footer_notes" :rows="3" placeholder="Thank you for supporting our club." label="Receipt and invoice footer notes" class="sm:col-span-2" />
        </div>
      </Card>
    </div>
</template>
