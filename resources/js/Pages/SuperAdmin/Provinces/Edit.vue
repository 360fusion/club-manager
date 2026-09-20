<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  province: {
    type: Object,
    required: true,
  },
  grandLodges: {
    type: Array,
    default: () => [],
  },
});

const countriesList = ['England', 'Scotland', 'Wales', 'Ireland', 'Malta', 'Isle of Man', 'Channel Islands'];

const form = useForm({
  name: props.province.name || '',
  code: props.province.code || '',
  grand_lodge_id: props.province.grand_lodge_id || null,
  country: props.province.country || 'England',
  region: props.province.region || '',
  website_url: props.province.website_url || '',
  provincial_grand_master: props.province.provincial_grand_master || '',
  provincial_grand_secretary: props.province.provincial_grand_secretary || '',
  address_line_1: props.province.address_line_1 || '',
  address_line_2: props.province.address_line_2 || '',
  town: props.province.town || '',
  county: props.province.county || '',
  postcode: props.province.postcode || '',
  telephone: props.province.telephone || '',
  email: props.province.email || '',
  twitter_url: props.province.twitter_url || '',
  facebook_url: props.province.facebook_url || '',
  description: props.province.description || '',
});

function submitUpdate() {
  form.put(route('superadmin.provinces.update', props.province.id));
}
</script>

<template>
  <SuperAdminLayout :title="`Edit ${province.name}`">
    <Head :title="`Edit - ${province.name}`" />

    <div class="space-y-8 max-w-5xl mx-auto">
      
      <!-- Top Header & Breadcrumbs -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <Link :href="route('superadmin.dashboard')" class="hover:text-white transition">Dashboard</Link>
            <span>/</span>
            <Link :href="route('superadmin.provinces.index')" class="hover:text-white transition">Provinces</Link>
            <span>/</span>
            <Link :href="route('superadmin.provinces.show', province.id)" class="hover:text-white transition">{{ province.name }}</Link>
            <span>/</span>
            <span class="text-indigo-400 font-bold">Edit</span>
          </div>
          <h1 class="text-2xl font-bold text-white tracking-tight">Edit {{ province.name }}</h1>
          <p class="text-xs text-slate-400 mt-0.5">Update official office address, executive leaders, contact details, and web portals.</p>
        </div>

        <Link
          :href="route('superadmin.provinces.show', province.id)"
          class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700 transition"
        >
          ← Cancel &amp; Back to Province
        </Link>
      </div>

      <!-- Edit Form -->
      <form @submit.prevent="submitUpdate" class="space-y-8">
        
        <!-- Section 1: Basic Classification -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
          <h3 class="text-xs font-extrabold text-purple-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
            <span>🏛️</span>
            <span>Basic Identification &amp; Grand Lodge</span>
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Province Official Name</label>
              <input
                v-model="form.name"
                type="text"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-bold"
                required
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Governing Sovereign Grand Lodge</label>
              <select
                v-model="form.grand_lodge_id"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 cursor-pointer font-medium"
              >
                <option v-for="gl in grandLodges" :key="gl.id" :value="gl.id">
                  {{ gl.name }} ({{ gl.short_name || gl.country }})
                </option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Country Jurisdiction</label>
              <select
                v-model="form.country"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 cursor-pointer font-medium"
                required
              >
                <option v-for="c in countriesList" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Geographic Region</label>
              <input
                v-model="form.region"
                type="text"
                placeholder="e.g. South West, East Midlands, Lothians"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>
          </div>
        </div>

        <!-- Section 2: Executive Leadership -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
          <h3 class="text-xs font-extrabold text-amber-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
            <span>👑</span>
            <span>Provincial Executive Rulers</span>
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Provincial Grand Master</label>
              <input
                v-model="form.provincial_grand_master"
                type="text"
                placeholder="e.g. David G. Maskell"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Provincial Grand Secretary</label>
              <input
                v-model="form.provincial_grand_secretary"
                type="text"
                placeholder="e.g. Trevor Conroy"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>
          </div>
        </div>

        <!-- Section 3: Official Headquarters Address & Contact -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
          <h3 class="text-xs font-extrabold text-indigo-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
            <span>📍</span>
            <span>Provincial Office Address &amp; Contact Information</span>
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Address Line 1</label>
              <input
                v-model="form.address_line_1"
                type="text"
                placeholder="e.g. 7 New Bridge Street"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Address Line 2 (Optional)</label>
              <input
                v-model="form.address_line_2"
                type="text"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Town / City</label>
              <input
                v-model="form.town"
                type="text"
                placeholder="e.g. Truro, Oxford, Durham"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">County</label>
              <input
                v-model="form.county"
                type="text"
                placeholder="e.g. Cornwall, Oxfordshire"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Postal Code</label>
              <input
                v-model="form.postcode"
                type="text"
                placeholder="e.g. TR1 2AA, OX2 7PP"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Telephone Number</label>
              <input
                v-model="form.telephone"
                type="text"
                placeholder="e.g. 01872 276191"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Official Email Address</label>
              <input
                v-model="form.email"
                type="email"
                placeholder="e.g. secretary@cornwallfreemasons.org.uk"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Official Website URL</label>
              <input
                v-model="form.website_url"
                type="url"
                placeholder="https://pglcornwall.org.uk"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>
          </div>
        </div>

        <!-- Section 4: Social Media Links -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
          <h3 class="text-xs font-extrabold text-sky-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
            <span>🌐</span>
            <span>Social Media Handles &amp; Portals</span>
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Twitter / X URL</label>
              <input
                v-model="form.twitter_url"
                type="url"
                placeholder="https://x.com/CornwallMasons"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Facebook Page URL</label>
              <input
                v-model="form.facebook_url"
                type="url"
                placeholder="https://facebook.com/CornwallFreemasons"
                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-indigo-500 font-medium"
              />
            </div>
          </div>
        </div>

        <!-- Section 5: Overview & Description -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
          <h3 class="text-xs font-extrabold text-emerald-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
            <span>📖</span>
            <span>Province Summary &amp; Background Notes</span>
          </h3>

          <div class="text-xs">
            <label class="block font-bold text-slate-300 mb-1">Province Description &amp; History</label>
            <textarea
              v-model="form.description"
              rows="4"
              placeholder="Enter historical background, number of lodges, charity relief notes, etc."
              class="w-full bg-slate-950 border border-slate-700 text-white rounded-2xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 font-medium leading-relaxed"
            ></textarea>
          </div>
        </div>

        <!-- Bottom Action Bar -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
          <Link
            :href="route('superadmin.provinces.show', province.id)"
            class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl border border-slate-700 transition"
          >
            Cancel
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs rounded-xl shadow-lg transition flex items-center gap-2"
          >
            <span>{{ form.processing ? 'Saving...' : 'Save Province Changes' }}</span>
          </button>
        </div>

      </form>
    </div>
  </SuperAdminLayout>
</template>
