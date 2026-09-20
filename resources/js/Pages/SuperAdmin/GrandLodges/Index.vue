<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    grandLodges: {
        type: Array,
        required: true,
    },
});

const showCreateModal = ref(false);
const editingGrandLodge = ref(null);

const createForm = useForm({
    name: '',
    code: '',
    short_name: '',
    country: 'Scotland',
    website_url: '',
    description: '',
});

const editForm = useForm({
    name: '',
    short_name: '',
    country: 'Scotland',
    website_url: '',
    description: '',
});

function openCreateModal() {
    createForm.reset();
    showCreateModal.value = true;
}

function submitCreate() {
    createForm.post(route('superadmin.grand_lodges.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
}

function startEdit(gl) {
    editingGrandLodge.value = gl;
    editForm.name = gl.name;
    editForm.short_name = gl.short_name || '';
    editForm.country = gl.country || 'England';
    editForm.website_url = gl.website_url || '';
    editForm.description = gl.description || '';
}

function submitUpdate() {
    if (!editingGrandLodge.value) return;
    editForm.put(route('superadmin.grand_lodges.update', editingGrandLodge.value.id), {
        onSuccess: () => {
            editingGrandLodge.value = null;
        },
    });
}

function deleteGrandLodge(gl) {
    if (confirm(`Are you sure you want to delete '${gl.name}'?`)) {
        editForm.delete(route('superadmin.grand_lodges.destroy', gl.id));
    }
}

function getFlag(country) {
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
    <SuperAdminLayout title="Masonic Grand Lodges & Country Heads">
        <Head title="Superadmin - Grand Lodges & Governing Bodies" />

        <div class="space-y-6">
            <!-- Header section -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Grand Lodges &amp; Country Governing Bodies</h1>
                    <p class="text-sm text-slate-500 mt-1 dark:text-slate-400">Manage national Masonic sovereign Grand Lodges (UGLE, Grand Lodge of Scotland, Ireland, Malta, etc.) and their regional jurisdictions.</p>
                </div>
                <button
                    @click="openCreateModal"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg text-sm shadow-sm transition inline-flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Grand Lodge
                </button>
            </div>

            <!-- KPI Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200 p-5 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block dark:text-blue-400">Governing Grand Lodges</span>
                    <span class="text-3xl font-black text-slate-900 block dark:text-white">{{ grandLodges.length }}</span>
                    <span class="text-xs text-slate-500 block font-medium dark:text-slate-400">Sovereign Masonic Heads</span>
                </div>

                <div class="bg-white border border-slate-200 p-5 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block dark:text-blue-400">Total Managed Provinces</span>
                    <span class="text-3xl font-black text-slate-900 block dark:text-white">
                        {{ grandLodges.reduce((acc, gl) => acc + (gl.provinces_count || 0), 0) }}
                    </span>
                    <span class="text-xs text-slate-500 block font-medium dark:text-slate-400">Provinces across all Grand Lodges</span>
                </div>

                <div class="bg-white border border-slate-200 p-5 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider block dark:text-emerald-400">Official Web Portals</span>
                    <span class="text-3xl font-black text-slate-900 block dark:text-white">
                        {{ grandLodges.filter(gl => gl.website_url).length }}
                    </span>
                    <span class="text-xs text-slate-500 block font-medium dark:text-slate-400">Active Grand Lodge websites</span>
                </div>

                <div class="bg-white border border-slate-200 p-5 rounded-xl shadow-lg space-y-1 dark:bg-slate-900 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wider block dark:text-amber-400">Jurisdictional Countries</span>
                    <span class="text-3xl font-black text-slate-900 block dark:text-white">
                        {{ new Set(grandLodges.map(gl => gl.country)).size }}
                    </span>
                    <span class="text-xs text-slate-500 block font-medium dark:text-slate-400">Global Sovereign Territories</span>
                </div>
            </div>

            <!-- Grand Lodges Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div
                    v-for="gl in grandLodges"
                    :key="gl.id"
                    class="bg-white border border-slate-200 rounded-xl p-6 shadow-xl flex flex-col justify-between space-y-4 hover:border-slate-400 transition dark:bg-slate-900 dark:border-slate-800 dark:hover:border-slate-600"
                >
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl p-2 bg-slate-100 border border-slate-200 rounded-lg shadow-inner dark:bg-slate-800 dark:border-slate-800">
                                    {{ getFlag(gl.country) }}
                                </span>
                                <div>
                                    <h2 class="text-lg font-bold text-slate-900 leading-tight dark:text-white">{{ gl.name }}</h2>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-extrabold bg-blue-50 text-blue-700 border border-blue-200 font-mono dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/60">
                                            {{ gl.short_name || gl.code }}
                                        </span>
                                        <span class="text-xs text-slate-500 font-medium dark:text-slate-400">
                                            Country: <strong class="text-slate-800 dark:text-slate-200">{{ gl.country }}</strong>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-slate-500 leading-relaxed dark:text-slate-400">
                            {{ gl.description || 'Sovereign Masonic governing body for Craft lodges and regional provinces.' }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-slate-200 flex items-center justify-between text-xs dark:border-slate-800">
                        <div class="flex items-center gap-4">
                            <div class="text-slate-700 dark:text-slate-300">
                                <span class="font-bold text-slate-900 dark:text-white">{{ gl.provinces_count }}</span>
                                <span class="text-slate-500 ml-1 dark:text-slate-400">Provinces</span>
                            </div>
                            <div class="text-slate-700 dark:text-slate-300">
                                <span class="font-bold text-slate-900 dark:text-white">{{ gl.clubs_count }}</span>
                                <span class="text-slate-500 ml-1 dark:text-slate-400">Lodges</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a
                                v-if="gl.website_url"
                                :href="gl.website_url"
                                target="_blank"
                                class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-semibold rounded-lg transition inline-flex items-center gap-1.5 dark:bg-blue-950/60 dark:hover:bg-blue-900/40 dark:text-blue-300 dark:border-blue-800/60"
                            >
                                <span>Official Portal</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                            <button
                                @click="startEdit(gl)"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg border border-slate-300 font-semibold transition dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteGrandLodge(gl)"
                                class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg border border-rose-200 font-semibold transition dark:bg-rose-950/50 dark:hover:bg-rose-900/40 dark:text-rose-400 dark:border-rose-800/50"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm dark:bg-black/70">
            <div class="bg-white border border-slate-200 rounded-xl p-6 w-full max-w-lg shadow-2xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Add Masonic Grand Lodge</h3>
                    <button @click="showCreateModal = false" class="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Grand Lodge Name</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            placeholder="e.g. Grand Lodge of Scotland"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Abbreviation / Short Name</label>
                            <input
                                v-model="createForm.short_name"
                                type="text"
                                placeholder="e.g. GLoS"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 font-mono dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">System Code Key</label>
                            <input
                                v-model="createForm.code"
                                type="text"
                                placeholder="e.g. glos"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 font-mono focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                                required
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Sovereign Country / Territory</label>
                        <input
                            v-model="createForm.country"
                            type="text"
                            placeholder="e.g. Scotland"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            required
                        />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Official Website URL</label>
                        <input
                            v-model="createForm.website_url"
                            type="url"
                            placeholder="https://www.grandlodgescotland.com"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Description</label>
                        <textarea
                            v-model="createForm.description"
                            rows="3"
                            placeholder="Summary of this Grand Lodge jurisdiction..."
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                        ></textarea>
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
                            Add Grand Lodge
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="editingGrandLodge" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm dark:bg-black/70">
            <div class="bg-white border border-slate-200 rounded-xl p-6 w-full max-w-lg shadow-2xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Edit {{ editingGrandLodge.name }}</h3>
                    <button @click="editingGrandLodge = null" class="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submitUpdate" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Grand Lodge Name</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Abbreviation / Short Name</label>
                            <input
                                v-model="editForm.short_name"
                                type="text"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 font-mono dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Sovereign Country</label>
                            <input
                                v-model="editForm.country"
                                type="text"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                                required
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Official Website URL</label>
                        <input
                            v-model="editForm.website_url"
                            type="url"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Description</label>
                        <textarea
                            v-model="editForm.description"
                            rows="3"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <button
                            type="button"
                            @click="editingGrandLodge = null"
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
