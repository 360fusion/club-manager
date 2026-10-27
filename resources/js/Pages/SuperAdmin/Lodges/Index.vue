<script setup>
import { debounce } from '@/Utils/debounce';
import { ref, reactive, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { describePattern } from '@/Utils/lodgeDates';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Modal from '@/Components/Ui/Modal.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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

function applyFilters() {
    const query = Object.fromEntries(Object.entries(search).filter(([, value]) => value));
    router.get(route('superadmin.lodges.index'), query, { preserveState: true, preserveScroll: true, replace: true });
}

watch(() => search.q, debounce(() => applyFilters(), 300));

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

const inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
const filterClass = inputClass;
const labelClass = 'block text-xs font-medium text-slate-700 mb-1.5 dark:text-slate-300';
</script>

<template>
    <SuperAdminLayout title="Lodge directory">
        <Head title="Superadmin - Lodge directory" />

        <div class="space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Lodge directory</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ totals.all }} lodges and chapters ({{ totals.managed }} managed). The public list is at
                        <Link :href="route('lodges.index')" class="text-blue-600 hover:underline dark:text-blue-400">/lodges</Link>.
                    </p>
                </div>
                <Button @click="openCreate">
                    <template #icon><UiIcon name="plus" /></template>
                    Add a lodge
                </Button>
            </header>

            <Card padding="sm" class="grid gap-3 md:grid-cols-3 lg:grid-cols-6">
                <Input v-model="search.q" type="search" placeholder="Name, number, hall, town, postcode..." aria-label="Search lodges" class="lg:col-span-2" />
                <select v-model="search.province" @change="applyFilters" :class="filterClass" aria-label="Province">
                    <option value="">All provinces</option>
                    <option v-for="p in provinces" :key="p.code" :value="p.code">{{ p.name }}</option>
                </select>
                <select v-model="search.order" @change="applyFilters" :class="filterClass" aria-label="Order">
                    <option value="">All orders</option>
                    <option v-for="o in orders" :key="o.code" :value="o.code">{{ o.name }}</option>
                </select>
                <select v-model="search.status" @change="applyFilters" :class="filterClass" aria-label="Status">
                    <option value="">Any status</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                    <option value="managed">Managed by a club</option>
                </select>
                <select v-model="search.gaps" @change="applyFilters" :class="filterClass" aria-label="Missing details">
                    <option value="">No gap filter</option>
                    <option value="no_hall">Missing hall ({{ totals.no_hall }})</option>
                    <option value="no_schedule">No meeting pattern ({{ totals.no_schedule }})</option>
                    <option value="no_province">Missing province</option>
                </select>
            </Card>

            <Card padding="none" class="overflow-hidden">
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
                                <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                    {{ l.display_name }}<span v-if="l.number" class="font-normal text-slate-500 dark:text-slate-400"> · No. {{ l.number }}</span>
                                    <Badge v-if="l.is_managed" variant="success" class="ml-1">Managed</Badge>
                                    <Badge v-if="l.status !== 'active'" variant="warning" class="ml-1">{{ l.status }}</Badge>
                                </td>
                                <td class="py-3 px-3 text-slate-700 dark:text-slate-300">{{ l.order }}</td>
                                <td class="py-3 px-3" :class="l.province ? 'text-slate-700 dark:text-slate-300' : 'text-amber-700 dark:text-amber-400'">{{ l.province || 'No province' }}</td>
                                <td class="py-3 px-3" :class="l.hall ? 'text-slate-700 dark:text-slate-300' : 'text-amber-700 dark:text-amber-400'">{{ l.hall || 'No hall' }}</td>
                                <td class="py-3 px-3 text-slate-700 dark:text-slate-300 max-w-xs">
                                    <span v-if="l.schedules.length"><span v-for="(sch, i) in l.schedules" :key="i" class="block">{{ describePattern(sch) }}</span></span>
                                    <span v-else-if="l.meets_text" class="line-clamp-2 text-slate-500 dark:text-slate-400" :title="l.meets_text">{{ l.meets_text }}</span>
                                    <span v-else class="text-amber-700 dark:text-amber-400">Not listed</span>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button variant="secondary" size="sm" @click="openEdit(l)">Edit</Button>
                                        <Button variant="secondary" size="sm" @click="remove(l)">Delete</Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="lodges.data.length === 0">
                                <td colspan="6" class="py-12 px-4 text-center text-sm text-slate-500 dark:text-slate-400">No lodges match.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="lodges.links.length > 3" class="border-t border-slate-200 p-3 dark:border-slate-800">
                    <Pagination :links="lodges.links" />
                </div>
            </Card>
        </div>

        <Modal :open="showModal" :title="editingId ? 'Edit lodge' : 'Add a lodge'" size="lg" @close="showModal = false">
            <form @submit.prevent="submit" class="space-y-4 text-sm">
                <div class="grid grid-cols-3 gap-3">
                    <Input v-model="form.name" label="Name" required placeholder="e.g. Lodge of Industry" :error="form.errors.name" class="col-span-2" />
                    <Input v-model="form.number" label="Number" :error="form.errors.number" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="lodge-order" :class="labelClass">Order</label>
                        <select id="lodge-order" v-model="form.club_type_id" required :class="inputClass">
                            <option value="" disabled>Choose an order</option>
                            <option v-for="o in allOrders" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>
                        <p v-if="form.errors.club_type_id" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ form.errors.club_type_id }}</p>
                    </div>
                    <div>
                        <label for="lodge-status" :class="labelClass">Status</label>
                        <select id="lodge-status" v-model="form.status" :class="inputClass">
                            <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="lodge-province" :class="labelClass">Province</label>
                        <select id="lodge-province" v-model="form.province_id" :class="inputClass">
                            <option value="">No province</option>
                            <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="lodge-hall" :class="labelClass">Meets at</label>
                        <select id="lodge-hall" v-model="form.masonic_hall_id" :class="inputClass">
                            <option value="">No hall</option>
                            <option v-for="h in hallsForProvince" :key="h.id" :value="h.id">{{ h.name }}{{ h.town ? ', ' + h.town : '' }}</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Textarea v-model="form.meets_text" label="When it meets, as the source words it" :rows="2" :error="form.errors.meets_text" />
                    <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                        <input v-model="form.reparse" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950" /> Read the pattern below from this wording when I save
                    </label>
                </div>

                <fieldset v-if="form.schedule === null" class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <legend class="px-1 text-xs font-medium text-slate-700 dark:text-slate-300">Meeting patterns (used for expected dates)</legend>
                    <ul class="space-y-1 text-slate-700 dark:text-slate-300">
                        <li v-for="(sch, i) in editingLodge.schedules" :key="i">{{ describePattern(sch) }}<span v-if="sch.start_time"> · from {{ sch.start_time }}</span></li>
                    </ul>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">This lodge meets on different days in different months, so its patterns are kept as they are. Tick "Read the pattern from this wording" above to build them again from the wording.</p>
                </fieldset>
                <fieldset v-else class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <legend class="px-1 text-xs font-medium text-slate-700 dark:text-slate-300">Meeting pattern (used for expected dates)</legend>
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
                            :aria-pressed="form.schedule.months.includes(i + 1)"
                            :class="['rounded-full border px-2.5 py-1 text-xs font-medium transition-colors', form.schedule.months.includes(i + 1) ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800']">{{ label }}</button>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Leave the day and months empty to remove the pattern; the wording above is still shown.</p>
                    <p v-if="form.errors['schedule.start_time']" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors['schedule.start_time'] }}</p>
                </fieldset>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="lodge-installation" :class="labelClass">Installation month</label>
                        <select id="lodge-installation" v-model="form.installation_month" :class="inputClass">
                            <option value="">Not listed</option>
                            <option v-for="(name, number) in months" :key="number" :value="Number(number)">{{ name }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The installation is the usual meeting in this month.</p>
                    </div>
                    <Input v-model="form.website_url" type="url" label="Website" placeholder="https://..." :error="form.errors.website_url" />
                </div>

                <fieldset v-if="editingLodge?.sources.length" class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <legend class="px-1 text-xs font-medium text-slate-700 dark:text-slate-300">Where the details came from</legend>
                    <ul class="space-y-2">
                        <li v-for="src in editingLodge.sources" :key="src.url">
                            <a :href="src.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-blue-600 hover:underline dark:text-blue-400">
                                <span>{{ src.label }}</span>
                                <UiIcon name="external-link" />
                            </a>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                <template v-if="src.last_checked_at">Checked {{ src.last_checked_at }}: {{ src.last_status }}<span v-if="src.changed_at"> (page changed {{ src.changed_at }})</span></template>
                                <template v-else>Not checked yet. Run <code>php artisan lodges:check-sources</code>.</template>
                            </span>
                        </li>
                    </ul>
                </fieldset>

                <fieldset v-if="editingLodge" class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <legend class="px-1 text-xs font-medium text-slate-700 dark:text-slate-300">Club account</legend>
                    <div v-if="editingLodge.club" class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-slate-700 dark:text-slate-300">Managed by <strong>{{ editingLodge.club.name }}</strong></span>
                        <Button variant="danger" size="sm" @click="unlinkClub">Take the listing back</Button>
                    </div>
                    <div v-else class="flex flex-wrap items-center gap-2">
                        <select v-model="linkClubId" :class="[inputClass, 'flex-1']" aria-label="Club to link">
                            <option value="">Link to a club that already exists…</option>
                            <option v-for="club in linkableClubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                        </select>
                        <Button variant="secondary" :disabled="!linkClubId" @click="linkClub">Link</Button>
                    </div>
                    <p v-if="$page.props.errors.club_id" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $page.props.errors.club_id }}</p>
                </fieldset>

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <Button variant="secondary" @click="showModal = false">Cancel</Button>
                    <Button type="submit" :loading="form.processing">{{ editingId ? 'Save' : 'Add lodge' }}</Button>
                </div>
            </form>
        </Modal>
    </SuperAdminLayout>
</template>
