<script setup>
// Communications tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';

defineProps({
    form: { type: Object, required: true }
});
</script>

<template>
    <div class="space-y-6">
      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Email sender settings</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Set default email signatures, reply-to addresses, and notification dispatch rules.</p>
        </template>

        <div class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <Input v-model="form.email_from_name" label="Sender name" />
            <Input v-model="form.email_reply_to" type="email" placeholder="admin@club.org" label="Reply-to email address" />
            <Input v-model="form.email_footer_address" placeholder="100 Boathouse Way, Oxford, UK" label="Email footer postal address or compliance text" class="sm:col-span-2" />
          </div>

          <label class="flex items-start gap-3 text-sm cursor-pointer">
            <input type="checkbox" v-model="form.notify_dues_overdue" class="mt-0.5 h-4 w-4 rounded accent-blue-600" />
            <span>
              <span class="font-medium text-slate-900 dark:text-white">Automated overdue dues notices</span>
              <span class="block text-slate-500 dark:text-slate-400">Send reminder emails when membership dues invoices pass their grace period.</span>
            </span>
          </label>
        </div>
      </Card>
    </div>
</template>
