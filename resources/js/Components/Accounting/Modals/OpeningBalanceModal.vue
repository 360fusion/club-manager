<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    accounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openingBalanceForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitOpeningBalance: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <Modal
      :open="true"
      title="Set an opening or carry-over balance"
      subtitle="Record the funds carried over when you first set up your club's accounts."
      @close="$emit('close')"
    >
      <form id="opening-balance-form" @submit.prevent="submitOpeningBalance" class="space-y-4">
        <div class="space-y-1.5">
          <label for="opening-balance-account" class="block text-xs font-medium text-slate-700 dark:text-slate-300">
            Ledger account <span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span>
          </label>
          <!-- Native select: the option values are numeric account ids. -->
          <select
            id="opening-balance-account"
            v-model="openingBalanceForm.account_id"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            required
          >
            <option value="" disabled>Select an account…</option>
            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
              {{ acc.code }} - {{ acc.name }} ({{ acc.type }})
            </option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <Input v-model="openingBalanceForm.opening_balance" type="number" step="0.01" :label="`Opening amount (${$cs})`" placeholder="e.g. 17903.76" required />
          <Input v-model="openingBalanceForm.as_of_date" type="date" label="Effective date" required />
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" @click="$emit('close')">Cancel</Button>
        <Button type="submit" form="opening-balance-form" :disabled="openingBalanceForm.processing">Post opening balance</Button>
      </template>
    </Modal>
</template>
