<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  types: Array,
});

const showModal = ref(false);
const editingType = ref(null);

const form = useForm({
  id: null,
  name: '',
  description: '',
  color: '#4f46e5',
  icon: '✉️',
  is_external_subscribable: true,
  require_approval: false,
  is_mandatory: false,
  require_home_club_info: true,
  default_roles: ['member'],
  sender_name: '',
  sender_email: '',
});

const openModal = (type = null) => {
  if (type) {
    editingType.value = type;
    form.id = type.id;
    form.name = type.name;
    form.description = type.description || '';
    form.color = type.color || '#4f46e5';
    form.icon = type.icon || '✉️';
    form.is_external_subscribable = type.is_external_subscribable ?? true;
    form.require_approval = type.require_approval ?? false;
    form.is_mandatory = type.is_mandatory ?? false;
    form.require_home_club_info = type.require_home_club_info ?? true;
    form.default_roles = type.default_roles || ['member'];
    form.sender_name = type.sender_name || '';
    form.sender_email = type.sender_email || '';
  } else {
    editingType.value = null;
    form.reset();
    form.id = null;
    form.color = '#4f46e5';
    form.icon = '✉️';
    form.is_external_subscribable = true;
    form.default_roles = ['member'];
  }
  showModal.value = true;
};

const saveType = () => {
  form.post(route('admin.newsletters.types.store', { clubSlug: props.club.slug }), {
    preserveScroll: true,
    onSuccess: () => {
      showModal.value = false;
    },
  });
};

const deleteType = (typeId) => {
  if (confirm('Are you sure you want to delete this newsletter channel? Existing broadcasts will remain.')) {
    router.delete(route('admin.newsletters.types.destroy', { clubSlug: props.club.slug, id: typeId }));
  }
};
</script>

