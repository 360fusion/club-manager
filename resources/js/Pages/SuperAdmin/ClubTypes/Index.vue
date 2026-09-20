<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import OrderColourPicker from '@/Components/OrderColourPicker.vue';
import { orderColour, ORDER_COLOURS } from '@/Utils/orderColour';

const props = defineProps({
    clubTypes: {
        type: Array,
        required: true,
    },
});

const selectedType = ref(props.clubTypes[0] || null);
const isEditing = ref(false);
const showCreateModal = ref(false);

const editForm = useForm({
    name: '',
    colour: 'slate',
    description: '',
    website_url: '',
    available_modules: [],
    terminology: {
        club: 'Lodge',
        master: 'Worshipful Master',
        summons: 'Summons',
        members: 'Brethren',
    },
    rulers_schema: [],
});

const createForm = useForm({
    name: '',
    code: '',
    colour: 'slate',
    description: '',
    website_url: '',
    available_modules: ['accounting', 'meetings', 'members', 'charity'],
    terminology: {
        club: 'Lodge',
        master: 'Worshipful Master',
        summons: 'Summons',
        members: 'Brethren',
    },
    rulers_schema: ['Provincial Grand Master', 'Deputy Provincial Grand Master', 'Assistant Provincial Grand Master'],
});

function selectClubType(ct) {
    selectedType.value = ct;
    isEditing.value = false;
    editForm.name = ct.name;
    editForm.colour = ct.colour || 'slate';
    editForm.description = ct.description || '';
    editForm.website_url = ct.website_url || '';
    editForm.available_modules = [...(ct.available_modules || [])];
    editForm.terminology = {
        club: ct.terminology?.club || 'Lodge',
        master: ct.terminology?.master || 'Worshipful Master',
        summons: ct.terminology?.summons || 'Summons',
        members: ct.terminology?.members || 'Brethren',
    };
    editForm.rulers_schema = [...(ct.rulers_schema || [])];
}

// Initial selection
if (selectedType.value) {
    selectClubType(selectedType.value);
}

function updateClubType() {
    if (!selectedType.value) return;
    editForm.put(route('superadmin.club_types.update', selectedType.value.id), {
        onSuccess: () => {
            isEditing.value = false;
        },
    });
}

