<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Modal from '@/Components/Ui/Modal.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Select from '@/Components/Ui/Select.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
        case 'district': return { label: 'District', variant: 'info' };
        case 'group': return { label: 'Group', variant: 'success' };
        case 'dormant': return { label: 'Dormant', variant: 'danger' };
        default: return { label: type, variant: 'neutral' };
    }
}

const TYPE_OPTIONS = [
    { value: 'district', label: 'District' },
    { value: 'group', label: 'Group' },
    { value: 'dormant', label: 'Dormant' },
];

const filterClass = (active) => [
    'inline-flex items-center rounded-xl px-3 py-1.5 text-xs font-medium transition-colors',
    active
        ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
];

const fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
</script>

<template>
    <SuperAdminLayout title="Districts and groups">
        <Head title="Superadmin - Districts and groups" />

        <div class="space-y-6">
            <PageHeader
                title="Districts and groups"
                description="UGLE overseas District Grand Lodges, groups of lodges and dormant districts worldwide."
            >
                <template #actions>
                    <Button @click="openCreateModal">
                        <template #icon><UiIcon name="plus" /></template>
                        Add district / group
                    </Button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <StatCard label="Total overseas bodies" :value="districts.length" caption="UGLE international jurisdictions" />
                <StatCard label="Active districts" :value="activeDistricts.length" caption="District Grand Lodges overseas" />
                <StatCard label="Groups of lodges" :value="groups.length" caption="Informal groupings (no DGL)" />
                <StatCard label="Dormant" :value="dormant.length" caption="Districts where Freemasonry is banned or inactive" />
                <StatCard
                    label="Total registered lodges"
                    :value="districts.reduce((sum, d) => sum + (d.clubs_count || 0), 0)"
                    caption="Across all districts"
                />
            </div>

            <Card padding="sm" class="space-y-4">
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                    <div class="relative flex-1 max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                            <UiIcon name="search" />
                        </div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by name, country, region or code..."
                            aria-label="Search districts and groups"
                            class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-9 text-sm text-slate-900 placeholder-slate-400 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            type="button"
                            aria-label="Clear search"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-900 dark:text-slate-500 dark:hover:text-white"
                        ><UiIcon name="x" /></button>
                    </div>
                    <div class="text-xs text-slate-500 font-medium dark:text-slate-400">
                        Showing <strong class="text-slate-900 dark:text-white">{{ filteredDistricts.length }}</strong> of {{ districts.length }} entries
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-2 dark:text-slate-400">Filter type</span>
                    <button type="button" @click="selectedTypeFilter = 'all'" :class="filterClass(selectedTypeFilter === 'all')">
                        All ({{ districts.length }})
                    </button>
                    <button type="button" @click="selectedTypeFilter = 'district'" :class="filterClass(selectedTypeFilter === 'district')">
                        Districts ({{ activeDistricts.length }})
                    </button>
                    <button type="button" @click="selectedTypeFilter = 'group'" :class="filterClass(selectedTypeFilter === 'group')">
                        Groups ({{ groups.length }})
                    </button>
                    <button type="button" @click="selectedTypeFilter = 'dormant'" :class="filterClass(selectedTypeFilter === 'dormant')">
                        Dormant ({{ dormant.length }})
                    </button>
                </div>
            </Card>

            <Card padding="none" class="overflow-hidden">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider dark:text-slate-400">
                        Showing {{ filteredDistricts.length }} of {{ districts.length }} overseas bodies
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
                                <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">
                                    <Link :href="route('superadmin.districts.show', d.id)" class="hover:text-blue-700 transition dark:hover:text-blue-300">
                                        {{ d.name }}
                                    </Link>
                                    <p v-if="d.description && d.type === 'dormant'" class="text-[11px] text-rose-700 font-normal mt-0.5 truncate max-w-xs dark:text-rose-400">{{ d.description }}</p>
                                </td>
                                <td class="py-3.5 px-3">
                                    <Badge :variant="typeBadge(d.type).variant">{{ typeBadge(d.type).label }}</Badge>
                                </td>
                                <td class="py-3.5 px-3 font-medium text-slate-800 dark:text-slate-200">{{ d.country || '—' }}</td>
                                <td class="py-3.5 px-3 text-slate-700 dark:text-slate-300">{{ d.region || '—' }}</td>
                                <td class="py-3.5 px-3 font-mono text-slate-600 dark:text-slate-400">{{ d.code }}</td>
                                <td class="py-3.5 px-3">
                                    <a v-if="d.website_url" :href="d.website_url" target="_blank" class="text-blue-600 hover:underline inline-flex items-center gap-1 dark:text-blue-400">
                                        <span>Website</span>
                                        <UiIcon name="external-link" :size="16" />
                                    </a>
                                    <span v-else class="text-slate-500 italic dark:text-slate-400">—</span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button variant="secondary" size="sm" :href="route('superadmin.districts.show', d.id)">View</Button>
                                        <Button variant="secondary" size="sm" :href="route('superadmin.districts.edit', d.id)">Edit</Button>
                                        <Button variant="secondary" size="sm" @click="deleteDistrict(d)">Delete</Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredDistricts.length === 0">
                                <td colspan="7" class="py-12 px-4 text-center text-slate-500 dark:text-slate-400">
                                    <div class="flex justify-center text-slate-400 dark:text-slate-500"><UiIcon name="search" :size="20" /></div>
                                    <p class="mt-2 font-semibold text-sm text-slate-700 dark:text-slate-300">No entries match your filters</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Try another search term or choose 'All'.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <Modal :open="showCreateModal" title="Add a district or group" size="lg" @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="space-y-4">
                <Input
                    v-model="createForm.name"
                    label="Official name"
                    placeholder="e.g. District Grand Lodge of Singapore"
                    :error="createForm.errors.name"
                    required
                />
                <div class="grid grid-cols-2 gap-3">
                    <Select v-model="createForm.type" label="Type" :options="TYPE_OPTIONS" :error="createForm.errors.type" required />
                    <div class="space-y-1.5">
                        <label for="create-grand-lodge" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Governing Grand Lodge</label>
                        <select id="create-grand-lodge" v-model="createForm.grand_lodge_id" :class="fieldClass">
                            <option v-for="gl in grandLodges" :key="gl.id" :value="gl.id">{{ gl.short_name || gl.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <Input v-model="createForm.country" label="Country" placeholder="e.g. Singapore" :error="createForm.errors.country" />
                    <Input v-model="createForm.region" label="Region" placeholder="e.g. South East Asia" :error="createForm.errors.region" />
                </div>
                <Input v-model="createForm.code" label="System code" placeholder="e.g. singapore" class="font-mono" :error="createForm.errors.code" required />
                <Input v-model="createForm.website_url" type="url" label="Official website URL" placeholder="https://..." :error="createForm.errors.website_url" />
                <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <Button variant="secondary" @click="showCreateModal = false">Cancel</Button>
                    <Button type="submit" :loading="createForm.processing">Add entry</Button>
                </div>
            </form>
        </Modal>
    </SuperAdminLayout>
</template>
