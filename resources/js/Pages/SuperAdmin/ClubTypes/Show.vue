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
      <div class="bg-gradient-to-r from-slate-900 via-purple-950 to-indigo-950 p-8 rounded-3xl border border-purple-500/20 shadow-2xl text-white space-y-6">
        
        <!-- Breadcrumbs & Order Switcher -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-purple-500/20 pb-4">
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-300">
            <Link :href="route('superadmin.dashboard')" class="hover:text-white transition">Dashboard</Link>
            <span>/</span>
            <Link :href="route('superadmin.club_types.index')" class="hover:text-white transition">Masonic Orders</Link>
            <span>/</span>
            <span class="text-purple-300 font-bold">{{ clubType.name }}</span>
          </div>

          <!-- Quick Order Switcher Select -->
          <div class="flex items-center gap-2">
            <label class="text-xs font-bold text-slate-300 whitespace-nowrap">Switch Order:</label>
            <select
              :value="clubType.id"
              @change="handleSwitchOrder"
              class="bg-slate-900 border border-purple-500/40 text-white text-xs font-bold rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-purple-500 cursor-pointer"
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
              <span class="px-3 py-1 bg-purple-500/20 text-purple-300 text-[10px] font-black uppercase tracking-wider rounded-full border border-purple-500/30">
                🏛️ Masonic Companion Order Specification
              </span>
              <span class="px-2.5 py-0.5 bg-slate-800 text-slate-300 text-xs font-mono font-bold rounded-lg border border-slate-700">
                Code: {{ clubType.code }}
              </span>
              <span class="px-3 py-1 bg-emerald-500/20 text-emerald-300 text-xs font-bold rounded-full border border-emerald-500/30">
                {{ clubType.clubs_count || clubs.length }} Registered Club(s)
              </span>
            </div>

            <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-white">{{ clubType.name }}</h1>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <a
              v-if="clubType.website_url"
              :href="clubType.website_url"
              target="_blank"
              rel="noopener noreferrer"
              class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-600/30 transition flex items-center gap-2"
            >
              <span>🌐 Visit Official Website</span>
              <span>↗</span>
            </a>
            <Link
              :href="route('superadmin.club_types.index')"
              class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-black text-xs rounded-xl border border-slate-700 transition flex items-center gap-2"
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
          <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-3">
            <h3 class="text-sm font-black text-purple-400 uppercase tracking-wider flex items-center gap-2">
              <span>📖</span>
              <span>About {{ clubType.name }}</span>
            </h3>
            <p class="text-xs text-slate-300 leading-relaxed font-medium">
              {{ clubType.description || 'No detailed description configured for this order yet.' }}
            </p>
          </div>

          <!-- Headquarters & Governing Body Link -->
          <div v-if="clubType.website_url" class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-3">
            <h3 class="text-sm font-black text-purple-400 uppercase tracking-wider flex items-center gap-2">
              <span>🏛️</span>
              <span>Official Headquarters &amp; Governing Body</span>
            </h3>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-950 p-4 rounded-2xl border border-slate-800">
              <div>
                <span class="text-[10px] text-slate-400 font-bold block uppercase tracking-wider">Official Web Domain</span>
                <span class="text-xs text-purple-300 font-mono font-bold block mt-0.5">{{ clubType.website_url }}</span>
              </div>
              <a
                :href="clubType.website_url"
                target="_blank"
                rel="noopener noreferrer"
                class="px-3.5 py-2 bg-purple-600/80 hover:bg-purple-600 text-white font-bold text-xs rounded-xl transition flex items-center gap-1.5 self-start sm:self-auto"
              >
                <span>Open External Link</span>
                <span>↗</span>
              </a>
            </div>
          </div>

          <!-- Custom Terminology -->
          <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h3 class="text-sm font-black text-indigo-400 uppercase tracking-wider flex items-center gap-2">
              <span>🏷️</span>
              <span>Custom Terminology Mappings</span>
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
              <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800 space-y-1">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Entity</span>
                <span class="font-extrabold text-white text-sm block">{{ clubType.terminology?.club || 'Lodge' }}</span>
              </div>

              <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800 space-y-1">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Presiding Officer</span>
                <span class="font-extrabold text-white text-sm block">{{ clubType.terminology?.master || 'Worshipful Master' }}</span>
              </div>

              <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800 space-y-1">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Summons Circular</span>
                <span class="font-extrabold text-white text-sm block">{{ clubType.terminology?.summons || 'Summons' }}</span>
              </div>

              <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800 space-y-1">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Members</span>
                <span class="font-extrabold text-white text-sm block">{{ clubType.terminology?.members || 'Brethren' }}</span>
              </div>
            </div>
          </div>

          <!-- Provincial Rulers Schema -->
          <div v-if="clubType.rulers_schema?.length" class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-3">
            <h3 class="text-sm font-black text-amber-400 uppercase tracking-wider flex items-center gap-2">
              <span>👑</span>
              <span>Provincial Executive &amp; Rulers Schema</span>
            </h3>
            <p class="text-xs text-slate-400 font-medium">Executive ruler titles assigned to Provincial Officers for clubs of this Order.</p>

            <div class="flex flex-wrap gap-2 pt-1">
              <span
                v-for="(ruler, idx) in clubType.rulers_schema"
                :key="idx"
                class="px-3.5 py-1.5 bg-amber-500/10 text-amber-300 border border-amber-500/30 text-xs font-bold rounded-full flex items-center gap-1.5"
              >
                <span>👑</span>
                <span>{{ ruler }}</span>
              </span>
            </div>
          </div>

        </div>

        <!-- Right Column: Officer Progression Ladder & Offices -->
        <div class="lg:col-span-5 space-y-6">
          <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
              <div>
                <h3 class="text-sm font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                  <span>🎖️</span>
                  <span>Offices &amp; Officer Progression Ladder</span>
                </h3>
                <p class="text-xs text-slate-400">Pre-configured officer roles for {{ clubType.name }}</p>
              </div>
              <span class="text-xs font-mono font-bold text-slate-300 px-2.5 py-1 bg-slate-800 rounded-lg border border-slate-700">
                {{ clubType.default_officer_roles?.length || 0 }} Roles
              </span>
            </div>

            <div class="space-y-2 max-h-[520px] overflow-y-auto pr-1">
              <div
                v-for="role in clubType.default_officer_roles || []"
                :key="role.id || role.title"
                class="p-3 bg-slate-950 rounded-2xl border border-slate-800/80 flex items-center justify-between text-xs hover:border-slate-700 transition"
              >
                <div class="flex items-center gap-3">
                  <span class="w-6 h-6 flex items-center justify-center bg-slate-800 text-purple-300 font-mono font-black text-[11px] rounded-full border border-slate-700">
                    {{ role.rank_level || '—' }}
                  </span>
                  <div>
                    <span class="font-bold text-white block">{{ role.title }}</span>
                    <span class="text-[10px] text-purple-400 font-mono">Code: {{ role.short_code }}</span>
                  </div>
                </div>
                <span v-if="role.is_executive" class="px-2.5 py-0.5 bg-amber-500/20 text-amber-300 text-[9px] font-black uppercase rounded-md border border-amber-500/30">
                  Key Officer
                </span>
              </div>

              <div v-if="!clubType.default_officer_roles?.length" class="text-xs text-slate-500 italic p-4 text-center">
                No officer ladder configured for this order.
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Registered Clubs Directory Table for this Order -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div>
            <h3 class="text-base font-black text-white flex items-center gap-2">
              <span>🏰</span>
              <span>Registered {{ clubType.name }} Clubs</span>
            </h3>
            <p class="text-xs text-slate-400">All active lodges/chapters of this specific Order registered on the platform.</p>
          </div>
          <span class="text-xs font-bold text-slate-400">Total {{ clubs.length }} club(s)</span>
        </div>

        <div v-if="clubs.length" class="border border-slate-800 rounded-2xl overflow-hidden shadow-2xs">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-950 text-slate-400 text-[10px] font-black uppercase tracking-wider border-b border-slate-800">
                <th class="py-3 px-4">Club Name &amp; No.</th>
                <th class="py-3 px-4">Masonic Province</th>
                <th class="py-3 px-4 text-center">Active Members</th>
                <th class="py-3 px-4">Registration Date</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/80 text-slate-300">
              <tr v-for="c in clubs" :key="c.id" class="hover:bg-slate-800/40 transition-colors">
                <td class="py-3.5 px-4 font-bold text-white">
                  {{ c.name }} <span v-if="c.lodge_number !== '—'" class="text-slate-400 font-normal">No. {{ c.lodge_number }}</span>
                </td>
                <td class="py-3.5 px-4">
                  <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-indigo-950 text-indigo-300 border border-indigo-800/50">
                    🏛️ {{ c.province }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center font-bold text-white">{{ c.members_count }}</td>
                <td class="py-3.5 px-4 text-slate-400 font-medium">{{ c.created_at }}</td>
                <td class="py-3.5 px-4 text-right">
                  <Link
                    :href="route('admin.club_acc.charity.index', c.slug)"
                    class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] rounded-lg transition"
                  >
                    View Workspace →
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="p-8 text-center bg-slate-950/60 rounded-2xl border border-slate-800 space-y-2">
          <span class="text-2xl block">🏛️</span>
          <h4 class="text-sm font-bold text-slate-300">No {{ clubType.name }} Clubs Registered Yet</h4>
          <p class="text-xs text-slate-500">Clubs registered under this order preset will automatically appear here.</p>
        </div>
      </div>

    </div>
  </SuperAdminLayout>
</template>
