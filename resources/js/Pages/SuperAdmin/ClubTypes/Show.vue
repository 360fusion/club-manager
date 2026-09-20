<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  clubType: {
    type: Object,
    required: true,
  },
  clubs: {
    type: Array,
    default: () => [],
  },
  allClubTypes: {
    type: Array,
    default: () => [],
  },
});

function handleSwitchOrder(event) {
  const selectedId = event.target.value;
  if (selectedId) {
    router.get(route('superadmin.club_types.show', selectedId));
  }
}
</script>

<template>
  <SuperAdminLayout :title="`${clubType.name} - Order Specification`">
    <Head :title="`Superadmin - ${clubType.name}`" />

    <div class="space-y-8">
      
      <!-- Top Header & Breadcrumb Banner -->
      <div class="bg-gradient-to-r from-white via-blue-50 to-blue-50 p-8 rounded-3xl border border-blue-200 shadow-2xl text-slate-900 space-y-6 dark:from-slate-900 dark:via-blue-950 dark:to-blue-950 dark:border-blue-800/60 dark:text-white">
        
        <!-- Breadcrumbs & Order Switcher -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-blue-200 pb-4 dark:border-blue-800/60">
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
            <Link :href="route('superadmin.dashboard')" class="hover:text-slate-900 transition dark:hover:text-white">Dashboard</Link>
            <span>/</span>
            <Link :href="route('superadmin.club_types.index')" class="hover:text-slate-900 transition dark:hover:text-white">Masonic Orders</Link>
            <span>/</span>
            <span class="text-blue-700 font-bold dark:text-blue-300">{{ clubType.name }}</span>
          </div>

          <!-- Quick Order Switcher Select -->
          <div class="flex items-center gap-2">
            <label class="text-xs font-bold text-slate-700 whitespace-nowrap dark:text-slate-300">Switch Order:</label>
            <select
              :value="clubType.id"
              @change="handleSwitchOrder"
              class="bg-white border border-blue-300 text-slate-900 text-xs font-bold rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-blue-500 cursor-pointer dark:bg-slate-900 dark:border-blue-700 dark:text-white"
            >
              <option v-for="ot in allClubTypes" :key="ot.id" :value="ot.id">
                {{ ot.name }} ({{ ot.code }})
              </option>
            </select>
          </div>
        </div>

        <!-- Main Banner Title & Actions -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <span class="px-3 py-1 bg-blue-50 text-blue-700 text-[10px] font-black uppercase tracking-wider rounded-full border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/60">
                🏛️ Masonic Companion Order Specification
              </span>
              <span class="px-2.5 py-0.5 bg-slate-100 text-slate-700 text-xs font-mono font-bold rounded-lg border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">
                Code: {{ clubType.code }}
              </span>
              <span class="px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-full border border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-700">
                {{ clubType.clubs_count || clubs.length }} Registered Club(s)
              </span>
            </div>

            <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ clubType.name }}</h1>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <a
              v-if="clubType.website_url"
              :href="clubType.website_url"
              target="_blank"
              rel="noopener noreferrer"
              class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-black text-xs rounded-xl shadow-sm transition flex items-center gap-2"
            >
              <span>🌐 Visit Official Website</span>
              <span>↗</span>
            </a>
            <Link
              :href="route('superadmin.club_types.index')"
              class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-black text-xs rounded-xl border border-slate-300 transition flex items-center gap-2 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700"
            >
              <span>⚙️ Manage Order Presets</span>
            </Link>
          </div>
        </div>
      </div>

      <!-- Main Overview & Specifications Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left Column: About, Official Links, Terminology -->
        <div class="lg:col-span-7 space-y-6">
          
          <!-- About & Description -->
          <div class="bg-white/90 border border-slate-200 rounded-3xl p-6 shadow-xl space-y-3 dark:bg-slate-900/90 dark:border-slate-800">
            <h3 class="text-sm font-black text-blue-600 uppercase tracking-wider flex items-center gap-2 dark:text-blue-400">
              <span>📖</span>
              <span>About {{ clubType.name }}</span>
            </h3>
            <p class="text-xs text-slate-700 leading-relaxed font-medium dark:text-slate-300">
              {{ clubType.description || 'No detailed description configured for this order yet.' }}
            </p>
          </div>

          <!-- Headquarters & Governing Body Link -->
          <div v-if="clubType.website_url" class="bg-white/90 border border-slate-200 rounded-3xl p-6 shadow-xl space-y-3 dark:bg-slate-900/90 dark:border-slate-800">
            <h3 class="text-sm font-black text-blue-600 uppercase tracking-wider flex items-center gap-2 dark:text-blue-400">
              <span>🏛️</span>
              <span>Official Headquarters &amp; Governing Body</span>
            </h3>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-100 p-4 rounded-2xl border border-slate-200 dark:bg-slate-800 dark:border-slate-800">
              <div>
                <span class="text-[10px] text-slate-500 font-bold block uppercase tracking-wider dark:text-slate-400">Official Web Domain</span>
                <span class="text-xs text-blue-700 font-mono font-bold block mt-0.5 dark:text-blue-300">{{ clubType.website_url }}</span>
              </div>
              <a
                :href="clubType.website_url"
                target="_blank"
                rel="noopener noreferrer"
                class="px-3.5 py-2 bg-blue-600/80 hover:bg-blue-700 text-white font-bold text-xs rounded-xl transition flex items-center gap-1.5 self-start sm:self-auto"
              >
                <span>Open External Link</span>
                <span>↗</span>
              </a>
            </div>
          </div>

          <!-- Custom Terminology -->
          <div class="bg-white/90 border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900/90 dark:border-slate-800">
            <h3 class="text-sm font-black text-blue-600 uppercase tracking-wider flex items-center gap-2 dark:text-blue-400">
              <span>🏷️</span>
              <span>Custom Terminology Mappings</span>
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
              <div class="bg-slate-100 p-3.5 rounded-2xl border border-slate-200 space-y-1 dark:bg-slate-800 dark:border-slate-800">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block dark:text-slate-400">Entity</span>
                <span class="font-extrabold text-slate-900 text-sm block dark:text-white">{{ clubType.terminology?.club || 'Lodge' }}</span>
              </div>

              <div class="bg-slate-100 p-3.5 rounded-2xl border border-slate-200 space-y-1 dark:bg-slate-800 dark:border-slate-800">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block dark:text-slate-400">Presiding Officer</span>
                <span class="font-extrabold text-slate-900 text-sm block dark:text-white">{{ clubType.terminology?.master || 'Worshipful Master' }}</span>
              </div>

              <div class="bg-slate-100 p-3.5 rounded-2xl border border-slate-200 space-y-1 dark:bg-slate-800 dark:border-slate-800">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block dark:text-slate-400">Summons Circular</span>
                <span class="font-extrabold text-slate-900 text-sm block dark:text-white">{{ clubType.terminology?.summons || 'Summons' }}</span>
              </div>

              <div class="bg-slate-100 p-3.5 rounded-2xl border border-slate-200 space-y-1 dark:bg-slate-800 dark:border-slate-800">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block dark:text-slate-400">Members</span>
                <span class="font-extrabold text-slate-900 text-sm block dark:text-white">{{ clubType.terminology?.members || 'Brethren' }}</span>
              </div>
            </div>
          </div>

          <!-- Provincial Rulers Schema -->
          <div v-if="clubType.rulers_schema?.length" class="bg-white/90 border border-slate-200 rounded-3xl p-6 shadow-xl space-y-3 dark:bg-slate-900/90 dark:border-slate-800">
            <h3 class="text-sm font-black text-amber-700 uppercase tracking-wider flex items-center gap-2 dark:text-amber-400">
              <span>👑</span>
              <span>Provincial Executive &amp; Rulers Schema</span>
            </h3>
            <p class="text-xs text-slate-500 font-medium dark:text-slate-400">Executive ruler titles assigned to Provincial Officers for clubs of this Order.</p>

            <div class="flex flex-wrap gap-2 pt-1">
              <span
                v-for="(ruler, idx) in clubType.rulers_schema"
                :key="idx"
                class="px-3.5 py-1.5 bg-amber-50 text-amber-700 border border-amber-300 text-xs font-bold rounded-full flex items-center gap-1.5 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-700"
              >
                <span>👑</span>
                <span>{{ ruler }}</span>
              </span>
            </div>
          </div>

        </div>

        <!-- Right Column: Officer Progression Ladder & Offices -->
        <div class="lg:col-span-5 space-y-6">
          <div class="bg-white/90 border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900/90 dark:border-slate-800">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
              <div>
                <h3 class="text-sm font-black text-emerald-700 uppercase tracking-wider flex items-center gap-2 dark:text-emerald-400">
                  <span>🎖️</span>
                  <span>Offices &amp; Officer Progression Ladder</span>
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Pre-configured officer roles for {{ clubType.name }}</p>
              </div>
              <span class="text-xs font-mono font-bold text-slate-700 px-2.5 py-1 bg-slate-100 rounded-lg border border-slate-300 dark:text-slate-300 dark:bg-slate-800 dark:border-slate-700">
                {{ clubType.default_officer_roles?.length || 0 }} Roles
              </span>
            </div>

            <div class="space-y-2 max-h-[520px] overflow-y-auto pr-1">
              <div
                v-for="role in clubType.default_officer_roles || []"
                :key="role.id || role.title"
                class="p-3 bg-slate-100 rounded-2xl border border-slate-200 flex items-center justify-between text-xs hover:border-slate-400 transition dark:bg-slate-800 dark:border-slate-800 dark:hover:border-slate-600"
              >
                <div class="flex items-center gap-3">
                  <span class="w-6 h-6 flex items-center justify-center bg-slate-100 text-blue-700 font-mono font-black text-[11px] rounded-full border border-slate-300 dark:bg-slate-800 dark:text-blue-300 dark:border-slate-700">
                    {{ role.rank_level || '—' }}
                  </span>
                  <div>
                    <span class="font-bold text-slate-900 block dark:text-white">{{ role.title }}</span>
                    <span class="text-[10px] text-blue-600 font-mono dark:text-blue-400">Code: {{ role.short_code }}</span>
                  </div>
                </div>
                <span v-if="role.is_executive" class="px-2.5 py-0.5 bg-amber-50 text-amber-700 text-[9px] font-black uppercase rounded-md border border-amber-300 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-700">
                  Key Officer
                </span>
              </div>

              <div v-if="!clubType.default_officer_roles?.length" class="text-xs text-slate-500 italic p-4 text-center dark:text-slate-400">
                No officer ladder configured for this order.
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Registered Clubs Directory Table for this Order -->
      <div class="bg-white/90 border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900/90 dark:border-slate-800">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
          <div>
            <h3 class="text-base font-black text-slate-900 flex items-center gap-2 dark:text-white">
              <span>🏰</span>
              <span>Registered {{ clubType.name }} Clubs</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">All active lodges/chapters of this specific Order registered on the platform.</p>
          </div>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Total {{ clubs.length }} club(s)</span>
        </div>

        <div v-if="clubs.length" class="border border-slate-200 rounded-2xl overflow-hidden shadow-2xs dark:border-slate-800">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-100 text-slate-500 text-[10px] font-black uppercase tracking-wider border-b border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-800">
                <th class="py-3 px-4">Club Name &amp; No.</th>
                <th class="py-3 px-4">Masonic Province</th>
                <th class="py-3 px-4 text-center">Active Members</th>
                <th class="py-3 px-4">Registration Date</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
              <tr v-for="c in clubs" :key="c.id" class="hover:bg-slate-50 transition-colors dark:hover:bg-slate-800/40">
                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                  {{ c.name }} <span v-if="c.lodge_number !== '—'" class="text-slate-500 font-normal dark:text-slate-400">No. {{ c.lodge_number }}</span>
                </td>
                <td class="py-3.5 px-4">
                  <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/60">
                    🏛️ {{ c.province }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center font-bold text-slate-900 dark:text-white">{{ c.members_count }}</td>
                <td class="py-3.5 px-4 text-slate-500 font-medium dark:text-slate-400">{{ c.created_at }}</td>
                <td class="py-3.5 px-4 text-right">
                  <Link
                    :href="route('admin.club_acc.charity.index', c.slug)"
                    class="px-3 py-1 bg-blue-600 hover:bg-blue-500 text-white font-bold text-[10px] rounded-lg transition"
                  >
                    View Workspace →
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-200 space-y-2 dark:bg-slate-900/60 dark:border-slate-800">
          <span class="text-2xl block">🏛️</span>
          <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300">No {{ clubType.name }} Clubs Registered Yet</h4>
          <p class="text-xs text-slate-500 dark:text-slate-400">Clubs registered under this order preset will automatically appear here.</p>
        </div>
      </div>

    </div>
  </SuperAdminLayout>
</template>