<template>
  <AdminLayout title="Newsletter Channels & Settings" :club="club" active-tab="newsletters">
    <Head title="Newsletter Channels & Settings" />

    <div class="space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <Link
              :href="route('admin.newsletters.index', club.slug)"
              class="inline-flex items-center text-xs font-bold text-slate-500 hover:text-indigo-600 transition-colors"
            >
              ← Back to Broadcasts
            </Link>
          </div>
          <h2 class="text-xl font-bold text-slate-900">
            ⚙️ Newsletter Channels & Settings
          </h2>
          <p class="text-xs text-slate-500 mt-1">
            Manage communication types (Summonses, News, Social Bulletins) and configure external visiting subscriptions & approval rules.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <Link
            :href="route('admin.newsletters.subscribers', club.slug)"
            class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-200 transition-all flex items-center gap-1"
          >
            👥 Manage Subscribers
          </Link>
          <button
            @click="openModal(null)"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition-all cursor-pointer"
          >
            + Create New Channel
          </button>
        </div>
      </div>

      <!-- Channel Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div
          v-for="type in types"
          :key="type.id"
          class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4 hover:border-indigo-300 transition-all flex flex-col justify-between"
        >
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-2xl">{{ type.icon }}</span>
              <div class="flex items-center gap-1">
                <span
                  v-if="type.is_mandatory"
                  class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-purple-50 text-purple-700 border border-purple-200"
                >
                  🔒 Mandatory Notice
                </span>
                <span
                  v-if="type.is_external_subscribable"
                  class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200"
                >
                  🌐 External Subs Allowed
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200"
                >
                  🔒 Members Only
                </span>
              </div>
            </div>

            <div>
              <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <span :style="{ backgroundColor: type.color }" class="w-3 h-3 rounded-full inline-block"></span>
                {{ type.name }}
              </h3>
              <p class="text-xs text-slate-500 mt-1">{{ type.description || 'No description provided.' }}</p>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-xs">
              <div class="bg-slate-50 p-2 rounded-xl">
                <div class="text-[10px] font-bold text-slate-400 uppercase">Total Subs</div>
                <div class="text-base font-bold text-indigo-600">{{ type.total_subscribers || 0 }}</div>
              </div>
              <div class="bg-slate-50 p-2 rounded-xl">
                <div class="text-[10px] font-bold text-slate-400 uppercase">Visiting Brethren</div>
                <div class="text-base font-bold text-emerald-600">{{ type.external_subscribers || 0 }}</div>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between pt-4 border-t border-slate-100">
            <button
              @click="openModal(type)"
              class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all"
            >
              ⚙️ Configure Channel
            </button>
            <button
              @click="deleteType(type.id)"
              class="px-2.5 py-1.5 text-rose-600 hover:bg-rose-50 rounded-xl text-xs font-bold transition-all"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Edit / Create Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-xl border border-slate-200 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-lg font-bold text-slate-900">
            {{ editingType ? 'Edit Newsletter Channel' : 'Create Newsletter Channel' }}
          </h3>
          <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
        </div>

        <form @submit.prevent="saveType" class="space-y-4 text-xs">
          <div class="grid grid-cols-4 gap-3">
            <div class="col-span-1">
              <label class="block font-bold text-slate-700 mb-1">Icon</label>
              <input v-model="form.icon" type="text" class="w-full p-2 border border-slate-200 rounded-xl text-center text-lg" />
            </div>
            <div class="col-span-3">
              <label class="block font-bold text-slate-700 mb-1">Channel Name *</label>
              <input v-model="form.name" type="text" placeholder="e.g., Summonses, Social Circulars" class="w-full p-2 border border-slate-200 rounded-xl font-medium" required />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="2" placeholder="Describe the purpose of this channel..." class="w-full p-2 border border-slate-200 rounded-xl"></textarea>
          </div>

          <!-- Color picker -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Badge Color</label>
            <div class="flex items-center gap-2">
              <input v-model="form.color" type="color" class="w-10 h-8 rounded border border-slate-200 cursor-pointer" />
              <input v-model="form.color" type="text" class="w-28 p-1.5 border border-slate-200 rounded-xl font-mono text-xs" />
            </div>
          </div>

          <!-- Settings Toggles -->
          <div class="space-y-3 pt-3 border-t border-slate-100">
            <div class="font-bold text-slate-900">Visibility & External Subscriptions</div>
            
            <label class="flex items-start gap-2 cursor-pointer bg-slate-50 p-3 rounded-xl border border-slate-200">
              <input v-model="form.is_external_subscribable" type="checkbox" class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500" />
              <div>
                <span class="font-bold text-slate-800">Allow External Subscriptions (Visiting Brethren / Other Lodges)</span>
                <p class="text-slate-500 text-[11px]">When enabled, this channel will appear in the National Directory allowing members of other clubs/lodges to subscribe.</p>
              </div>
            </label>

            <label v-if="form.is_external_subscribable" class="flex items-start gap-2 cursor-pointer bg-slate-50 p-3 rounded-xl border border-slate-200">
              <input v-model="form.require_approval" type="checkbox" class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500" />
              <div>
                <span class="font-bold text-slate-800">Require Secretary Approval for External Subscribers</span>
                <p class="text-slate-500 text-[11px]">External visiting brethren requests will enter a pending approval queue before receiving broadcasts.</p>
              </div>
            </label>

            <label class="flex items-start gap-2 cursor-pointer bg-slate-50 p-3 rounded-xl border border-slate-200">
              <input v-model="form.is_mandatory" type="checkbox" class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500" />
              <div>
                <span class="font-bold text-slate-800">Mandatory Official Notice (No Member Unsubscribe)</span>
                <p class="text-slate-500 text-[11px]">Enforces bylaws compliance — active internal members cannot unsubscribe from official notices like Summonses.</p>
              </div>
            </label>
          </div>

          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
            <button type="button" @click="showModal = false" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl font-bold">
              Cancel
            </button>
            <button type="submit" :disabled="form.processing" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-md">
              Save Channel
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
