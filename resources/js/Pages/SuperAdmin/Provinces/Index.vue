<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    provinces: {
        type: Array,
        required: true,
    },
    grandLodges: {
        type: Array,
        default: () => [],
    },
});

const showCreateModal = ref(false);
const editingProvince = ref(null);
const selectedCountryFilter = ref('All');
const searchQuery = ref('');

const countriesList = ['England', 'Scotland', 'Wales', 'Ireland', 'Malta', 'Isle of Man', 'Channel Islands'];

const filteredProvinces = computed(() => {
    let result = props.provinces;

    if (selectedCountryFilter.value !== 'All') {
        result = result.filter(p => (p.country || 'England') === selectedCountryFilter.value);
    }

    if (searchQuery.value.trim() !== '') {
        const q = searchQuery.value.toLowerCase().trim();
        result = result.filter(p => {
            return (
                (p.name && p.name.toLowerCase().includes(q)) ||
                (p.code && p.code.toLowerCase().includes(q)) ||
                (p.region && p.region.toLowerCase().includes(q)) ||
                (p.country && p.country.toLowerCase().includes(q)) ||
                (p.grand_lodge?.name && p.grand_lodge.name.toLowerCase().includes(q)) ||
                (p.grand_lodge?.short_name && p.grand_lodge.short_name.toLowerCase().includes(q))
            );
        });
    }

    return result;
});

const createForm = useForm({
    name: '',
    code: '',
    grand_lodge_id: props.grandLodges[0]?.id || null,
    country: 'England',
    region: 'South East',
    website_url: '',
});

const editForm = useForm({
    name: '',
    grand_lodge_id: null,
    country: 'England',
    region: '',
    website_url: '',
});

function openCreateModal() {
    createForm.reset();
    if (props.grandLodges.length > 0) {
        createForm.grand_lodge_id = props.grandLodges[0].id;
    }
    showCreateModal.value = true;
}