function submitCreate() {
    createForm.post(route('superadmin.club_types.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
}

function addRulerField() {
    editForm.rulers_schema.push('New Executive Officer Title');
}

function removeRulerField(index) {
    editForm.rulers_schema.splice(index, 1);
}
</script>

<template>
    <SuperAdminLayout title="Masonic Orders & Club Types Configuration">
        <Head title="Superadmin - Masonic Orders" />

        <div class="space-y-6">
            <!-- Header section -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Masonic Orders & Club Types</h1>
                    <p class="text-sm text-slate-500 mt-1 dark:text-slate-400">Configure preset ladders, dynamic terminology, and Provincial Rulers for each Masonic Order.</p>
                </div>
                <button
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg text-sm shadow-sm transition inline-flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Masonic Order Preset
                </button>
            </div>

            <!-- Main grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left panel: List of Orders -->
                <div class="lg:col-span-4 bg-white border border-slate-200 rounded-xl p-4 shadow-xl dark:bg-slate-900 dark:border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 px-2 dark:text-slate-400">Configured Orders ({{ clubTypes.length }})</h2>
                    <div class="space-y-2">
                        <div
                            v-for="ct in clubTypes"
                            :key="ct.id"
                            @click="selectClubType(ct)"
                            :class="[
                                'p-3.5 rounded-lg border cursor-pointer transition-all duration-150',
                                selectedType && selectedType.id === ct.id
                                    ? 'bg-blue-50 border-blue-400 text-slate-900 shadow-md dark:bg-blue-950/60 dark:border-blue-600 dark:text-white'
                                    : 'bg-slate-50 border-slate-200 text-slate-700 hover:border-slate-400 hover:text-slate-900 dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-white'
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-sm font-semibold"><span :class="['h-2.5 w-2.5 shrink-0 rounded-full', orderColour(ct.colour).dot]" aria-hidden="true" />{{ ct.name }}</span>
                                <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">{{ ct.code }}</span>
                            </div>
                            <div class="flex items-center gap-3 mt-2 text-xs text-slate-500 dark:text-slate-400">
                                <span>{{ ct.default_officer_roles?.length || 0 }} Officer Roles</span>
                                <span>•</span>
                                <span>{{ ct.default_ranks?.length || 0 }} Ranks</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right panel: Selected Order Details & Editor -->
                <div v-if="selectedType" class="lg:col-span-8 bg-white border border-slate-200 rounded-xl p-6 shadow-xl space-y-6 dark:bg-slate-900 dark:border-slate-800">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4 dark:border-slate-800">
                        <div>
                            <div class="flex items-center gap-3">
                                <span :class="['h-4 w-4 shrink-0 rounded-full', orderColour(selectedType.colour).dot]" :title="ORDER_COLOURS.find((c) => c.key === selectedType.colour)?.label" aria-hidden="true" />
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ selectedType.name }}</h2>
                                <span class="text-xs font-mono text-blue-600 bg-blue-50 border border-blue-200 px-2.5 py-0.5 rounded-full dark:text-blue-400 dark:bg-blue-950/60 dark:border-blue-800/60">{{ selectedType.code }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">Configure default terminology, executive positions, and module access.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link
                                :href="route('superadmin.club_types.show', selectedType.id)"
                                class="px-3.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs rounded-lg border border-blue-300 transition flex items-center gap-1 dark:bg-blue-950/60 dark:hover:bg-blue-900/40 dark:text-blue-300 dark:border-blue-700"
                            >
                                <span>View Order Page</span>
                                <span>↗</span>
                            </Link>
                            <button
                                v-if="!isEditing"
                                @click="isEditing = true"
                                class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-medium text-xs rounded-lg border border-slate-300 transition dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700"
                            >
                                Edit Preset
                            </button>
                            <template v-else>
                                <button
                                    @click="isEditing = false"
                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-xs rounded-lg border border-slate-300 transition dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="updateClubType"
                                    :disabled="editForm.processing"
                                    class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-500 text-white font-medium text-xs rounded-lg transition"
                                >
                                    {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Display Mode vs Edit Mode -->
                    <div class="space-y-6">
                        <!-- Description & Official Website Section -->
                        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 space-y-4 dark:bg-slate-900/60 dark:border-slate-800">
                            <h3 class="text-xs font-semibold text-blue-600 uppercase tracking-wider dark:text-blue-400">Order Information & Official Links</h3>
                            <div class="space-y-3 text-sm">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1 dark:text-slate-400">Order Colour</label>
                                    <OrderColourPicker v-if="isEditing" v-model="editForm.colour" />
                                    <div v-else class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                        <span :class="['h-4 w-4 rounded-full', orderColour(selectedType.colour).dot]" aria-hidden="true" />
                                        {{ ORDER_COLOURS.find((c) => c.key === selectedType.colour)?.label ?? 'Black' }}
                                        <span class="text-slate-500 dark:text-slate-400">· used for date tiles, calendars and chips for every club in this order</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1 dark:text-slate-400">Order Description</label>
                                    <textarea
                                        v-if="isEditing"
                                        v-model="editForm.description"
                                        rows="3"
                                        class="w-full bg-white border border-slate-300 text-slate-900 text-xs rounded-md px-3 py-2 focus:ring-1 focus:ring-blue-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white"
                                        placeholder="Enter background context, degree progression, and eligibility requirements..."
                                    ></textarea>
                                    <p v-else class="text-xs text-slate-700 leading-relaxed dark:text-slate-300">{{ selectedType.description || 'No description added yet.' }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1 dark:text-slate-400">Official Website URL</label>
                                    <input
                                        v-if="isEditing"
                                        v-model="editForm.website_url"
                                        type="url"
                                        class="w-full bg-white border border-slate-300 text-slate-900 text-xs rounded-md px-3 py-2 focus:ring-1 focus:ring-blue-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white"
                                        placeholder="e.g. https://markmasonshall.org"
                                    />
                                    <div v-else class="flex items-center gap-2">
                                        <a
                                            v-if="selectedType.website_url"
                                            :href="selectedType.website_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-xs text-blue-600 hover:text-blue-700 font-mono underline flex items-center gap-1 dark:text-blue-400 dark:hover:text-blue-300"
                                        >
                                            <span>{{ selectedType.website_url }}</span>
                                            <span>↗</span>
                                        </a>
                                        <span v-else class="text-xs text-slate-500 italic dark:text-slate-400">No official website link configured.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Terminology Section -->
                        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 space-y-4 dark:bg-slate-900/60 dark:border-slate-800">
                            <h3 class="text-xs font-semibold text-blue-600 uppercase tracking-wider dark:text-blue-400">Dynamic Terminology Mappings</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1 dark:text-slate-400">Entity Term (e.g. Lodge / Chapter / Conclave)</label>
                                    <input
                                        v-if="isEditing"
                                        v-model="editForm.terminology.club"
                                        type="text"
                                        class="w-full bg-white border border-slate-300 text-slate-900 text-xs rounded-md px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white"
                                    />
                                    <span v-else class="font-medium text-slate-800 dark:text-slate-200">{{ selectedType.terminology?.club || 'Lodge' }}</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1 dark:text-slate-400">Presiding Officer Term (e.g. Worshipful Master / M.E.Z.)</label>
                                    <input
                                        v-if="isEditing"
                                        v-model="editForm.terminology.master"
                                        type="text"
                                        class="w-full bg-white border border-slate-300 text-slate-900 text-xs rounded-md px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white"
                                    />
                                    <span v-else class="font-medium text-slate-800 dark:text-slate-200">{{ selectedType.terminology?.master || 'Worshipful Master' }}</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1 dark:text-slate-400">Summons / Circular Document Term</label>
                                    <input
                                        v-if="isEditing"
                                        v-model="editForm.terminology.summons"
                                        type="text"
                                        class="w-full bg-white border border-slate-300 text-slate-900 text-xs rounded-md px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white"
                                    />
                                    <span v-else class="font-medium text-slate-800 dark:text-slate-200">{{ selectedType.terminology?.summons || 'Summons' }}</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1 dark:text-slate-400">Members Collective Term (e.g. Brethren / Companions / Knights)</label>
                                    <input
                                        v-if="isEditing"
                                        v-model="editForm.terminology.members"
                                        type="text"
                                        class="w-full bg-white border border-slate-300 text-slate-900 text-xs rounded-md px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white"
                                    />
                                    <span v-else class="font-medium text-slate-800 dark:text-slate-200">{{ selectedType.terminology?.members || 'Brethren' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Provincial Rulers Schema -->
                        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 space-y-3 dark:bg-slate-900/60 dark:border-slate-800">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-semibold text-blue-600 uppercase tracking-wider dark:text-blue-400">Provincial Executive & Rulers Schema</h3>
                                <button
                                    v-if="isEditing"
                                    @click="addRulerField"
                                    type="button"
                                    class="text-xs font-medium text-blue-600 hover:text-blue-700 flex items-center gap-1 dark:text-blue-400 dark:hover:text-blue-300"
                                >
                                    + Add Field
                                </button>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Specifies executive ruler titles rendered on the Province Settings page for clubs of this type.</p>

                            <div v-if="isEditing" class="space-y-2">
                                <div v-for="(ruler, idx) in editForm.rulers_schema" :key="idx" class="flex items-center gap-2">
                                    <input
                                        v-model="editForm.rulers_schema[idx]"
                                        type="text"
                                        class="flex-1 bg-white border border-slate-300 text-slate-900 text-xs rounded-md px-3 py-1.5 focus:ring-1 focus:ring-blue-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white"
                                    />
                                    <button @click="removeRulerField(idx)" type="button" class="text-red-700 hover:text-red-800 p-1 dark:text-red-400 dark:hover:text-red-300">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div v-else class="flex flex-wrap gap-2 pt-1">
                                <span
                                    v-for="(ruler, idx) in selectedType.rulers_schema || []"
                                    :key="idx"
                                    class="text-xs font-medium px-3 py-1 bg-slate-100 text-slate-700 rounded-full border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700"
                                >
                                    {{ ruler }}
                                </span>
                                <span v-if="!selectedType.rulers_schema?.length" class="text-xs text-slate-500 italic dark:text-slate-400">No specific ruler fields defined.</span>
                            </div>
                        </div>

                        <!-- Default Officer Ladder -->
                        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 space-y-3 dark:bg-slate-900/60 dark:border-slate-800">
                            <h3 class="text-xs font-semibold text-blue-600 uppercase tracking-wider dark:text-blue-400">Default Officer Progression Ladder</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-700 dark:text-slate-300">
                                <div
                                    v-for="role in selectedType.default_officer_roles || []"
                                    :key="role.id"
                                    class="p-2 bg-white border border-slate-200 rounded flex items-center justify-between dark:bg-slate-900 dark:border-slate-800"
                                >
                                    <span class="font-medium">{{ role.title }} ({{ role.short_code }})</span>
                                    <span v-if="role.is_executive" class="text-[10px] px-1.5 py-0.5 bg-amber-50 text-amber-700 rounded font-mono dark:bg-amber-950/30 dark:text-amber-400">Key Officer</span>
                                </div>
                                <span v-if="!selectedType.default_officer_roles?.length" class="text-xs text-slate-500 italic col-span-2 dark:text-slate-400">No pre-configured officer roles set.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Club Type Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm dark:bg-black/70">
            <div class="bg-white border border-slate-200 rounded-xl p-6 w-full max-w-lg shadow-2xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Create Masonic Order Preset</h3>
                    <button @click="showCreateModal = false" class="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Order Name</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            placeholder="e.g. Knights Templar Preceptory"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            required
                        />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">System Code Key</label>
                        <input
                            v-model="createForm.code"
                            type="text"
                            placeholder="e.g. kt_preceptory"
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 font-mono focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            required
                        />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Order Colour</label>
                        <OrderColourPicker v-model="createForm.colour" />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Presiding Officer Term</label>
                        <input
                            v-model="createForm.terminology.master"
                            type="text"
                            placeholder="e.g. Eminent Preceptor"
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
                            Create Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>
