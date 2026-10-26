<script setup>
import { computed } from 'vue';
import Tabs from '@/Components/Ui/Tabs.vue';

const props = defineProps({
  club: Object,
  activeTab: {
    type: String,
    required: true, // 'lodge', 'committee', 'visitors', 'officers'
  },
});

// The committee page is Livewire, so it needs a full page load. The same list is in livewire/committee/meeting-index.
const items = computed(() => [
  { key: 'lodge', label: 'Lodge meetings & summonses', href: route('admin.meetings.index', { clubSlug: props.club.slug }) },
  { key: 'committee', label: 'Committee & board governance', href: route('admin.committee.index', { clubSlug: props.club.slug }), reload: true },
  { key: 'visitors', label: 'Visitors', href: route('admin.visitors.index', { clubSlug: props.club.slug }) },
  { key: 'officers', label: 'Annual officers roster & board', href: route('admin.officers.index', { clubSlug: props.club.slug }) },
]);
</script>

<template>
  <Tabs :items="items" :active="activeTab" label="Meetings" class="mb-6" />
</template>
