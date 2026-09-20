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
        case 'district': return { label: 'District', cls: 'bg-indigo-950 text-indigo-300 border-indigo-800/60' };
        case 'group': return { label: 'Group', cls: 'bg-emerald-950 text-emerald-300 border-emerald-800/60' };
        case 'dormant': return { label: 'Dormant', cls: 'bg-rose-950 text-rose-300 border-rose-800/60' };
        default: return { label: type, cls: 'bg-slate-950 text-slate-300 border-slate-700' };
    }
}
</script>

<template>
    <SuperAdminLayout title="Districts &amp; Groups">
        <Head title="Superadmin - Districts &amp; Groups" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-800 pb-5">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight">Districts &amp; Groups</h1>
                    <p class="text-sm text-slate-400 mt-1">UGLE overseas District Grand Lodges, Groups of Lodges, and Dormant Districts worldwide.</p>
                </div>
                <button
                    @click="openCreateModal"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg text-sm shadow-sm transition inline-flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add District / Group
                </button>
            </div>

            <!-- KPI Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-lg space-y-1">
                    <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider block">Total Overseas Bodies</span>
                    <span class="text-2xl font-black text-white block">{{ districts.length }}</span>
                    <span class="text-[11px] text-slate-400 block font-medium">UGLE international jurisdictions</span>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-lg space-y-1">
                    <span class="text-[10px] font-bold text-sky-400 uppercase tracking-wider block">🌐 Active Districts</span>
                    <span class="text-2xl font-black text-white block">{{ activeDistricts.length }}</span>
                    <span class="text-[11px] text-slate-400 block font-medium">District Grand Lodges overseas</span>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-lg space-y-1">
                    <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider block">🏛️ Groups of Lodges</span>
                    <span class="text-2xl font-black text-white block">{{ groups.length }}</span>
                    <span class="text-[11px] text-slate-400 block font-medium">Informal groupings (no DGL)</span>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-lg space-y-1">
                    <span class="text-[10px] font-bold text-rose-400 uppercase tracking-wider block">⏸️ Dormant</span>
                    <span class="text-2xl font-black text-white block">{{ dormant.length }}</span>
                    <span class="text-[11px] text-slate-400 block font-medium">Districts where FM is banned / inactive</span>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-lg space-y-1">
                    <span class="text-[10px] font-bold text-purple-400 uppercase tracking-wider block">Total Registered Lodges</span>
                    <span class="text-2xl font-black text-white block">
                        {{ districts.reduce((sum, d) => sum + (d.clubs_count || 0), 0) }}
                    </span>
                    <span class="text-[11px] text-slate-400 block font-medium">Across all districts</span>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-lg space-y-4">
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                    <div class="relative flex-1 max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">🔍</div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by name, country, region, or code..."
                            class="w-full bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-xs rounded-xl pl-9 pr-8 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white text-xs"
                        >✕</button>
                    </div>
                    <div class="text-xs text-slate-400 font-medium">
                        Showing <strong class="text-white">{{ filteredDistricts.length }}</strong> of {{ districts.length }} entries
                    </div>
                </div>

                <!-- Type Filter Tabs -->
                <div class="flex flex-wrap items-center gap-2 border-t border-slate-800/80 pt-3">
                    <span class="text-xs font-semibold text-slate-400 uppercase mr-2">Filter Type:</span>
                    <button
                        @click="selectedTypeFilter = 'all'"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition', selectedTypeFilter === 'all' ? 'bg-indigo-600 text-white shadow' : 'bg-slate-950 border border-slate-800 text-slate-400 hover:text-white']"
                    >
                        All ({{ districts.length }})
                    </button>
                    <button
                        @click="selectedTypeFilter = 'district'"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition', selectedTypeFilter === 'district' ? 'bg-indigo-600 text-white shadow' : 'bg-slate-950 border border-slate-800 text-slate-400 hover:text-white']"
                    >
                        🌐 Districts ({{ activeDistricts.length }})
                    </button>
                    <button
                        @click="selectedTypeFilter = 'group'"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition', selectedTypeFilter === 'group' ? 'bg-emerald-600 text-white shadow' : 'bg-slate-950 border border-slate-800 text-slate-400 hover:text-white']"
                    >
                        🏛️ Groups ({{ groups.length }})
                    </button>
                    <button
                        @click="selectedTypeFilter = 'dormant'"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition', selectedTypeFilter === 'dormant' ? 'bg-rose-600 text-white shadow' : 'bg-slate-950 border border-slate-800 text-slate-400 hover:text-white']"
                    >
                        ⏸️ Dormant ({{ dormant.length }})
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-xl">
                <div class="p-4 border-b border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        Showing {{ filteredDistricts.length }} of {{ districts.length }} Overseas Bodies
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-950/60 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4">Name</th>
                                <th class="py-3 px-3">Type</th>
                                <th class="py-3 px-3">Country</th>
                                <th class="py-3 px-3">Region</th>
                                <th class="py-3 px-3">Code</th>
                                <th class="py-3 px-3">Website</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-xs">
                            <tr v-for="d in filteredDistricts" :key="d.id" class="hover:bg-slate-800/40 transition">
                                <td class="py-3.5 px-4 font-bold text-white">
                                    <Link :href="route('superadmin.districts.show', d.id)" class="hover:text-purple-300 transition">
                                        {{ d.name }}
                                    </Link>
                                    <p v-if="d.description && d.type === 'dormant'" class="text-[10px] text-rose-400 font-normal mt-0.5 truncate max-w-xs">{{ d.description }}</p>
                                </td>
                                <td class="py-3.5 px-3">
                                    <span :class="['px-2.5 py-0.5 rounded text-[11px] font-bold border', typeBadge(d.type).cls]">
                                        {{ typeBadge(d.type).label }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-3 font-semibold text-slate-200">{{ d.country || '—' }}</td>
                                <td class="py-3.5 px-3 text-slate-300">{{ d.region || '—' }}</td>
                                <td class="py-3.5 px-3 font-mono text-indigo-400">{{ d.code }}</td>
                                <td class="py-3.5 px-3">
                                    <a v-if="d.website_url" :href="d.website_url" target="_blank" class="text-indigo-400 hover:underline flex items-center gap-1">
                                        <span>Website</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                    <span v-else class="text-slate-500 italic">—</span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            :href="route('superadmin.districts.show', d.id)"
                                            class="px-2.5 py-1 bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 font-bold text-xs rounded border border-indigo-500/30 transition whitespace-nowrap"
                                        >
                                            View →
                                        </Link>
                                        <Link
                                            :href="route('superadmin.districts.edit', d.id)"
                                            class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded border border-slate-700 transition"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            @click="deleteDistrict(d)"
                                            class="px-2.5 py-1 bg-rose-950/50 hover:bg-rose-900 text-rose-300 text-xs rounded border border-rose-800/50 transition"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredDistricts.length === 0">
                                <td colspan="7" class="py-12 px-4 text-center text-slate-400 space-y-2">
                                    <span class="text-2xl block">🔍</span>
                                    <p class="font-bold text-sm text-slate-300">No entries found matching your filters</p>
                                    <p class="text-xs text-slate-500">Try adjusting your search term or selecting 'All'.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 w-full max-w-lg shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-lg font-bold text-white">Add District / Group</h3>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Official Name</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            placeholder="e.g. District Grand Lodge of Singapore"
                            class="w-full bg-slate-950 border border-slate-700 text-white rounded-md px-3 py-2 focus:ring-indigo-500"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Type</label>
                            <select v-model="createForm.type" class="w-full bg-slate-950 border border-slate-700 text-white rounded-md px-3 py-2" required>
                                <option value="district">District</option>
                                <option value="group">Group</option>
                                <option value="dormant">Dormant</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Governing Grand Lodge</label>
                            <select v-model="createForm.grand_lodge_id" class="w-full bg-slate-950 border border-slate-700 text-white rounded-md px-3 py-2">
                                <option v-for="gl in grandLodges" :key="gl.id" :value="gl.id">{{ gl.short_name || gl.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Country</label>
                            <input v-model="createForm.country" type="text" placeholder="e.g. Singapore" class="w-full bg-slate-950 border border-slate-700 text-white rounded-md px-3 py-2" />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Region</label>
                            <input v-model="createForm.region" type="text" placeholder="e.g. South East Asia" class="w-full bg-slate-950 border border-slate-700 text-white rounded-md px-3 py-2" />
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-300 mb-1">System Code</label>
                        <input v-model="createForm.code" type="text" placeholder="e.g. singapore" class="w-full bg-slate-950 border border-slate-700 text-white rounded-md px-3 py-2 font-mono" required />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Official Website URL</label>
                        <input v-model="createForm.website_url" type="url" placeholder="https://..." class="w-full bg-slate-950 border border-slate-700 text-white rounded-md px-3 py-2" />
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                        <button type="button" @click="showCreateModal = false" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-md hover:bg-slate-700">Cancel</button>
                        <button type="submit" :disabled="createForm.processing" class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-500">Add Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>
