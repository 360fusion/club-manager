<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
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
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-3xl overflow-hidden flex flex-col max-h-[85vh] animate-in fade-in zoom-in-95 duration-150">
      
      <!-- Modal Header -->
      <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
        <div>
          <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
            <span>🧩</span> Select Content Items to Insert
          </h3>
          <p class="text-xs text-slate-500 mt-0.5">Pick approved updates, upcoming meetings, events, or news posts to include in your newsletter.</p>
        </div>
        <button
          type="button"
          @click="$emit('close')"
          class="w-8 h-8 rounded-full bg-slate-200/80 hover:bg-slate-300 text-slate-600 flex items-center justify-center text-sm font-bold transition-all cursor-pointer"
        >
          ✕
        </button>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex items-center gap-1 p-2 bg-slate-100 border-b border-slate-200">
        <button
          type="button"
          @click="activeTab = 'updates'"
          :class="[
            'px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer',
            activeTab === 'updates' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'
          ]"
        >
          <span>📜</span> Approved Updates ({{ updates.length }})
        </button>
        <button
          type="button"
          @click="activeTab = 'meetings'"
          :class="[
            'px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer',
            activeTab === 'meetings' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'
          ]"
        >
          <span>📅</span> Meetings ({{ meetings.length }})
        </button>
        <button
          type="button"
          @click="activeTab = 'events'"
          :class="[
            'px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer',
            activeTab === 'events' ? 'bg-white text-amber-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'
          ]"
        >
          <span>🎟️</span> Events ({{ events.length }})
        </button>
        <button
          type="button"
          @click="activeTab = 'news'"
          :class="[
            'px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer',
            activeTab === 'news' ? 'bg-white text-sky-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'
          ]"
        >
          <span>📰</span> News ({{ news.length }})
        </button>
      </div>

      <!-- Modal Body List -->
      <div class="p-4 overflow-y-auto flex-1 space-y-2">
        
        <!-- Tab 1: Approved Updates -->
        <div v-if="activeTab === 'updates'" class="space-y-2">
          <div v-if="!updates.length" class="text-center py-8 text-slate-400 text-xs font-medium">
            No approved update items ready in queue.
          </div>
          <label
            v-for="item in updates"
            :key="item.id"
            :class="[
              'flex items-start gap-3 p-3 rounded-xl border transition-all cursor-pointer select-none',
              selectedUpdateIds.includes(item.id) ? 'bg-indigo-50/60 border-indigo-300' : 'bg-white border-slate-200 hover:bg-slate-50'
            ]"
          >
            <input
              type="checkbox"
              :checked="selectedUpdateIds.includes(item.id)"
              @change="toggleSelection(selectedUpdateIds, item.id)"
              class="mt-1 rounded text-indigo-600 focus:ring-indigo-500 cursor-pointer"
            />
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-900 truncate">{{ item.title }}</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 uppercase tracking-wider">{{ item.category }}</span>
              </div>
              <p v-if="item.summary" class="text-xs text-slate-500 line-clamp-2 mt-0.5">{{ item.summary }}</p>
            </div>
          </label>
        </div>

        <!-- Tab 2: Upcoming Meetings -->
        <div v-if="activeTab === 'meetings'" class="space-y-2">
          <div v-if="!meetings.length" class="text-center py-8 text-slate-400 text-xs font-medium">
            No upcoming meetings scheduled in the next 30 days.
          </div>
          <label
            v-for="m in meetings"
            :key="m.id"
            :class="[
              'flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer select-none',
              selectedMeetingIds.includes(m.id) ? 'bg-emerald-50/60 border-emerald-300' : 'bg-white border-slate-200 hover:bg-slate-50'
            ]"
          >
            <input
              type="checkbox"
              :checked="selectedMeetingIds.includes(m.id)"
              @change="toggleSelection(selectedMeetingIds, m.id)"
              class="rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer"
            />
            <div class="flex-1 min-w-0">
              <div class="font-bold text-xs text-slate-900">{{ m.title }}</div>
              <div class="text-[11px] text-slate-500 mt-0.5">🗓️ {{ m.date }} • 📍 {{ m.room }}</div>
            </div>
          </label>
        </div>

        <!-- Tab 3: Upcoming Events -->
        <div v-if="activeTab === 'events'" class="space-y-2">
          <div v-if="!events.length" class="text-center py-8 text-slate-400 text-xs font-medium">
            No upcoming events or dining scheduled.
          </div>
          <label
            v-for="evt in events"
            :key="evt.id"
            :class="[
              'flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer select-none',
              selectedEventIds.includes(evt.id) ? 'bg-amber-50/60 border-amber-300' : 'bg-white border-slate-200 hover:bg-slate-50'
            ]"
          >
            <input
              type="checkbox"
              :checked="selectedEventIds.includes(evt.id)"
              @change="toggleSelection(selectedEventIds, evt.id)"
              class="rounded text-amber-600 focus:ring-amber-500 cursor-pointer"
            />
            <div class="flex-1 min-w-0">
              <div class="font-bold text-xs text-slate-900">{{ evt.title }}</div>
              <div class="text-[11px] text-slate-500 mt-0.5">🗓️ {{ evt.date }} • {{ evt.price }}</div>
            </div>
          </label>
        </div>

        <!-- Tab 4: News Posts -->
        <div v-if="activeTab === 'news'" class="space-y-2">
          <div v-if="!news.length" class="text-center py-8 text-slate-400 text-xs font-medium">
            No published news articles available.
          </div>
          <label
            v-for="n in news"
            :key="n.id"
            :class="[
              'flex items-start gap-3 p-3 rounded-xl border transition-all cursor-pointer select-none',
              selectedNewsIds.includes(n.id) ? 'bg-sky-50/60 border-sky-300' : 'bg-white border-slate-200 hover:bg-slate-50'
            ]"
          >
            <input
              type="checkbox"
              :checked="selectedNewsIds.includes(n.id)"
              @change="toggleSelection(selectedNewsIds, n.id)"
              class="mt-1 rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
            />
            <div class="flex-1 min-w-0">
              <div class="font-bold text-xs text-slate-900">{{ n.title }}</div>
              <p v-if="n.excerpt" class="text-xs text-slate-500 line-clamp-1 mt-0.5">{{ n.excerpt }}</p>
            </div>
          </label>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <span class="text-xs font-semibold text-slate-600">Selected Items: <strong class="text-slate-900">{{ totalSelected }}</strong></span>
          <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 cursor-pointer select-none border-l border-slate-300 pl-3">
            <input type="checkbox" v-model="attachFilesToNewsletter" class="rounded text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
            <span>📎 Also attach document files (PDFs) to Newsletter</span>
          </label>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition-all cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="confirmInsertion"
            :disabled="!totalSelected"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl shadow-sm transition-all cursor-pointer"
          >
            Insert Selected Items →
          </button>
        </div>
      </div>

    </div>
  </div>
</template>
