<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Alert from '@/Components/Ui/Alert.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
const showSumUpKey = defineModel('showSumUpKey', { type: [String, Number, Boolean, Array, Object], default: undefined });
defineEmits(['close']);

defineProps({
    submitSumUpAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    sumUpForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <Modal :open="true" title="Connect a SumUp card reader" subtitle="Brings in card terminal sales and payouts automatically" size="lg" @close="$emit('close')">
      <form id="sumup-account-form" class="space-y-4" @submit.prevent="submitSumUpAccount">
        <Input v-model="sumUpForm.account_name" label="Account name" required placeholder="For example Lodge SumUp account" />

        <div class="relative">
          <Input v-model="sumUpForm.sumup_api_key" :type="showSumUpKey ? 'text' : 'password'" label="SumUp API key or access token" required placeholder="sup_sk_... or your SumUp API key" />
          <button type="button" class="absolute right-0 top-0 text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300" @click="showSumUpKey = !showSumUpKey">
            {{ showSumUpKey ? 'Hide' : 'Show key' }}
          </button>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <Input v-model="sumUpForm.currency" label="Currency" readonly />
          <Input v-model="sumUpForm.opening_balance" type="number" step="0.01" label="Opening balance" :prefix="$cs" />
        </div>

        <Alert variant="info" title="Where to find your SumUp API key">
          Sign in to <a href="https://me.sumup.com/developers" target="_blank" class="font-medium underline">me.sumup.com/developers</a>, create an API key and paste it here.
        </Alert>
      </form>

      <template #footer>
        <Button variant="secondary" @click="$emit('close')">Cancel</Button>
        <Button type="submit" form="sumup-account-form" :loading="sumUpForm.processing">Save and connect SumUp</Button>
      </template>
    </Modal>
</template>
