<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { describePattern } from '@/Utils/lodgeDates';

const props = defineProps({
    lodges: { type: Object, required: true },
    filters: { type: Object, required: true },
    provinces: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    allOrders: { type: Array, default: () => [] },
    halls: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    months: { type: Object, default: () => ({}) },
    linkableClubs: { type: Array, default: () => [] },
    occurrences: { type: Array, default: () => [] },
    days: { type: Array, default: () => [] },
    totals: { type: Object, required: true },
});

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const search = reactive({ ...props.filters });
let timer = null;

function applyFilters() {
    const query = Object.fromEntries(Object.entries(search).filter(([, value]) => value));
    router.get(route('superadmin.lodges.index'), query, { preserveState: true, preserveScroll: true, replace: true });
}

watch(() => search.q, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const showModal = ref(false);
const editingId = ref(null);
const editingLodge = ref(null);
const linkClubId = ref('');

const emptyForm = () => ({
    club_type_id: '',
    province_id: '',
    masonic_hall_id: '',
    name: '',
    number: '',
    status: 'active',
    meets_text: '',
    installation_month: '',
    website_url: '',
    description: '',
    schedule: { occurrence: '', day_of_week: '', months: [], start_time: '' },
    reparse: false,
});

const form = useForm(emptyForm());

const hallsForProvince = computed(() => props.halls.filter(
    (h) => !form.province_id || !h.province_id || h.province_id === Number(form.province_id)
));

function openCreate() {
    editingId.value = null;
    editingLodge.value = null;
    form.defaults(emptyForm()).reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(lodge) {
    editingId.value = lodge.id;
    editingLodge.value = lodge;
    linkClubId.value = '';
    const values = emptyForm();
    for (const key of ['club_type_id', 'province_id', 'masonic_hall_id', 'name', 'number', 'status', 'meets_text', 'installation_month', 'website_url', 'description']) {
        values[key] = lodge[key] ?? '';
    }
    if (lodge.schedules.length > 1) {
        // Several patterns cannot be edited in this form. Leaving the field out keeps them as they are.
        values.schedule = null;
    } else if (lodge.schedule) {
        values.schedule = {
            occurrence: lodge.schedule.occurrence,
            day_of_week: lodge.schedule.day_of_week,
            months: [...lodge.schedule.months],
            start_time: lodge.schedule.start_time ?? '',
        };
    }
    form.defaults(values).reset();
    form.clearErrors();
    showModal.value = true;
}

function toggleMonth(month) {
    const months = form.schedule.months;
    form.schedule.months = months.includes(month) ? months.filter((m) => m !== month) : [...months, month];
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => { showModal.value = false; } };

    if (editingId.value) {
        form.put(route('superadmin.lodges.update', editingId.value), options);
    } else {
        form.post(route('superadmin.lodges.store'), options);
    }
}

function linkClub() {
    router.post(route('superadmin.lodges.link_club', editingId.value), { club_id: linkClubId.value }, { preserveScroll: true, onSuccess: () => { showModal.value = false; } });
}

function unlinkClub() {
    if (confirm(`Take '${editingLodge.value.display_name}' back from ${editingLodge.value.club.name}? The club and its data stay as they are.`)) {
        router.delete(route('superadmin.lodges.unlink_club', editingId.value), { preserveScroll: true, onSuccess: () => { showModal.value = false; } });
    }
}

function remove(lodge) {
    if (confirm(`Delete '${lodge.display_name}'?`)) {
        useForm({}).delete(route('superadmin.lodges.destroy', lodge.id), { preserveScroll: true });
    }
}

const inputClass = 'w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 dark:bg-slate-800 dark:border-slate-700 dark:text-white';
const filterClass = 'bg-slate-100 border border-slate-300 text-slate-900 text-xs rounded-xl px-3 py-2.5 dark:bg-slate-800 dark:border-slate-700 dark:text-white';
</script>

<template>
    <SuperAdminLayout title="Lodge Directory">
        <Head title="Superadmin - Lodge Directory" />

        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Lodge Directory</h1>
                    <p class="text-sm text-slate-500 mt-1 dark:text-slate-400">
                        {{ totals.all }} lodges and chapters ({{ totals.managed }} managed). The public list at
                        <Link :href="route('lodges.index')" class="text-blue-600 hover:underline dark:text-blue-400">/lodges</Link>.
                    </p>
                </div>
                <button @click="openCreate" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg text-sm shadow-sm transition">+ Add Lodge</button>
            </div>

            <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-lg grid gap-3 md:grid-cols-3 lg:grid-cols-6 dark:bg-slate-900 dark:border-slate-800">
                <input v-model="search.q" type="search" placeholder="Name, number, hall, town, postcode..." :class="[filterClass, 'lg:col-span-2']" />
                <select v-model="search.province" @change="applyFilters" :class="filterClass">
                    <option value="">All provinces</option>
                    <option v-for="p in provinces" :key="p.code" :value="p.code">{{ p.name }}</option>
                </select>
                <select v-model="search.order" @change="applyFilters" :class="filterClass">
                    <option value="">All orders</option>
                    <option v-for="o in orders" :key="o.code" :value="o.code">{{ o.name }}</option>
                </select>
                <select v-model="search.status" @change="applyFilters" :class="filterClass">
                    <option value="">Any status</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                    <option value="managed">Managed by a club</option>
                </select>
                <select v-model="search.gaps" @change="applyFilters" :class="filterClass">
                    <option value="">No gap filter</option>
                    <option value="no_hall">Missing hall ({{ totals.no_hall }})</option>
                    <option value="no_schedule">No meeting pattern ({{ totals.no_schedule }})</option>
                    <option value="no_province">Missing province</option>
                </select>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-xl dark:bg-slate-900 dark:border-slate-800">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-400">
                                <th class="py-3 px-4">Lodge</th>
                                <th class="py-3 px-3">Order</th>
                                <th class="py-3 px-3">Province</th>
                                <th class="py-3 px-3">Hall</th>
                                <th class="py-3 px-3">Meets</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-xs dark:divide-slate-800">
                            <tr v-for="l in lodges.data" :key="l.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                    {{ l.display_name }}<span v-if="l.number" class="font-normal text-slate-500"> · No. {{ l.number }}</span>
                                    <span v-if="l.is_managed" class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">managed</span>
                                    <span v-if="l.status !== 'active'" class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">{{ l.status }}</span>
                                </td>
                                <td class="py-3 px-3 text-slate-700 dark:text-slate-300">{{ l.order }}</td>
                                <td class="py-3 px-3" :class="l.province ? 'text-slate-700 dark:text-slate-300' : 'text-amber-600 dark:text-amber-400'">{{ l.province || 'No province' }}</td>
                                <td class="py-3 px-3" :class="l.hall ? 'text-slate-700 dark:text-slate-300' : 'text-amber-600 dark:text-amber-400'">{{ l.hall || 'No hall' }}</td>
                                <td class="py-3 px-3 text-slate-700 dark:text-slate-300 max-w-xs">
                                    <span v-if="l.schedules.length"><span v-for="(sch, i) in l.schedules" :key="i" class="block">{{ describePattern(sch) }}</span></span>
                                    <span v-else-if="l.meets_text" class="line-clamp-2 text-slate-500" :title="l.meets_text">{{ l.meets_text }}</span>
                                    <span v-else class="text-amber-600 dark:text-amber-400">Not listed</span>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <button @click="openEdit(l)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs rounded border border-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700">Edit</button>
                                    <button @click="remove(l)" class="ml-2 px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs rounded border border-rose-200 dark:bg-rose-950/50 dark:hover:bg-rose-900/40 dark:text-rose-400 dark:border-rose-800/50">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="lodges.data.length === 0">
                                <td colspan="6" class="py-12 px-4 text-center text-slate-500 dark:text-slate-400">No lodges match.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav v-if="lodges.links.length > 3" class="flex flex-wrap gap-1.5 border-t border-slate-200 p-3 dark:border-slate-800" aria-label="Pages">
                    <template v-for="link in lodges.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" preserve-scroll :class="['rounded-md border px-2.5 py-1 text-xs', link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800']" v-html="link.label" />
                        <span v-else class="rounded-md border border-slate-200 px-2.5 py-1 text-xs text-slate-400 dark:border-slate-800" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm dark:bg-black/70">
            <div class="bg-white border border-slate-200 rounded-xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ editingId ? 'Edit lodge' : 'Add lodge' }}</h3>
                    <button @click="showModal = false" class="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submit" class="space-y-3 text-xs">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="block font-medium mb-1 dark:text-slate-300">Name</label>
                            <input v-model="form.name" type="text" required placeholder="e.g. Lodge of Industry" :class="inputClass" />
                            <p v-if="form.errors.name" class="text-rose-600 mt-1">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block font-medium mb-1 dark:text-slate-300">Number</label>
                            <input v-model="form.number" type="text" :class="inputClass" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium mb-1 dark:text-slate-300">Order</label>
                            <select v-model="form.club_type_id" required :class="inputClass">
                                <option value="" disabled>Choose an order</option>
                                <option v-for="o in allOrders" :key="o.id" :value="o.id">{{ o.name }}</option>
                            </select>
                            <p v-if="form.errors.club_type_id" class="text-rose-600 mt-1">{{ form.errors.club_type_id }}</p>
                        </div>
                        <div>
                            <label class="block font-medium mb-1 dark:text-slate-300">Status</label>
                            <select v-model="form.status" :class="inputClass">
                                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium mb-1 dark:text-slate-300">Province</label>
                            <select v-model="form.province_id" :class="inputClass">
                                <option value="">No province</option>
                                <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium mb-1 dark:text-slate-300">Meets at</label>
                            <select v-model="form.masonic_hall_id" :class="inputClass">
                                <option value="">No hall</option>
                                <option v-for="h in hallsForProvince" :key="h.id" :value="h.id">{{ h.name }}{{ h.town ? ', ' + h.town : '' }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium mb-1 dark:text-slate-300">When it meets, as the source words it</label>
                        <textarea v-model="form.meets_text" rows="2" :class="inputClass" />
                        <label class="mt-1 flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <input v-model="form.reparse" type="checkbox" /> Read the pattern below from this wording when I save
                        </label>
                    </div>

                    <fieldset v-if="form.schedule === null" class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <legend class="px-1 font-medium dark:text-slate-300">Meeting patterns (used for expected dates)</legend>
                        <ul class="space-y-1">
                            <li v-for="(sch, i) in editingLodge.schedules" :key="i">{{ describePattern(sch) }}<span v-if="sch.start_time"> · from {{ sch.start_time }}</span></li>
                        </ul>
                        <p class="mt-2 text-slate-500 dark:text-slate-400">This lodge meets on different days in different months, so its patterns are kept as they are. Tick "Read the pattern from this wording" above to build them again from the wording.</p>
                    </fieldset>
                    <fieldset v-else class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <legend class="px-1 font-medium dark:text-slate-300">Meeting pattern (used for expected dates)</legend>
                        <div class="grid grid-cols-3 gap-3">
                            <select v-model="form.schedule.occurrence" :class="inputClass" aria-label="Occurrence">
                                <option value="">Occurrence</option>
                                <option v-for="o in occurrences" :key="o" :value="o">{{ o }}</option>
                            </select>
                            <select v-model="form.schedule.day_of_week" :class="inputClass" aria-label="Day">
                                <option value="">Day</option>
                                <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
                            </select>
                            <input v-model="form.schedule.start_time" type="time" :class="inputClass" aria-label="Start time" />
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button v-for="(label, i) in MONTHS" :key="label" type="button" @click="toggleMonth(i + 1)"
                                :class="['rounded-md border px-2 py-1', form.schedule.months.includes(i + 1) ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 dark:border-slate-700']">{{ label }}</button>
                        </div>
                        <p class="mt-2 text-slate-500 dark:text-slate-400">Leave the day and months empty to remove the pattern; the wording above is still shown.</p>
                        <p v-if="form.errors['schedule.start_time']" class="text-rose-600">{{ form.errors['schedule.start_time'] }}</p>
                    </fieldset>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium mb-1 dark:text-slate-300">Installation month</label>
                            <select v-model="form.installation_month" :class="inputClass">
                                <option value="">Not listed</option>
                                <option v-for="(name, number) in months" :key="number" :value="Number(number)">{{ name }}</option>
                            </select>
                            <p class="mt-1 text-slate-500 dark:text-slate-400">The installation is the usual meeting in this month.</p>
                        </div>
                        <div>
                            <label class="block font-medium mb-1 dark:text-slate-300">Website</label>
                            <input v-model="form.website_url" type="url" placeholder="https://..." :class="inputClass" />
                            <p v-if="form.errors.website_url" class="text-rose-600 mt-1">{{ form.errors.website_url }}</p>
                        </div>
                    </div>

                    <fieldset v-if="editingLodge?.sources.length" class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <legend class="px-1 font-medium dark:text-slate-300">Where the details came from</legend>
                        <ul class="space-y-2">
                            <li v-for="src in editingLodge.sources" :key="src.url">
                                <a :href="src.url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline dark:text-blue-400">{{ src.label }} ↗</a>
                                <span class="block text-slate-500 dark:text-slate-400">
                                    <template v-if="src.last_checked_at">Checked {{ src.last_checked_at }}: {{ src.last_status }}<span v-if="src.changed_at"> (page changed {{ src.changed_at }})</span></template>
                                    <template v-else>Not checked yet. Run <code>php artisan lodges:check-sources</code>.</template>
                                </span>
                            </li>
                        </ul>
                    </fieldset>

                    <fieldset v-if="editingLodge" class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <legend class="px-1 font-medium dark:text-slate-300">Club account</legend>
                        <div v-if="editingLodge.club" class="flex flex-wrap items-center justify-between gap-2">
                            <span>Managed by <strong>{{ editingLodge.club.name }}</strong></span>
                            <button type="button" class="rounded-md bg-rose-50 px-3 py-1.5 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-400" @click="unlinkClub">Take the listing back</button>
                        </div>
                        <div v-else class="flex flex-wrap items-center gap-2">
                            <select v-model="linkClubId" :class="[inputClass, 'flex-1']" aria-label="Club to link">
                                <option value="">Link to a club that already exists…</option>
                                <option v-for="club in linkableClubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                            </select>
                            <button type="button" :disabled="!linkClubId" class="rounded-md bg-blue-600 px-3 py-2 font-medium text-white hover:bg-blue-500 disabled:opacity-50" @click="linkClub">Link</button>
                        </div>
                        <p v-if="$page.props.errors.club_id" class="mt-1 text-rose-600">{{ $page.props.errors.club_id }}</p>
                    </fieldset>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-md hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-500">{{ editingId ? 'Save' : 'Add lodge' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>
