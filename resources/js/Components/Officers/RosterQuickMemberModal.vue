<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Alert from '@/Components/Ui/Alert.vue';

// A dialog of the officer roster page, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    grandRanks: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isCreatingMember: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    provincialRanks: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    quickMemberError: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    quickMemberForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitQuickMember: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const membershipStatusOptions = [
    { value: 'historical', label: 'Historical member (default)' },
    { value: 'active', label: 'Active member' },
    { value: 'honorary', label: 'Honorary member' },
    { value: 'resigned', label: 'Resigned' },
    { value: 'excluded_rule_181', label: 'Excluded (Rule 181)' },
    { value: 'deceased', label: 'Deceased' },
];

const masonicRankOptions = [
    { value: 'Bro', label: 'Bro (Brother)' },
    { value: 'WBro', label: 'WBro (Worshipful Brother)' },
    { value: 'VWBro', label: 'VWBro (Very Worshipful Brother)' },
    { value: 'RWBro', label: 'RWBro (Right Worshipful Brother)' },
    { value: 'MWBro', label: 'MWBro (Most Worshipful Brother)' },
];
</script>

<template>
    <Modal :open="true" title="Add a historic member" subtitle="Quickly add a member to assign to roster entries." @close="$emit('close')">
        <Alert v-if="quickMemberError" variant="danger" class="mb-4">
          {{ quickMemberError }}
        </Alert>

        <form @submit.prevent="submitQuickMember" class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <Input v-model="quickMemberForm.first_name" label="First name" placeholder="e.g. Thomas" required />
            <Input v-model="quickMemberForm.last_name" label="Surname" placeholder="e.g. Dunckerley" required />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <Select v-model="quickMemberForm.membership_status" label="Member status" :options="membershipStatusOptions" />
            <Select v-model="quickMemberForm.masonic_rank" label="Rank (optional)" :options="masonicRankOptions" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <Select v-model="quickMemberForm.grand_rank" label="Grand rank (optional)" :options="[{ value: '', label: 'None' }, ...(grandRanks || [])]" />
            <Select v-model="quickMemberForm.provincial_rank" label="Provincial rank (optional)" :options="[{ value: '', label: 'None' }, ...(provincialRanks || [])]" />
          </div>

          <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
            <Button variant="secondary" @click="$emit('close')">Cancel</Button>
            <Button type="submit" :loading="isCreatingMember">
              <span v-if="isCreatingMember">Saving...</span>
              <span v-else>Save and select member</span>
            </Button>
          </div>
        </form>
    </Modal>
</template>
