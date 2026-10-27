<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Modal from '@/Components/Ui/Modal.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
    halls: { type: Array, required: true },
    provinces: { type: Array, default: () => [] },
    kinds: { type: Object, default: () => ({}) },
});

const searchQuery = ref('');
const provinceFilter = ref('all');
const kindFilter = ref('all');
const showModal = ref(false);
const editingId = ref(null);

const withoutProvince = computed(() => props.halls.filter((h) => !h.province_id).length);

const emptyForm = () => ({
    province_id: '',
    name: '',
    kind: 'hall',
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

    if (kindFilter.value !== 'all') {
        result = result.filter((h) => h.kind === kindFilter.value);
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

const kindOptions = computed(() => Object.entries(props.kinds).map(([value, label]) => ({ value, label })));
const provinceOptions = computed(() => props.provinces.map((p) => ({ value: p.id, label: p.name })));
</script>

<template>
    <SuperAdminLayout title="Masonic halls">
        <Head title="Superadmin - Masonic halls" />

        <div class="space-y-6">
            <PageHeader title="Masonic halls" description="The buildings lodges, chapters and other bodies meet in. Lodges pick their hall on their settings page, filtered by province.">
                <template #actions>
                    <Button @click="openCreate">
                        <template #icon><UiIcon name="plus" /></template>
                        Add a Masonic hall
                    </Button>
                </template>
            </PageHeader>

            <Card padding="sm" class="flex flex-col md:flex-row gap-3">
                <Input v-model="searchQuery" class="flex-1" aria-label="Search halls" placeholder="Search by name, town, postcode or province..." />
                <Select v-model="provinceFilter" aria-label="Province" :options="[{ value: 'all', label: 'All provinces' }, { value: 'none', label: 'No province' }, ...provinceOptions]" />
                <Select v-model="kindFilter" aria-label="Type" :options="[{ value: 'all', label: 'All types' }, ...kindOptions]" />
                <div class="text-xs text-slate-500 self-center dark:text-slate-400">
                    <strong class="text-slate-900 dark:text-white">{{ filteredHalls.length }}</strong> of {{ halls.length }}
                    <span v-if="withoutProvince" class="ml-2 text-amber-700 dark:text-amber-400">{{ withoutProvince }} with no province</span>
                </div>
            </Card>

            <Card padding="none" class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200 text-xs text-slate-500 dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="py-3 px-4 font-medium">Hall</th>
                                <th scope="col" class="py-3 px-3 font-medium">Type</th>
                                <th scope="col" class="py-3 px-3 font-medium">Address</th>
                                <th scope="col" class="py-3 px-3 font-medium">Province</th>
                                <th scope="col" class="py-3 px-3 font-medium">Bodies</th>
                                <th scope="col" class="py-3 px-4 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <tr v-for="h in filteredHalls" :key="h.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="py-3.5 px-4 font-medium text-slate-900 dark:text-white">{{ h.name }}</td>
                                <td class="py-3.5 px-3 text-slate-700 dark:text-slate-300">{{ kinds[h.kind] || h.kind }}</td>
                                <td class="py-3.5 px-3 text-slate-700 dark:text-slate-300">{{ addressOf(h) || '—' }}</td>
                                <td class="py-3.5 px-3" :class="h.province ? 'text-slate-700 dark:text-slate-300' : 'text-amber-700 dark:text-amber-400'">{{ h.province?.name || 'No province' }}</td>
                                <td class="py-3.5 px-3 text-slate-700 dark:text-slate-300">{{ h.clubs_count }}</td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-2">
                                    <Button variant="secondary" size="sm" @click="openEdit(h)">Edit</Button>
                                    <Button variant="danger" size="sm" @click="deleteHall(h)">Delete</Button>
                                </td>
                            </tr>
                            <tr v-if="filteredHalls.length === 0">
                                <td colspan="6" class="py-12 px-4 text-center text-slate-500 dark:text-slate-400">No Masonic halls match.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <Modal :open="showModal" :title="editingId ? 'Edit Masonic hall' : 'Add a Masonic hall'" size="lg" @close="showModal = false">
            <form class="space-y-3" @submit.prevent="submit">
                <Input v-model="form.name" label="Name" required placeholder="e.g. Freemasons' Hall" :error="form.errors.name" />
                <Select v-model="form.kind" label="Type" :options="kindOptions" :error="form.errors.kind" />
                <Select v-model.number="form.province_id" label="Province" :options="[{ value: '', label: 'No province' }, ...provinceOptions]" />
                <Input v-model="form.address_line_1" label="Address line 1" />
                <Input v-model="form.address_line_2" label="Address line 2" />
                <div class="grid grid-cols-2 gap-3">
                    <Input v-model="form.town" label="Town or city" />
                    <Input v-model="form.county" label="County" />
                    <Input v-model="form.postcode" label="Postcode" />
                    <Input v-model="form.country" label="Country" />
                    <Input v-model="form.telephone" label="Telephone" />
                    <Input v-model="form.email" type="email" label="Email" :error="form.errors.email" />
                </div>
                <Input v-model="form.website_url" type="url" label="Website" placeholder="https://..." :error="form.errors.website_url" />
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <Button variant="secondary" @click="showModal = false">Cancel</Button>
                    <Button type="submit" :loading="form.processing">{{ editingId ? 'Save' : 'Add hall' }}</Button>
                </div>
            </form>
        </Modal>
    </SuperAdminLayout>
</template>
