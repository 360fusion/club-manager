<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
  clubs: Array,
  regions: Array,
  filters: Object,
  user: Object,
});

const searchInput = ref(props.filters.search || '');
const selectedRegion = ref(props.filters.region || '');

const showSubscribeModal = ref(false);
const targetClub = ref(null);
const targetType = ref(null);

const form = useForm({
  email: props.user?.email || '',
  name: props.user?.name || '',
  rank: props.user?.rank || '',
  home_club_name: '',
  home_club_number: '',
});

const applyFilters = () => {
  router.get(
    route('directory.index'),
    { search: searchInput.value, region: selectedRegion.value },
    { preserveState: true, preserveScroll: true }
  );
};

const openSubscribeModal = (club, type) => {
  targetClub.value = club;
  targetType.value = type;
  showSubscribeModal.value = true;
};

const submitSubscription = () => {
  if (!targetClub.value || !targetType.value) return;

  form.post(
    route('directory.subscribe', { clubSlug: targetClub.value.slug, typeId: targetType.value.id }),
    {
      preserveScroll: true,
      onSuccess: () => {
        showSubscribeModal.value = false;
      },
    }
  );
};
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-800 font-sans">
    <Head title="National Lodge & Club Directory" />

    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Link href="/" class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <span>🏛️ National Lodge Directory</span>
          </Link>
        </div>

        <div class="flex items-center gap-3">
          <Link
            v-if="user"
            :href="route('portal.subscriptions')"
            class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-200 transition-all flex items-center gap-1"
          >
            📬 My Subscriptions
          </Link>
          <Link
            v-if="user"
            href="/portal/dashboard"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition-all"
          >
            Member Portal
          </Link>
          <Link
            v-else
            route="login"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition-all"
          >
            Sign In
          </Link>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      
      <!-- Hero Banner -->
      <div class="bg-gradient-to-br from-indigo-900 via-slate-900 to-indigo-950 text-white rounded-3xl p-8 shadow-xl space-y-4">
        <h1 class="text-3xl font-extrabold tracking-tight">
          Find Lodges & Clubs Across the Country
        </h1>
        <p class="text-slate-300 text-sm max-w-2xl">
          Discover lodges, view public meeting details, and subscribe to official Summonses and Bulletins as a visiting brother.
        </p>

        <!-- Search Bar -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
          <div class="sm:col-span-2">
            <input
              v-model="searchInput"
              @keyup.enter="applyFilters"
              type="text"
              placeholder="Search by Lodge Name, Number (e.g. 357), or Town..."
              class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 text-sm"
            />
          </div>
          <div>
            <button
              @click="applyFilters"
              class="w-full py-3 bg-indigo-500 hover:bg-indigo-600 text-white font-bold rounded-xl text-sm shadow-md transition-all"
            >
              🔍 Search Directory
            </button>
          </div>
        </div>
      </div>

      <!-- Directory List -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-slate-900">
            Registered Lodges & Clubs ({{ clubs.length }})
          </h2>
        </div>

        <div v-if="clubs.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="club in clubs"
            :key="club.id"
            class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 hover:border-indigo-300 transition-all flex flex-col justify-between space-y-4"
          >
            <div class="space-y-3">
              <div class="flex items-start justify-between">
                <div>
                  <span v-if="club.lodge_number" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200">
                    No. {{ club.lodge_number }}
                  </span>
                  <h3 class="text-lg font-bold text-slate-900 mt-1">{{ club.name }}</h3>
                  <p class="text-xs text-slate-500">📍 {{ club.town_city }} • {{ club.province_region }}</p>
                </div>
              </div>

              <!-- Available Subscriptions -->
              <div class="pt-3 border-t border-slate-100 space-y-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Available Subscriptions</span>
                
                <div v-if="club.subscribable_types.length" class="space-y-2">
                  <div
                    v-for="type in club.subscribable_types"
                    :key="type.id"
                    class="bg-slate-50 p-3 rounded-xl border border-slate-200 flex items-center justify-between"
                  >
                    <div>
                      <div class="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                        <span>{{ type.icon }}</span>
                        <span>{{ type.name }}</span>
                      </div>
                      <p class="text-[11px] text-slate-500 line-clamp-1 mt-0.5">{{ type.description }}</p>
                    </div>

                    <button
                      @click="openSubscribeModal(club, type)"
                      class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs shadow-sm transition-all cursor-pointer whitespace-nowrap ml-2"
                    >
                      Subscribe +
                    </button>
                  </div>
                </div>
                <div v-else class="text-xs text-slate-400 italic">
                  No public subscription channels available.
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="bg-white p-12 text-center rounded-2xl border border-slate-200 space-y-2">
          <p class="text-slate-500 text-sm font-medium">No lodges found matching your search criteria.</p>
        </div>
      </div>
    </main>

    <!-- Subscription Modal -->
    <div v-if="showSubscribeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-1.5">
              <span>{{ targetType?.icon }}</span>
              <span>Subscribe to {{ targetType?.name }}</span>
            </h3>
            <p class="text-xs text-slate-500">{{ targetClub?.name }}</p>
          </div>
          <button @click="showSubscribeModal = false" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
        </div>

        <form @submit.prevent="submitSubscription" class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Your Full Name *</label>
            <input v-model="form.name" type="text" placeholder="e.g. W.Bro John Smith" class="w-full p-2.5 border border-slate-200 rounded-xl" required />
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Email Address *</label>
            <input v-model="form.email" type="email" placeholder="john@example.com" class="w-full p-2.5 border border-slate-200 rounded-xl" required />
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Masonic Rank</label>
              <input v-model="form.rank" type="text" placeholder="e.g. W.Bro / Bro" class="w-full p-2.5 border border-slate-200 rounded-xl" />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Home Lodge #</label>
              <input v-model="form.home_club_number" type="text" placeholder="e.g. 357" class="w-full p-2.5 border border-slate-200 rounded-xl" />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Home Lodge / Club Name</label>
            <input v-model="form.home_club_name" type="text" placeholder="e.g. Apollo Lodge" class="w-full p-2.5 border border-slate-200 rounded-xl" />
          </div>

          <div v-if="targetType?.require_approval" class="bg-amber-50 p-3 rounded-xl border border-amber-200 text-amber-800 text-[11px]">
            ℹ️ Subscriptions to this channel require approval by the Secretary.
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" @click="showSubscribeModal = false" class="px-4 py-2 text-slate-600 font-bold">
              Cancel
            </button>
            <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-md">
              Confirm Subscription
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
