<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  province: {
    type: Object,
    required: true,
  },
  allProvinces: {
    type: Array,
    default: () => [],
  },
  grandLodges: {
    type: Array,
    default: () => [],
  },
});

function handleSwitchProvince(event) {
  const selectedId = event.target.value;
  if (selectedId) {
    router.get(route('superadmin.provinces.show', selectedId));
  }
}

function getCountryFlag(country) {
  switch (country) {
    case 'Scotland': return '🏴󠁧󠁢󠁳󠁣󠁴󠁿';
    case 'Wales': return '🏴󠁧󠁢󠁷󠁬󠁳󠁿';
    case 'Ireland': return '🇮🇪';
    case 'Malta': return '🇲🇹';
    case 'Isle of Man': return '🇮🇲';
    case 'Channel Islands': return '🇯🇪';
    default: return '🏴󠁧󠁢󠁥󠁮󠁧󠁿';
  }
}
</script>

<template>
  <SuperAdminLayout :title="`${province.name} - Province Specification`">
    <Head :title="`Superadmin - ${province.name}`" />

    <div class="space-y-8">
      
      <!-- Top Header & Breadcrumb Banner -->
      <div class="bg-gradient-to-r from-slate-900 via-purple-950 to-indigo-950 p-8 rounded-3xl border border-purple-500/20 shadow-2xl text-white space-y-6">
        
        <!-- Breadcrumbs & Province Switcher -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-purple-500/20 pb-4">
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-300">
            <Link :href="route('superadmin.dashboard')" class="hover:text-white transition">Dashboard</Link>
            <span>/</span>
            <Link :href="route('superadmin.provinces.index')" class="hover:text-white transition">Masonic Provinces</Link>
            <span>/</span>
            <span class="text-purple-300 font-bold">{{ province.name }}</span>
          </div>

          <!-- Quick Province Switcher -->
          <div class="flex items-center gap-2">
            <label class="text-xs font-bold text-slate-300 whitespace-nowrap">Switch Province:</label>
            <select
              :value="province.id"
              @change="handleSwitchProvince"
              class="bg-slate-900 border border-purple-500/40 text-white text-xs font-bold rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-purple-500 cursor-pointer max-w-xs truncate"
            >
              <option v-for="p in allProvinces" :key="p.id" :value="p.id">
                {{ getCountryFlag(p.country) }} {{ p.name }}
              </option>
            </select>
          </div>
        </div>

        <!-- Title & Action Buttons -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <span class="px-3 py-1 bg-purple-500/20 text-purple-300 text-[10px] font-black uppercase tracking-wider rounded-full border border-purple-500/30">
                🏛️ Masonic Province Specification
              </span>
              <span class="px-2.5 py-0.5 bg-slate-800 text-slate-300 text-xs font-mono font-bold rounded-lg border border-slate-700">
                Code: {{ province.code }}
              </span>
              <span class="px-2.5 py-0.5 bg-indigo-950 text-indigo-300 text-xs font-bold rounded-lg border border-indigo-800/60 flex items-center gap-1">
                <span>{{ getCountryFlag(province.country || 'England') }}</span>
                <span>{{ province.country || 'England' }}</span>
              </span>
              <span v-if="province.grand_lodge" class="px-2.5 py-0.5 bg-purple-950 text-purple-300 text-xs font-bold rounded-lg border border-purple-800/60">
                👑 {{ province.grand_lodge.short_name || province.grand_lodge.name }}
              </span>
              <span class="px-3 py-1 bg-emerald-500/20 text-emerald-300 text-xs font-bold rounded-full border border-emerald-500/30">
                {{ province.clubs_count || province.clubs?.length || 0 }} Associated Lodges
              </span>
            </div>

            <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-white">{{ province.name }}</h1>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <Link
              :href="route('superadmin.provinces.edit', province.id)"
              class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-black text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition flex items-center gap-2"
            >
              <span>✏️ Edit Province Info</span>
            </Link>
            <a
              v-if="province.website_url"
              :href="province.website_url"
              target="_blank"
              rel="noopener noreferrer"
              class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-600/30 transition flex items-center gap-2"
            >
              <span>🌐 Visit Website</span>
              <span>↗</span>
            </a>
          </div>
        </div>
      </div>

      <!-- Main Specifications & Details Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left Column: Executive Leadership & Contact/Address Info -->
        <div class="lg:col-span-6 space-y-6">
          
          <!-- Executive Leadership -->
          <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h3 class="text-sm font-black text-purple-400 uppercase tracking-wider flex items-center gap-2 border-b border-slate-800 pb-3">
              <span>👑</span>
              <span>Provincial Executive Leadership</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 space-y-1">
                <span class="text-[10px] text-amber-400 font-bold uppercase tracking-wider block">Provincial Grand Master</span>
                <span class="font-extrabold text-white text-base block">
                  {{ province.provincial_grand_master || 'Not specified' }}
                </span>
              </div>

              <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 space-y-1">
                <span class="text-[10px] text-indigo-400 font-bold uppercase tracking-wider block">Provincial Grand Secretary</span>
                <span class="font-extrabold text-white text-base block">
                  {{ province.provincial_grand_secretary || 'Not specified' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Official Office Address & Contact Details -->
          <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h3 class="text-sm font-black text-indigo-400 uppercase tracking-wider flex items-center gap-2 border-b border-slate-800 pb-3">
              <span>📍</span>
              <span>Provincial Headquarters Office &amp; Address</span>
            </h3>

            <!-- Address block -->
            <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 space-y-2">
              <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Official Postal Address</span>
              
              <div v-if="province.address_line_1 || province.town || province.postcode" class="text-xs text-white space-y-0.5 font-medium leading-relaxed">
                <div v-if="province.address_line_1">{{ province.address_line_1 }}</div>
                <div v-if="province.address_line_2">{{ province.address_line_2 }}</div>
                <div v-if="province.town">{{ province.town }}</div>
                <div v-if="province.county">{{ province.county }}</div>
                <div v-if="province.postcode" class="font-mono text-purple-300 font-bold">{{ province.postcode }}</div>
                <div class="text-slate-400">{{ province.country || 'United Kingdom' }}</div>
              </div>
              <div v-else class="text-xs text-slate-500 italic">No office address recorded for this province.</div>
            </div>

            <!-- Contact & Social Links Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
              <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800 space-y-1">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Telephone</span>
                <a v-if="province.telephone" :href="`tel:${province.telephone}`" class="font-bold text-white hover:text-purple-300 block font-mono">
                  📞 {{ province.telephone }}
                </a>
                <span v-else class="text-slate-500 italic block">—</span>
              </div>

              <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800 space-y-1">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Email Address</span>
                <a v-if="province.email" :href="`mailto:${province.email}`" class="font-bold text-indigo-400 hover:text-indigo-300 block truncate">
                  ✉️ {{ province.email }}
                </a>
                <span v-else class="text-slate-500 italic block">—</span>
              </div>

              <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800 space-y-1">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Official Website</span>
                <a v-if="province.website_url" :href="province.website_url" target="_blank" class="font-bold text-purple-400 hover:text-purple-300 block truncate">
                  🌐 {{ province.website_url }} ↗
                </a>
                <span v-else class="text-slate-500 italic block">—</span>
              </div>

              <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800 space-y-1">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Social Channels</span>
                <div class="flex items-center gap-3 pt-0.5">
                  <a v-if="province.twitter_url" :href="province.twitter_url" target="_blank" class="text-sky-400 hover:underline font-bold">
                    Twitter / X ↗
                  </a>
                  <a v-if="province.facebook_url" :href="province.facebook_url" target="_blank" class="text-blue-400 hover:underline font-bold">
                    Facebook ↗
                  </a>
                  <span v-if="!province.twitter_url && !province.facebook_url" class="text-slate-500 italic">—</span>
                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- Right Column: Jurisdiction Overview & Registered Lodges -->
        <div class="lg:col-span-6 space-y-6">
          
          <!-- Jurisdiction & Description -->
          <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-3">
            <h3 class="text-sm font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2 border-b border-slate-800 pb-3">
              <span>📖</span>
              <span>Jurisdiction Overview &amp; Regional Scope</span>
            </h3>

            <div class="grid grid-cols-2 gap-3 text-xs">
              <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Geographic Region</span>
                <span class="font-extrabold text-white text-sm block mt-0.5">{{ province.region || '—' }}</span>
              </div>

              <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Sovereign Grand Lodge</span>
                <span class="font-extrabold text-white text-sm block mt-0.5">{{ province.grand_lodge?.name || 'UGLE' }}</span>
              </div>
            </div>

            <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 space-y-1">
              <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Province Notes &amp; History</span>
              <p class="text-xs text-slate-300 leading-relaxed font-medium">
                {{ province.description || 'No detailed background notes recorded for this province.' }}
              </p>
            </div>
          </div>

          <!-- Associated Registered Lodges -->
          <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
              <div>
                <h3 class="text-sm font-black text-white flex items-center gap-2">
                  <span>🏰</span>
                  <span>Registered Lodges in {{ province.name }}</span>
                </h3>
                <p class="text-xs text-slate-400">Lodges and chapters operating within this province.</p>
              </div>
              <span class="text-xs font-bold text-slate-400">Total {{ province.clubs?.length || 0 }} lodge(s)</span>
            </div>

            <div v-if="province.clubs?.length" class="border border-slate-800 rounded-2xl overflow-hidden shadow-2xs max-h-80 overflow-y-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="bg-slate-950 text-slate-400 text-[10px] font-black uppercase tracking-wider border-b border-slate-800">
                    <th class="py-3 px-4">Lodge Name &amp; No.</th>
                    <th class="py-3 px-3">Order Type</th>
                    <th class="py-3 px-3 text-center">Active Members</th>
                    <th class="py-3 px-4 text-right">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80 text-slate-300">
                  <tr v-for="c in province.clubs" :key="c.id" class="hover:bg-slate-800/40 transition-colors">
                    <td class="py-3 px-4 font-bold text-white">
                      {{ c.name }} <span v-if="c.lodge_number" class="text-slate-400 font-normal">No. {{ c.lodge_number }}</span>
                    </td>
                    <td class="py-3 px-3">
                      <span class="px-2 py-0.5 bg-purple-500/20 text-purple-300 text-[10px] font-black rounded border border-purple-500/30">
                        {{ c.club_type?.name || 'Craft Lodge' }}
                      </span>
                    </td>
                    <td class="py-3 px-3 text-center font-bold text-white">{{ c.users_count || 0 }}</td>
                    <td class="py-3 px-4 text-right">
                      <Link
                        :href="route('admin.club_acc.charity.index', c.slug)"
                        class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] rounded transition"
                      >
                        Workspace →
                      </Link>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="p-6 text-center bg-slate-950/60 rounded-2xl border border-slate-800 space-y-1">
              <span class="text-xl block">🏛️</span>
              <h4 class="text-xs font-bold text-slate-300">No Lodges Registered in this Province Yet</h4>
              <p class="text-[11px] text-slate-500">New lodges assigned to {{ province.name }} will appear here.</p>
            </div>
          </div>

        </div>

      </div>

    </div>
  </SuperAdminLayout>
</template>
