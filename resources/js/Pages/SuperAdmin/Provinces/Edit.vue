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
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1 dark:text-slate-400">
            <Link :href="route('superadmin.dashboard')" class="hover:text-slate-900 transition dark:hover:text-white">Dashboard</Link>
            <span>/</span>
            <Link :href="route('superadmin.provinces.index')" class="hover:text-slate-900 transition dark:hover:text-white">Provinces</Link>
            <span>/</span>
            <Link :href="route('superadmin.provinces.show', province.id)" class="hover:text-slate-900 transition dark:hover:text-white">{{ province.name }}</Link>
            <span>/</span>
            <span class="text-blue-600 font-bold dark:text-blue-400">Edit</span>
          </div>
          <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Edit {{ province.name }}</h1>
          <p class="text-xs text-slate-500 mt-0.5 dark:text-slate-400">Update official office address, executive leaders, contact details, and web portals.</p>
        </div>

        <Link
          :href="route('superadmin.provinces.show', province.id)"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-xl border border-slate-300 transition dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700"
        >
          ← Cancel &amp; Back to Province
        </Link>
      </div>

      <!-- Edit Form -->
      <form @submit.prevent="submitUpdate" class="space-y-8">
        
        <!-- Section 1: Basic Classification -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
          <h3 class="text-xs font-extrabold text-blue-600 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2 dark:text-blue-400 dark:border-slate-800">
            <span>🏛️</span>
            <span>Basic Identification &amp; Grand Lodge</span>
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Province Official Name</label>
              <input
                v-model="form.name"
                type="text"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-bold dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                required
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Governing Sovereign Grand Lodge</label>
              <select
                v-model="form.grand_lodge_id"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 cursor-pointer font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              >
                <option v-for="gl in grandLodges" :key="gl.id" :value="gl.id">
                  {{ gl.name }} ({{ gl.short_name || gl.country }})
                </option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Country Jurisdiction</label>
              <select
                v-model="form.country"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 cursor-pointer font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                required
              >
                <option v-for="c in countriesList" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Geographic Region</label>
              <input
                v-model="form.region"
                type="text"
                placeholder="e.g. South West, East Midlands, Lothians"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>
          </div>
        </div>

        <!-- Section 2: Executive Leadership -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
          <h3 class="text-xs font-extrabold text-amber-700 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2 dark:text-amber-400 dark:border-slate-800">
            <span>👑</span>
            <span>Provincial Executive Rulers</span>
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Provincial Grand Master</label>
              <input
                v-model="form.provincial_grand_master"
                type="text"
                placeholder="e.g. David G. Maskell"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Provincial Grand Secretary</label>
              <input
                v-model="form.provincial_grand_secretary"
                type="text"
                placeholder="e.g. Trevor Conroy"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>
          </div>
        </div>

        <!-- Section 3: Official Headquarters Address & Contact -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
          <h3 class="text-xs font-extrabold text-blue-600 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2 dark:text-blue-400 dark:border-slate-800">
            <span>📍</span>
            <span>Provincial Office Address &amp; Contact Information</span>
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Address Line 1</label>
              <input
                v-model="form.address_line_1"
                type="text"
                placeholder="e.g. 7 New Bridge Street"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Address Line 2 (Optional)</label>
              <input
                v-model="form.address_line_2"
                type="text"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Town / City</label>
              <input
                v-model="form.town"
                type="text"
                placeholder="e.g. Truro, Oxford, Durham"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">County</label>
              <input
                v-model="form.county"
                type="text"
                placeholder="e.g. Cornwall, Oxfordshire"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Postal Code</label>
              <input
                v-model="form.postcode"
                type="text"
                placeholder="e.g. TR1 2AA, OX2 7PP"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Telephone Number</label>
              <input
                v-model="form.telephone"
                type="text"
                placeholder="e.g. 01872 276191"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Official Email Address</label>
              <input
                v-model="form.email"
                type="email"
                placeholder="e.g. secretary@cornwallfreemasons.org.uk"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Official Website URL</label>
              <input
                v-model="form.website_url"
                type="url"
                placeholder="https://pglcornwall.org.uk"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>
          </div>
        </div>

        <!-- Section 4: Social Media Links -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
          <h3 class="text-xs font-extrabold text-blue-700 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2 dark:text-blue-400 dark:border-slate-800">
            <span>🌐</span>
            <span>Social Media Handles &amp; Portals</span>
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Twitter / X URL</label>
              <input
                v-model="form.twitter_url"
                type="url"
                placeholder="https://x.com/CornwallMasons"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Facebook Page URL</label>
              <input
                v-model="form.facebook_url"
                type="url"
                placeholder="https://facebook.com/CornwallFreemasons"
                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
              />
            </div>
          </div>
        </div>

        <!-- Section 5: Overview & Description -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
          <h3 class="text-xs font-extrabold text-emerald-700 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2 dark:text-emerald-400 dark:border-slate-800">
            <span>📖</span>
            <span>Province Summary &amp; Background Notes</span>
          </h3>

          <div class="text-xs">
            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Province Description &amp; History</label>
            <textarea
              v-model="form.description"
              rows="4"
              placeholder="Enter historical background, number of lodges, charity relief notes, etc."
              class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-blue-500 font-medium leading-relaxed dark:bg-slate-800 dark:border-slate-700 dark:text-white"
            ></textarea>
          </div>
        </div>

        <!-- Bottom Action Bar -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
          <Link
            :href="route('superadmin.provinces.show', province.id)"
            class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-300 transition dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700"
          >
            Cancel
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-extrabold text-xs rounded-xl shadow-lg transition flex items-center gap-2"
          >
            <span>{{ form.processing ? 'Saving...' : 'Save Province Changes' }}</span>
          </button>
        </div>

      </form>
    </div>
  </SuperAdminLayout>
</template>
