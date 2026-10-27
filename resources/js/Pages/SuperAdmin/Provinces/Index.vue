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
</script>

<template>
    <SuperAdminLayout title="Masonic provinces directory">
        <Head title="Superadmin - Masonic provinces" />

        <div class="space-y-6">
            <PageHeader
                title="Masonic provinces directory"
                description="Manage official UGLE, Scottish, Irish and Maltese Masonic provinces and districts."
            >
                <template #actions>
                    <Button @click="openCreateModal">
                        <template #icon><UiIcon name="plus" /></template>
                        Add Masonic province
                    </Button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <StatCard label="Provinces managed" :value="provinces.length" caption="All Grand Lodges (UGLE, Scotland, Ireland, Malta)" />
                <StatCard
                    label="UGLE provinces"
                    :value="provinces.filter(p => !p.grand_lodge || p.grand_lodge?.code === 'ugle').length"
                    caption="48 official UGLE jurisdictions"
                />
                <StatCard
                    label="Scotland (GLoS)"
                    :value="provinces.filter(p => p.grand_lodge?.code === 'glos' || p.country === 'Scotland').length"
                    caption="Scottish provincial lodges"
                />
                <StatCard
                    label="Ireland and Malta"
                    :value="provinces.filter(p => p.grand_lodge?.code === 'gli' || p.grand_lodge?.code === 'sglm' || p.country === 'Ireland' || p.country === 'Malta').length"
                    caption="Irish and Maltese jurisdictions"
                />
                <StatCard
                    label="Registered lodges"
                    :value="provinces.reduce((sum, p) => sum + (p.clubs_count || 0), 0)"
                    caption="Across all provinces"
                />
            </div>

            <Card padding="sm" class="space-y-4">
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                    <div class="relative flex-1 max-w-md">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><UiIcon name="search" /></span>
                        <input
                            v-model="searchQuery"
                            type="text"
                            aria-label="Search provinces"
                            placeholder="Search by name, code, region or Grand Lodge"
                            class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-9 text-sm text-slate-900 placeholder-slate-400 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            aria-label="Clear search"
                            @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-900 dark:hover:text-white"
                        >
                            <UiIcon name="x" />
                        </button>
                    </div>

                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Showing <strong class="font-semibold text-slate-900 dark:text-white">{{ filteredProvinces.length }}</strong> of {{ provinces.length }} provinces
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-3 space-y-2 dark:border-slate-800">
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Filter by country</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            :aria-pressed="selectedCountryFilter === 'All'"
                            @click="selectedCountryFilter = 'All'"
                            :class="[
                                'px-3 py-1 rounded-full border text-xs font-medium transition-colors',
                                selectedCountryFilter === 'All' ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'
                            ]"
                        >
                            All countries ({{ provinces.length }})
                        </button>
                        <button
                            v-for="c in countriesList"
                            :key="c"
                            type="button"
                            :aria-pressed="selectedCountryFilter === c"
                            @click="selectedCountryFilter = c"
                            :class="[
                                'px-3 py-1 rounded-full border text-xs font-medium transition-colors inline-flex items-center gap-1.5',
                                selectedCountryFilter === c ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'
                            ]"
                        >
                            <span>{{ c }}</span>
                            <span class="opacity-75">({{ provinces.filter(p => (p.country || 'England') === c).length }})</span>
                        </button>
                    </div>
                </div>
            </Card>

            <Card padding="none" class="overflow-hidden">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider dark:text-slate-400">
                        Showing {{ filteredProvinces.length }} of {{ provinces.length }} provinces
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs text-slate-500 dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-400">
                                <th scope="col" class="py-3 px-4 font-medium">Province name</th>
                                <th scope="col" class="py-3 px-3 font-medium">Governing Grand Lodge</th>
                                <th scope="col" class="py-3 px-3 font-medium">Country</th>
                                <th scope="col" class="py-3 px-3 font-medium">System code</th>
                                <th scope="col" class="py-3 px-3 font-medium">Region</th>
                                <th scope="col" class="py-3 px-3 font-medium">Lodges</th>
                                <th scope="col" class="py-3 px-3 font-medium">Official website</th>
                                <th scope="col" class="py-3 px-4 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm dark:divide-slate-800">
                            <tr v-for="prov in filteredProvinces" :key="prov.id" class="hover:bg-slate-50 transition-colors dark:hover:bg-slate-800/40">
                                <td class="py-3 px-4 font-medium text-slate-900 dark:text-white">
                                    <Link :href="route('superadmin.provinces.show', prov.id)" class="hover:text-blue-700 dark:hover:text-blue-300">
                                        {{ prov.name }}
                                    </Link>
                                </td>
                                <td class="py-3 px-3">
                                    <Badge v-if="prov.grand_lodge" variant="info">
                                        {{ prov.grand_lodge.short_name || prov.grand_lodge.name }}
                                    </Badge>
                                    <span v-else class="text-xs text-slate-500 dark:text-slate-400">UGLE</span>
                                </td>
                                <td class="py-3 px-3">
                                    <Badge>{{ prov.country || 'England' }}</Badge>
                                </td>
                                <td class="py-3 px-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                                    {{ prov.code }}
                                </td>
                                <td class="py-3 px-3 text-slate-700 dark:text-slate-300">
                                    {{ prov.region || '—' }}
                                </td>
                                <td class="py-3 px-3">
                                    <Badge variant="info">{{ prov.clubs_count }} lodges</Badge>
                                </td>
                                <td class="py-3 px-3">
                                    <a v-if="prov.website_url" :href="prov.website_url" target="_blank" class="text-blue-600 hover:underline inline-flex items-center gap-1 dark:text-blue-400">
                                        <span>Website</span>
                                        <UiIcon name="external-link" :size="14" />
                                    </a>
                                    <span v-else class="text-slate-500 dark:text-slate-400">—</span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button variant="secondary" size="sm" :href="route('superadmin.provinces.show', prov.id)">View</Button>
                                        <Button variant="secondary" size="sm" :href="route('superadmin.provinces.edit', prov.id)">Edit</Button>
                                        <Button variant="danger" size="sm" @click="deleteProvince(prov)">Delete</Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredProvinces.length === 0">
                                <td colspan="8" class="py-12 px-4 text-center text-slate-500 space-y-1 dark:text-slate-400">
                                    <p class="font-medium text-sm text-slate-700 dark:text-slate-300">No provinces match your filters</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Try another search, or choose all countries.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <Modal :open="showCreateModal" title="Add Masonic province" size="lg" @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="space-y-4">
                <Input
                    v-model="createForm.name"
                    label="Province name"
                    placeholder="e.g. Provincial Grand Lodge of Edinburgh"
                    required
                />
                <div class="grid grid-cols-2 gap-3">
                    <Select
                        v-model.number="createForm.grand_lodge_id"
                        label="Governing Grand Lodge"
                        :options="grandLodges.map(gl => ({ value: gl.id, label: gl.short_name || gl.name }))"
                    />
                    <Select
                        v-model="createForm.country"
                        label="Country"
                        :options="countriesList.map(c => ({ value: c, label: c }))"
                        required
                    />
                </div>
                <Input
                    v-model="createForm.code"
                    label="System code key"
                    placeholder="e.g. pgl_edinburgh"
                    class="font-mono"
                    required
                />
                <Input
                    v-model="createForm.region"
                    label="Geographic region"
                    placeholder="e.g. Edinburgh & Lothians"
                />
                <Input
                    v-model="createForm.website_url"
                    type="url"
                    label="Official website URL"
                    placeholder="https://www.pgledinburgh.org.uk"
                />

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <Button variant="secondary" @click="showCreateModal = false">Cancel</Button>
                    <Button type="submit" :loading="createForm.processing">Add province</Button>
                </div>
            </form>
        </Modal>

        <Modal :open="!!editingProvince" :title="editingProvince ? `Edit ${editingProvince.name}` : 'Edit province'" size="lg" @close="editingProvince = null">
            <form @submit.prevent="submitUpdate" class="space-y-4">
                <Input v-model="editForm.name" label="Province name" required />
                <div class="grid grid-cols-2 gap-3">
                    <Select
                        v-model.number="editForm.grand_lodge_id"
                        label="Governing Grand Lodge"
                        :options="grandLodges.map(gl => ({ value: gl.id, label: gl.short_name || gl.name }))"
                    />
                    <Select
                        v-model="editForm.country"
                        label="Country"
                        :options="countriesList.map(c => ({ value: c, label: c }))"
                        required
                    />
                </div>
                <Input v-model="editForm.region" label="Geographic region" />
                <Input v-model="editForm.website_url" type="url" label="Official website URL" />

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <Button variant="secondary" @click="editingProvince = null">Cancel</Button>
                    <Button type="submit" :loading="editForm.processing">Save changes</Button>
                </div>
            </form>
        </Modal>
    </SuperAdminLayout>
</template>