function submitCreate() {
    createForm.post(route('superadmin.provinces.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
}

function startEdit(prov) {
    editingProvince.value = prov;
    editForm.name = prov.name;
    editForm.grand_lodge_id = prov.grand_lodge_id || null;
    editForm.country = prov.country || 'England';
    editForm.region = prov.region || '';
    editForm.website_url = prov.website_url || '';
}

function submitUpdate() {
    if (!editingProvince.value) return;
    editForm.put(route('superadmin.provinces.update', editingProvince.value.id), {
        onSuccess: () => {
            editingProvince.value = null;
        },
    });
}

function deleteProvince(prov) {
    if (confirm(`Are you sure you want to delete '${prov.name}'?`)) {
        editForm.delete(route('superadmin.provinces.destroy', prov.id));
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
    <SuperAdminLayout title="Masonic Provinces Directory">
        <Head title="Superadmin - Masonic Provinces" />

        <div class="space-y-6">
            <!-- Header section -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Masonic Provinces Directory</h1>
                    <p class="text-sm text-slate-500 mt-1 dark:text-slate-400">Manage official UGLE, Scottish, Irish, and Maltese Masonic Provinces &amp; Districts.</p>
                </div>
                <button
                    @click="openCreateModal"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg text-sm shadow-sm transition inline-flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Masonic Province
                </button>
            </div>

            <!-- KPI Metrics Section -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block dark:text-blue-400">Total Managed Provinces</span>
                    <span class="text-2xl font-black text-slate-900 block dark:text-white">{{ provinces.length }}</span>
                    <span class="text-[11px] text-slate-500 block font-medium dark:text-slate-400">All Grand Lodges (UGLE, Scotland, Ireland, Malta)</span>
                </div>

                <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider block dark:text-emerald-400">👑 UGLE PROVINCES</span>
                    <span class="text-2xl font-black text-slate-900 block dark:text-white">
                        {{ provinces.filter(p => !p.grand_lodge || p.grand_lodge?.code === 'ugle').length }}
                    </span>
                    <span class="text-[11px] text-slate-500 block font-medium dark:text-slate-400">48 Official UGLE Jurisdictions</span>
                </div>

                <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-blue-700 uppercase tracking-wider block dark:text-blue-400">🏴󠁧󠁢󠁳󠁣󠁴󠁿 SCOTLAND (GLoS)</span>
                    <span class="text-2xl font-black text-slate-900 block dark:text-white">
                        {{ provinces.filter(p => p.grand_lodge?.code === 'glos' || p.country === 'Scotland').length }}
                    </span>
                    <span class="text-[11px] text-slate-500 block font-medium dark:text-slate-400">Scottish Provincial Lodges</span>
                </div>

                <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wider block dark:text-amber-400">🇮🇪 IRELAND &amp; 🇲🇹 MALTA</span>
                    <span class="text-2xl font-black text-slate-900 block dark:text-white">
                        {{ provinces.filter(p => p.grand_lodge?.code === 'gli' || p.grand_lodge?.code === 'sglm' || p.country === 'Ireland' || p.country === 'Malta').length }}
                    </span>
                    <span class="text-[11px] text-slate-500 block font-medium dark:text-slate-400">Irish &amp; Maltese Jurisdictions</span>
                </div>

                <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block dark:text-blue-400">Total Registered Lodges</span>
                    <span class="text-2xl font-black text-slate-900 block dark:text-white">
                        {{ provinces.reduce((sum, p) => sum + (p.clubs_count || 0), 0) }}
                    </span>
                    <span class="text-[11px] text-slate-500 block font-medium dark:text-slate-400">Across all provinces</span>
                </div>
            </div>

            <!-- Search & Country Filter Controls Bar -->
            <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-4 dark:bg-slate-900 dark:border-slate-800">
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                    <!-- Live Search Bar Input -->
                    <div class="relative flex-1 max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 text-xs dark:text-slate-400">
                            🔍
                        </div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search provinces by name, code, region, or Grand Lodge..."
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 placeholder-slate-400 text-xs rounded-xl pl-9 pr-8 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition dark:bg-slate-800 dark:border-slate-700 dark:text-white dark:placeholder-slate-500"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-900 text-xs dark:text-slate-400 dark:hover:text-white"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="text-xs text-slate-500 font-medium dark:text-slate-400">
                        Showing <strong class="text-slate-900 dark:text-white">{{ filteredProvinces.length }}</strong> of {{ provinces.length }} Provinces
                    </div>
                </div>

                <!-- Country Filter Tabs -->
                <div class="border-t border-slate-200 pt-3 space-y-2 dark:border-slate-800">
                    <span class="text-xs font-semibold text-slate-500 uppercase dark:text-slate-400">Filter Country:</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            @click="selectedCountryFilter = 'All'"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-bold transition',
                                selectedCountryFilter === 'All' ? 'bg-blue-600 text-white shadow' : 'bg-slate-100 border border-slate-200 text-slate-500 hover:text-slate-900 dark:bg-slate-800 dark:border-slate-800 dark:text-slate-400 dark:hover:text-white'
                            ]"
                        >
                            All Countries ({{ provinces.length }})
                        </button>
                        <button
                            v-for="c in countriesList"
                            :key="c"
                            @click="selectedCountryFilter = c"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-bold transition inline-flex items-center gap-1.5',
                                selectedCountryFilter === c ? 'bg-blue-600 text-white shadow' : 'bg-slate-100 border border-slate-200 text-slate-500 hover:text-slate-900 dark:bg-slate-800 dark:border-slate-800 dark:text-slate-400 dark:hover:text-white'
                            ]"
                        >
                            <span>{{ getCountryFlag(c) }}</span>
                            <span>{{ c }}</span>
                            <span class="text-[10px] opacity-75">({{ provinces.filter(p => (p.country || 'England') === c).length }})</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Provinces Data Table -->
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-xl dark:bg-slate-900 dark:border-slate-800">
                <div class="p-4 border-b border-slate-200 flex items-center justify-between dark:border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider dark:text-slate-400">
                        Showing {{ filteredProvinces.length }} of {{ provinces.length }} Provinces
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-400">
                                <th class="py-3 px-4">Province Name</th>
                                <th class="py-3 px-3">Governing Grand Lodge</th>
                                <th class="py-3 px-3">Country</th>
                                <th class="py-3 px-3">System Code</th>
                                <th class="py-3 px-3">Region</th>
                                <th class="py-3 px-3">Associated Lodges</th>
                                <th class="py-3 px-3">Official Website</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-xs dark:divide-slate-800">
                            <tr v-for="prov in filteredProvinces" :key="prov.id" class="hover:bg-slate-50 transition dark:hover:bg-slate-800/40">
                                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                    <Link :href="route('superadmin.provinces.show', prov.id)" class="hover:text-blue-700 transition dark:hover:text-blue-300">
                                        {{ prov.name }}
                                    </Link>
                                </td>
                                <td class="py-3.5 px-3">
                                    <span v-if="prov.grand_lodge" class="px-2.5 py-0.5 rounded text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/60">
                                        {{ prov.grand_lodge.short_name || prov.grand_lodge.name }}
                                    </span>
                                    <span v-else class="text-slate-500 italic dark:text-slate-400">UGLE</span>
                                </td>
                                <td class="py-3.5 px-3 font-semibold text-slate-800 dark:text-slate-200">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] bg-slate-100 border border-slate-200 dark:bg-slate-800 dark:border-slate-800">
                                        <span>{{ getCountryFlag(prov.country || 'England') }}</span>
                                        <span>{{ prov.country || 'England' }}</span>
                                    </span>
                                </td>
                                <td class="py-3.5 px-3 font-mono text-blue-600 dark:text-blue-400">
                                    {{ prov.code }}
                                </td>
                                <td class="py-3.5 px-3 text-slate-700 dark:text-slate-300">
                                    {{ prov.region || '—' }}
                                </td>
                                <td class="py-3.5 px-3">
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/60">
                                        {{ prov.clubs_count }} lodges
                                    </span>
                                </td>
                                <td class="py-3.5 px-3">
                                    <a v-if="prov.website_url" :href="prov.website_url" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1 dark:text-blue-400">
                                        <span>Website</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                    <span v-else class="text-slate-500 italic dark:text-slate-400">—</span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            :href="route('superadmin.provinces.show', prov.id)"
                                            class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs rounded border border-blue-300 transition whitespace-nowrap dark:bg-blue-950/60 dark:hover:bg-blue-900/40 dark:text-blue-300 dark:border-blue-700"
                                        >
                                            View →
                                        </Link>
                                        <Link
                                            :href="route('superadmin.provinces.edit', prov.id)"
                                            class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs rounded border border-slate-300 transition dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            @click="deleteProvince(prov)"
                                            class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs rounded border border-rose-200 transition dark:bg-rose-950/50 dark:hover:bg-rose-900/40 dark:text-rose-400 dark:border-rose-800/50"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredProvinces.length === 0">
                                <td colspan="8" class="py-12 px-4 text-center text-slate-500 space-y-2 dark:text-slate-400">
                                    <span class="text-2xl block">🔍</span>
                                    <p class="font-bold text-sm text-slate-700 dark:text-slate-300">No provinces found matching your filters</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Try adjusting your search term or selecting 'All Countries'.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm dark:bg-black/70">
            <div class="bg-white border border-slate-200 rounded-xl p-6 w-full max-w-lg shadow-2xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Add Masonic Province</h3>
                    <button @click="showCreateModal = false" class="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Province Name</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            placeholder="e.g. Provincial Grand Lodge of Edinburgh"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Governing Grand Lodge</label>
                            <select
                                v-model="createForm.grand_lodge_id"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            >
                                <option v-for="gl in grandLodges" :key="gl.id" :value="gl.id">
                                    {{ gl.short_name || gl.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Country</label>
                            <select
                                v-model="createForm.country"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                                required
                            >
                                <option v-for="c in countriesList" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">System Code Key</label>
                        <input
                            v-model="createForm.code"
                            type="text"
                            placeholder="e.g. pgl_edinburgh"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 font-mono focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            required
                        />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Geographic Region</label>
                        <input
                            v-model="createForm.region"
                            type="text"
                            placeholder="e.g. Edinburgh & Lothians"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Official Website URL</label>
                        <input
                            v-model="createForm.website_url"
                            type="url"
                            placeholder="https://www.pgledinburgh.org.uk"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 bg-slate-100 text-slate-700 rounded-md hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-500"
                        >
                            Add Province
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="editingProvince" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm dark:bg-black/70">
            <div class="bg-white border border-slate-200 rounded-xl p-6 w-full max-w-lg shadow-2xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Edit {{ editingProvince.name }}</h3>
                    <button @click="editingProvince = null" class="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submitUpdate" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Province Name</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Governing Grand Lodge</label>
                            <select
                                v-model="editForm.grand_lodge_id"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            >
                                <option v-for="gl in grandLodges" :key="gl.id" :value="gl.id">
                                    {{ gl.short_name || gl.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Country</label>
                            <select
                                v-model="editForm.country"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                                required
                            >
                                <option v-for="c in countriesList" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Geographic Region</label>
                        <input
                            v-model="editForm.region"
                            type="text"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Official Website URL</label>
                        <input
                            v-model="editForm.website_url"
                            type="url"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <button
                            type="button"
                            @click="editingProvince = null"
                            class="px-4 py-2 bg-slate-100 text-slate-700 rounded-md hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-500"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>
