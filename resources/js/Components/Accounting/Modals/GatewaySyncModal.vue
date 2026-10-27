<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Alert from '@/Components/Ui/Alert.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    gatewaySyncForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    gatewaySyncType: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitGatewaySyncModal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <Modal
      :open="true"
      :title="`Sync ${gatewaySyncType === 'stripe' ? 'Stripe' : (gatewaySyncType === 'sumup' ? 'SumUp' : 'PayPal')} transactions`"
      subtitle="Choose the dates to bring in settled transactions for"
      @close="$emit('close')"
    >
      <form id="gateway-sync-form" class="space-y-4" @submit.prevent="submitGatewaySyncModal">
        <Alert variant="info" title="Duplicates are skipped">
          Transactions already in your ledger are left out, so overlapping date ranges are safe.
        </Alert>

        <div class="grid grid-cols-2 gap-3">
          <Input v-model="gatewaySyncForm.start_date" type="date" label="From" required />
          <Input v-model="gatewaySyncForm.end_date" type="date" label="To" required />
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" @click="$emit('close')">Cancel</Button>
        <Button type="submit" form="gateway-sync-form" :loading="gatewaySyncForm.processing">
          <template #icon><UiIcon name="refresh" /></template>
          Pull {{ gatewaySyncType === 'stripe' ? 'Stripe' : (gatewaySyncType === 'sumup' ? 'SumUp' : 'PayPal') }} transactions
        </Button>
      </template>
    </Modal>
</template>
