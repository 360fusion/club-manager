<script setup>
import { computed } from 'vue';
import Tabs from '@/Components/Ui/Tabs.vue';

const props = defineProps({
  clubSlug: {
    type: String,
    required: true,
  },
  activeTab: {
    type: String,
    default: 'dashboard',
  },
});

const items = computed(() => [
  { key: 'dashboard', label: 'Charity dashboard', href: route('admin.club_acc.charity.index', props.clubSlug) },
  { key: 'gift-aid', label: 'Gift Aid page', href: route('admin.charity.giftaid.transactions_page', props.clubSlug) },
  { key: 'festival', label: 'Festival information', href: route('admin.club_acc.charity.festival', props.clubSlug) },
]);
</script>

<template>
  <Tabs :items="items" :active="activeTab" label="Charity" class="mb-4" />
</template>
