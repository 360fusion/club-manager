<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    halls: { type: Array, required: true },
    provinces: { type: Array, default: () => [] },
});

const searchQuery = ref('');
const provinceFilter = ref('all');
const showModal = ref(false);
const editingId = ref(null);

const withoutProvince = computed(() => props.halls.filter((h) => !h.province_id).length);

const emptyForm = () => ({
    province_id: '',
    name: '',
    address_line_1: '',
    address_line_2: '',
    town: '',
    county: '',
    postcode: '',
    country: '',
    telephone: '',
    email: '',
    website_url: '',
});

const form = useForm(emptyForm());

const filteredHalls = computed(() => {
    let result = props.halls;

    if (provinceFilter.value === 'none') {
        result = result.filter((h) => !h.province_id);
    } else if (provinceFilter.value !== 'all') {
        result = result.filter((h) => h.province_id === Number(provinceFilter.value));
    }

    const q = searchQuery.value.toLowerCase().trim();
    if (q) {
        result = result.filter((h) =>
            [h.name, h.town, h.postcode, h.province?.name].some((v) => v && v.toLowerCase().includes(q))
        );
    }

    return result;
});

function addressOf(h) {
    return [h.address_line_1, h.address_line_2, h.town, h.county, h.postcode].filter(Boolean).join(', ');
}

function openCreate() {
    editingId.value = null;
    form.defaults(emptyForm()).reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(hall) {
    editingId.value = hall.id;
    const values = emptyForm();
    Object.keys(values).forEach((key) => {
        values[key] = hall[key] ?? '';
    });
    form.defaults(values).reset();
    form.clearErrors();
    showModal.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
        },
    };

    if (editingId.value) {
        form.put(route('superadmin.masonic_halls.update', editingId.value), options);
    } else {
        form.post(route('superadmin.masonic_halls.store'), options);
    }
}

function deleteHall(hall) {
    const note = hall.clubs_count ? ` ${hall.clubs_count} lodge(s) or chapter(s) will be left without a hall.` : '';
    if (confirm(`Delete '${hall.name}'?${note}`)) {
        useForm({}).delete(route('superadmin.masonic_halls.destroy', hall.id), { preserveScroll: true });
    }
}

const inputClass = 'w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white';
</script>

<template>
    <SuperAdminLayout title="Masonic Halls">
        <Head title="Superadmin - Masonic Halls" />

        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Masonic Halls</h1>
                    <p class="text-sm text-slate-500 mt-1 dark:text-slate-400">The buildings lodges, chapters and other bodies meet in. Lodges pick their hall on their settings page, filtered by province.</p>
                </div>
                <button @click="openCreate" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg text-sm shadow-sm transition">
                    + Add Masonic Hall
                </button>
            </div>

            <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg flex flex-col md:flex-row gap-3 dark:bg-slate-900 dark:border-slate-800">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search by name, town, postcode or province..."
                    class="flex-1 bg-slate-100 border border-slate-300 text-slate-900 placeholder-slate-400 text-xs rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                />
                <select v-model="provinceFilter" class="bg-slate-100 border border-slate-300 text-slate-900 text-xs rounded-xl px-3 py-2.5 dark:bg-slate-800 dark:border-slate-700 dark:text-white">
                    <option value="all">All provinces</option>
                    <option value="none">No province</option>
                    <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <div class="text-xs text-slate-500 self-center dark:text-slate-400">
                    <strong class="text-slate-900 dark:text-white">{{ filteredHalls.length }}</strong> of {{ halls.length }}
                    <span v-if="withoutProvince" class="ml-2 text-amber-600 dark:text-amber-400">{{ withoutProvince }} with no province</span>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-xl dark:bg-slate-900 dark:border-slate-800">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-400">
                                <th class="py-3 px-4">Hall</th>
                                <th class="py-3 px-3">Address</th>
                                <th class="py-3 px-3">Province</th>
                                <th class="py-3 px-3">Bodies</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-xs dark:divide-slate-800">
                            <tr v-for="h in filteredHalls" :key="h.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">{{ h.name }}</td>
                                <td class="py-3.5 px-3 text-slate-700 dark:text-slate-300">{{ addressOf(h) || '—' }}</td>
                                <td class="py-3.5 px-3" :class="h.province ? 'text-slate-700 dark:text-slate-300' : 'text-amber-600 dark:text-amber-400'">{{ h.province?.name || 'No province' }}</td>
                                <td class="py-3.5 px-3 text-slate-700 dark:text-slate-300">{{ h.clubs_count }}</td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <button @click="openEdit(h)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs rounded border border-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700">Edit</button>
                                    <button @click="deleteHall(h)" class="ml-2 px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs rounded border border-rose-200 dark:bg-rose-950/50 dark:hover:bg-rose-900/40 dark:text-rose-400 dark:border-rose-800/50">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="filteredHalls.length === 0">
                                <td colspan="5" class="py-12 px-4 text-center text-slate-500 dark:text-slate-400">No masonic halls match.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm dark:bg-black/70">
            <div class="bg-white border border-slate-200 rounded-xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ editingId ? 'Edit Masonic Hall' : 'Add Masonic Hall' }}</h3>
                    <button @click="showModal = false" class="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submit" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Name</label>
                        <input v-model="form.name" type="text" required placeholder="e.g. Freemasons' Hall" :class="inputClass" />
                        <p v-if="form.errors.name" class="text-rose-600 mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Province</label>
                        <select v-model="form.province_id" :class="inputClass">
                            <option value="">No province</option>
                            <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Address line 1</label>
                        <input v-model="form.address_line_1" type="text" :class="inputClass" />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Address line 2</label>
                        <input v-model="form.address_line_2" type="text" :class="inputClass" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Town / city</label>
                            <input v-model="form.town" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">County</label>
                            <input v-model="form.county" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Postcode</label>
                            <input v-model="form.postcode" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Country</label>
                            <input v-model="form.country" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Telephone</label>
                            <input v-model="form.telephone" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Email</label>
                            <input v-model="form.email" type="email" :class="inputClass" />
                            <p v-if="form.errors.email" class="text-rose-600 mt-1">{{ form.errors.email }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1 dark:text-slate-300">Website</label>
                        <input v-model="form.website_url" type="url" placeholder="https://..." :class="inputClass" />
                        <p v-if="form.errors.website_url" class="text-rose-600 mt-1">{{ form.errors.website_url }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-md hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-500">{{ editingId ? 'Save' : 'Add Hall' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>
