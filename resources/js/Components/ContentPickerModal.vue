<script setup>
import { ref, computed } from 'vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Modal from '@/Components/Ui/Modal.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
  show: Boolean,
  updates: { type: Array, default: () => [] },
  meetings: { type: Array, default: () => [] },
  events: { type: Array, default: () => [] },
  news: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'select-content']);

const activeTab = ref('updates'); // 'updates', 'meetings', 'events', 'news'

const selectedUpdateIds = ref([]);
const selectedMeetingIds = ref([]);
const selectedEventIds = ref([]);
const selectedNewsIds = ref([]);

const attachFilesToNewsletter = ref(true);

const toggleSelection = (list, id) => {
  const index = list.indexOf(id);
  if (index > -1) {
    list.splice(index, 1);
  } else {
    list.push(id);
  }
};

const totalSelected = computed(() => {
  return selectedUpdateIds.value.length +
    selectedMeetingIds.value.length +
    selectedEventIds.value.length +
    selectedNewsIds.value.length;
});

const confirmInsertion = () => {
  emit('select-content', {
    updateIds: selectedUpdateIds.value,
    meetingIds: selectedMeetingIds.value,
    eventIds: selectedEventIds.value,
    newsIds: selectedNewsIds.value,
    attachFiles: attachFilesToNewsletter.value,
  });
  emit('close');
};

const tabClass = (isActive) => [
  'inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer',
  isActive
    ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
    : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white',
];

const rowClass = (isSelected) => [
  'flex items-start gap-3 p-3 rounded-xl border transition-colors cursor-pointer select-none',
  isSelected
    ? 'bg-blue-50/60 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700/60'
    : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50',
];

const CHECKBOX = 'mt-0.5 h-4 w-4 rounded border-slate-300 accent-blue-600 cursor-pointer dark:border-slate-700';
const EMPTY = 'text-center py-8 text-slate-400 text-xs';
</script>

<template>
  <Modal
    :open="show"
    title="Choose content to insert"
    subtitle="Pick approved updates, upcoming meetings, events, or news posts to include in your newsletter."
    size="lg"
    @close="$emit('close')"
  >
    <!-- Content type tabs -->
    <div class="mb-4 max-w-full overflow-x-auto">
      <div class="flex w-fit items-center gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
        <button type="button" @click="activeTab = 'updates'" :class="tabClass(activeTab === 'updates')">
          <UiIcon name="document" :size="14" /> Approved updates ({{ updates.length }})
        </button>
        <button type="button" @click="activeTab = 'meetings'" :class="tabClass(activeTab === 'meetings')">
          <UiIcon name="calendar" :size="14" /> Meetings ({{ meetings.length }})
        </button>
        <button type="button" @click="activeTab = 'events'" :class="tabClass(activeTab === 'events')">
          <UiIcon name="tag" :size="14" /> Events ({{ events.length }})
        </button>
        <button type="button" @click="activeTab = 'news'" :class="tabClass(activeTab === 'news')">
          <UiIcon name="document" :size="14" /> News ({{ news.length }})
        </button>
      </div>
    </div>

    <div class="space-y-2">

      <!-- Tab 1: Approved Updates -->
      <div v-if="activeTab === 'updates'" class="space-y-2">
        <div v-if="!updates.length" :class="EMPTY">
          No approved update items ready in queue.
        </div>
        <label v-for="item in updates" :key="item.id" :class="rowClass(selectedUpdateIds.includes(item.id))">
          <input
            type="checkbox"
            :checked="selectedUpdateIds.includes(item.id)"
            @change="toggleSelection(selectedUpdateIds, item.id)"
            :class="CHECKBOX"
          />
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-xs font-semibold text-slate-900 dark:text-white truncate">{{ item.title }}</span>
              <Badge>{{ item.category }}</Badge>
            </div>
            <p v-if="item.summary" class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mt-0.5">{{ item.summary }}</p>
          </div>
        </label>
      </div>

      <!-- Tab 2: Upcoming Meetings -->
      <div v-if="activeTab === 'meetings'" class="space-y-2">
        <div v-if="!meetings.length" :class="EMPTY">
          No upcoming meetings scheduled in the next 30 days.
        </div>
        <label v-for="m in meetings" :key="m.id" :class="rowClass(selectedMeetingIds.includes(m.id))">
          <input
            type="checkbox"
            :checked="selectedMeetingIds.includes(m.id)"
            @change="toggleSelection(selectedMeetingIds, m.id)"
            :class="CHECKBOX"
          />
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-xs text-slate-900 dark:text-white">{{ m.title }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ m.date }} · {{ m.room }}</div>
          </div>
        </label>
      </div>

      <!-- Tab 3: Upcoming Events -->
      <div v-if="activeTab === 'events'" class="space-y-2">
        <div v-if="!events.length" :class="EMPTY">
          No upcoming events or dining scheduled.
        </div>
        <label v-for="evt in events" :key="evt.id" :class="rowClass(selectedEventIds.includes(evt.id))">
          <input
            type="checkbox"
            :checked="selectedEventIds.includes(evt.id)"
            @change="toggleSelection(selectedEventIds, evt.id)"
            :class="CHECKBOX"
          />
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-xs text-slate-900 dark:text-white">{{ evt.title }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ evt.date }} · {{ evt.price }}</div>
          </div>
        </label>
      </div>

      <!-- Tab 4: News Posts -->
      <div v-if="activeTab === 'news'" class="space-y-2">
        <div v-if="!news.length" :class="EMPTY">
          No published news articles available.
        </div>
        <label v-for="n in news" :key="n.id" :class="rowClass(selectedNewsIds.includes(n.id))">
          <input
            type="checkbox"
            :checked="selectedNewsIds.includes(n.id)"
            @change="toggleSelection(selectedNewsIds, n.id)"
            :class="CHECKBOX"
          />
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-xs text-slate-900 dark:text-white">{{ n.title }}</div>
            <p v-if="n.excerpt" class="text-xs text-slate-500 dark:text-slate-400 line-clamp-1 mt-0.5">{{ n.excerpt }}</p>
          </div>
        </label>
      </div>

    </div>

    <template #footer>
      <div class="mr-auto flex flex-wrap items-center gap-3">
        <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Selected: <strong class="font-semibold text-slate-900 dark:text-white">{{ totalSelected }}</strong></span>
        <label class="flex items-center gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 cursor-pointer select-none border-l border-slate-300 dark:border-slate-700 pl-3">
          <input type="checkbox" v-model="attachFilesToNewsletter" class="h-4 w-4 rounded border-slate-300 accent-blue-600 cursor-pointer dark:border-slate-700" />
          <span>Also attach document files (PDFs) to the newsletter</span>
        </label>
      </div>
      <Button variant="secondary" @click="$emit('close')">Cancel</Button>
      <Button @click="confirmInsertion" :disabled="!totalSelected">Insert selected items</Button>
    </template>
  </Modal>
</template>
