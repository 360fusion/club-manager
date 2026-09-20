<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    districts: { type: Array, required: true },
    grandLodges: { type: Array, default: () => [] },
});

const showCreateModal = ref(false);
const searchQuery = ref('');
const selectedTypeFilter = ref('all');

const filteredDistricts = computed(() => {
    let result = props.districts;

    if (selectedTypeFilter.value !== 'all') {
        result = result.filter(d => d.type === selectedTypeFilter.value);
    }

    if (searchQuery.value.trim() !== '') {
        const q = searchQuery.value.toLowerCase().trim();
        result = result.filter(d =>
            (d.name && d.name.toLowerCase().includes(q)) ||
            (d.code && d.code.toLowerCase().includes(q)) ||
            (d.country && d.country.toLowerCase().includes(q)) ||
            (d.region && d.region.toLowerCase().includes(q))
        );
    }

    return result;
});

const activeDistricts = computed(() => props.districts.filter(d => d.type === 'district'));
const groups = computed(() => props.districts.filter(d => d.type === 'group'));
const dormant = computed(() => props.districts.filter(d => d.type === 'dormant'));

const createForm = useForm({
    name: '',
    code: '',
    type: 'district',
    grand_lodge_id: props.grandLodges[0]?.id || null,
    country: '',
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
    createForm.post(route('superadmin.districts.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
}

function deleteDistrict(district) {
    if (confirm(`Are you sure you want to delete '${district.name}'?`)) {
        useForm({}).delete(route('superadmin.districts.destroy', district.id));
    }
}

function typeBadge(type) {
    switch (type) {
        case 'district': return { label: 'District', cls: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/60' };
        case 'group': return { label: 'Group', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800/50' };
        case 'dormant': return { label: 'Dormant', cls: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-400 dark:border-rose-800/50' };
        default: return { label: type, cls: 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' };
    }
}
</script>

<template>
    <SuperAdminLayout title="Districts &amp; Groups">
        <Head title="Superadmin - Districts &amp; Groups" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Districts &amp; Groups</h1>
                    <p class="text-sm text-slate-500 mt-1 dark:text-slate-400">UGLE overseas District Grand Lodges, Groups of Lodges, and Dormant Districts worldwide.</p>
                </div>
                <button
                    @click="openCreateModal"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg text-sm shadow-sm transition inline-flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add District / Group
                </button>
            </div>

            <!-- KPI Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block dark:text-blue-400">Total Overseas Bodies</span>
                    <span class="text-2xl font-black text-slate-900 block dark:text-white">{{ districts.length }}</span>
                    <span class="text-[11px] text-slate-500 block font-medium dark:text-slate-400">UGLE international jurisdictions</span>
                </div>
                <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-blue-700 uppercase tracking-wider block dark:text-blue-400">🌐 Active Districts</span>
                    <span class="text-2xl font-black text-slate-900 block dark:text-white">{{ activeDistricts.length }}</span>
                    <span class="text-[11px] text-slate-500 block font-medium dark:text-slate-400">District Grand Lodges overseas</span>
                </div>
                <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider block dark:text-emerald-400">🏛️ Groups of Lodges</span>
                    <span class="text-2xl font-black text-slate-900 block dark:text-white">{{ groups.length }}</span>
                    <span class="text-[11px] text-slate-500 block font-medium dark:text-slate-400">Informal groupings (no DGL)</span>
                </div>
                <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-rose-700 uppercase tracking-wider block dark:text-rose-400">⏸️ Dormant</span>
                    <span class="text-2xl font-black text-slate-900 block dark:text-white">{{ dormant.length }}</span>
                    <span class="text-[11px] text-slate-500 block font-medium dark:text-slate-400">Districts where FM is banned / inactive</span>
                </div>
                <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block dark:text-blue-400">Total Registered Lodges</span>
                    <span class="text-2xl font-black text-slate-900 block dark:text-white">
                        {{ districts.reduce((sum, d) => sum + (d.clubs_count || 0), 0) }}
                    </span>
                    <span class="text-[11px] text-slate-500 block font-medium dark:text-slate-400">Across all districts</span>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg space-y-4 dark:bg-slate-900 dark:border-slate-800">
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                    <div class="relative flex-1 max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 text-xs dark:text-slate-400">🔍</div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by name, country, region, or code..."
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 placeholder-slate-400 text-xs rounded-xl pl-9 pr-8 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition dark:bg-slate-800 dark:border-slate-700 dark:text-white dark:placeholder-slate-500"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-900 text-xs dark:text-slate-400 dark:hover:text-white"
                        >✕</button>
                    </div>
                    <div class="text-xs text-slate-500 font-medium dark:text-slate-400">
                        Showing <strong class="text-slate-900 dark:text-white">{{ filteredDistricts.length }}</strong> of {{ districts.length }} entries
                    </div>
                </div>

                <!-- Type Filter Tabs -->
                <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                    <span class="text-xs font-semibold text-slate-500 uppercase mr-2 dark:text-slate-400">Filter Type:</span>
                    <button
                        @click="selectedTypeFilter = 'all'"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition', selectedTypeFilter === 'all' ? 'bg-blue-600 text-white shadow' : 'bg-slate-100 border border-slate-200 text-slate-500 hover:text-white dark:bg-slate-800 dark:border-slate-800 dark:text-slate-400']"
                    >
                        All ({{ districts.length }})
                    </button>
                    <button
                        @click="selectedTypeFilter = 'district'"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition', selectedTypeFilter === 'district' ? 'bg-blue-600 text-white shadow' : 'bg-slate-100 border border-slate-200 text-slate-500 hover:text-white dark:bg-slate-800 dark:border-slate-800 dark:text-slate-400']"
                    >
                        🌐 Districts ({{ activeDistricts.length }})
                    </button>
                    <button
                        @click="selectedTypeFilter = 'group'"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition', selectedTypeFilter === 'group' ? 'bg-emerald-600 text-white shadow' : 'bg-slate-100 border border-slate-200 text-slate-500 hover:text-white dark:bg-slate-800 dark:border-slate-800 dark:text-slate-400']"
                    >
                        🏛️ Groups ({{ groups.length }})
                    </button>
                    <button
                        @click="selectedTypeFilter = 'dormant'"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition', selectedTypeFilter === 'dormant' ? 'bg-rose-600 text-white shadow' : 'bg-slate-100 border border-slate-200 text-slate-500 hover:text-white dark:bg-slate-800 dark:border-slate-800 dark:text-slate-400']"
                    >
                        ⏸️ Dormant ({{ dormant.length }})
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-xl dark:bg-slate-900 dark:border-slate-800">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider dark:text-slate-400">
                        Showing {{ filteredDistricts.length }} of {{ districts.length }} Overseas Bodies
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-400">
                                <th class="py-3 px-4">Name</th>
                                <th class="py-3 px-3">Type</th>
                                <th class="py-3 px-3">Country</th>
                                <th class="py-3 px-3">Region</th>
                                <th class="py-3 px-3">Code</th>
                                <th class="py-3 px-3">Website</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-xs dark:divide-slate-800">
                            <tr v-for="d in filteredDistricts" :key="d.id" class="hover:bg-slate-50 transition dark:hover:bg-slate-800/40">
                                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                    <Link :href="route('superadmin.districts.show', d.id)" class="hover:text-blue-700 transition dark:hover:text-blue-300">
                                        {{ d.name }}
                                    </Link>
                                    <p v-if="d.description && d.type === 'dormant'" class="text-[10px] text-rose-700 font-normal mt-0.5 truncate max-w-xs dark:text-rose-400">{{ d.description }}</p>
                                </td>
                                <td class="py-3.5 px-3">
                                    <span :class="['px-2.5 py-0.5 rounded text-[11px] font-bold border', typeBadge(d.type).cls]">
                                        {{ typeBadge(d.type).label }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-3 font-semibold text-slate-800 dark:text-slate-200">{{ d.country || '—' }}</td>
                                <td class="py-3.5 px-3 text-slate-700 dark:text-slate-300">{{ d.region || '—' }}</td>
                                <td class="py-3.5 px-3 font-mono text-blue-600 dark:text-blue-400">{{ d.code }}</td>
                                <td class="py-3.5 px-3">
                                    <a v-if="d.website_url" :href="d.website_url" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1 dark:text-blue-400">
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
                                            :href="route('superadmin.districts.show', d.id)"
                                            class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs rounded border border-blue-300 transition whitespace-nowrap dark:bg-blue-950/60 dark:hover:bg-blue-900/40 dark:text-blue-300 dark:border-blue-700"
                                        >
                                            View →
                                        </Link>
                                        <Link
                                            :href="route('superadmin.districts.edit', d.id)"
                                            class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs rounded border border-slate-300 transition dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            @click="deleteDistrict(d)"
                                            class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs rounded border border-rose-200 transition dark:bg-rose-950/50 dark:hover:bg-rose-900/40 dark:text-rose-400 dark:border-rose-800/50"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredDistricts.length === 0">
                                <td colspan="7" class="py-12 px-4 text-center text-slate-500 space-y-2 dark:text-slate-400">
                                    <span class="text-2xl block">🔍</span>
                                    <p class="font-bold text-sm text-slate-700 dark:text-slate-300">No entries found matching your filters</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Try adjusting your search term or selecting 'All'.</p>
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
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Add District / Group</h3>
                    <button @click="showCreateModal = false" class="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Official Name</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            placeholder="e.g. District Grand Lodge of Singapore"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Type</label>
                            <select v-model="createForm.type" class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white" required>
                                <option value="district">District</option>
                                <option value="group">Group</option>
                                <option value="dormant">Dormant</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Governing Grand Lodge</label>
                            <select v-model="createForm.grand_lodge_id" class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white">
                                <option v-for="gl in grandLodges" :key="gl.id" :value="gl.id">{{ gl.short_name || gl.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Country</label>
                            <input v-model="createForm.country" type="text" placeholder="e.g. Singapore" class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white" />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Region</label>
                            <input v-model="createForm.region" type="text" placeholder="e.g. South East Asia" class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white" />
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">System Code</label>
                        <input v-model="createForm.code" type="text" placeholder="e.g. singapore" class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 font-mono dark:bg-slate-800 dark:border-slate-700 dark:text-white" required />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Official Website URL</label>
                        <input v-model="createForm.website_url" type="url" placeholder="https://..." class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white" />
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="showCreateModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-md hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit" :disabled="createForm.processing" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-500">Add Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>
