<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import Modal from '@/Components/Ui/Modal.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
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
</script>

<template>
    <SuperAdminLayout title="Masonic Grand Lodges and country heads">
        <Head title="Superadmin - Grand Lodges and governing bodies" />

        <div class="space-y-6">
            <PageHeader
                title="Grand Lodges and country governing bodies"
                description="Manage national Masonic sovereign Grand Lodges (UGLE, Grand Lodge of Scotland, Ireland, Malta, etc.) and their regional jurisdictions."
            >
                <template #actions>
                    <Button @click="openCreateModal">
                        <template #icon><UiIcon name="plus" /></template>
                        Add a Grand Lodge
                    </Button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard label="Governing Grand Lodges" :value="grandLodges.length" caption="Sovereign Masonic heads" />
                <StatCard
                    label="Total managed provinces"
                    :value="grandLodges.reduce((acc, gl) => acc + (gl.provinces_count || 0), 0)"
                    caption="Provinces across all Grand Lodges"
                />
                <StatCard
                    label="Official web portals"
                    :value="grandLodges.filter(gl => gl.website_url).length"
                    caption="Active Grand Lodge websites"
                />
                <StatCard
                    label="Jurisdictional countries"
                    :value="new Set(grandLodges.map(gl => gl.country)).size"
                    caption="Global sovereign territories"
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Card
                    v-for="gl in grandLodges"
                    :key="gl.id"
                    class="flex flex-col justify-between space-y-4"
                >
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                                <UiIcon name="bank" :size="20" />
                            </span>
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900 leading-tight dark:text-white">{{ gl.name }}</h2>
                                <div class="flex items-center gap-2 mt-1">
                                    <Badge variant="info">{{ gl.short_name || gl.code }}</Badge>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        Country: <span class="font-medium text-slate-800 dark:text-slate-200">{{ gl.country }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-slate-500 leading-relaxed dark:text-slate-400">
                            {{ gl.description || 'Sovereign Masonic governing body for Craft lodges and regional provinces.' }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs dark:border-slate-800">
                        <div class="flex items-center gap-4">
                            <div class="text-slate-700 dark:text-slate-300">
                                <span class="font-semibold text-slate-900 dark:text-white">{{ gl.provinces_count }}</span>
                                <span class="text-slate-500 ml-1 dark:text-slate-400">provinces</span>
                            </div>
                            <div class="text-slate-700 dark:text-slate-300">
                                <span class="font-semibold text-slate-900 dark:text-white">{{ gl.clubs_count }}</span>
                                <span class="text-slate-500 ml-1 dark:text-slate-400">lodges</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <a
                                v-if="gl.website_url"
                                :href="gl.website_url"
                                target="_blank"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Official portal
                                <UiIcon name="external-link" :size="14" />
                            </a>
                            <Button variant="secondary" size="sm" :href="route('superadmin.grand_lodges.ranks', gl.id)">Ranks</Button>
                            <Button variant="secondary" size="sm" @click="startEdit(gl)">Edit</Button>
                            <Button variant="secondary" size="sm" @click="deleteGrandLodge(gl)">Delete</Button>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <Modal :open="showCreateModal" title="Add a Masonic Grand Lodge" size="lg" @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="space-y-4">
                <Input
                    v-model="createForm.name"
                    label="Grand Lodge name"
                    placeholder="e.g. Grand Lodge of Scotland"
                    required
                />
                <div class="grid grid-cols-2 gap-3">
                    <Input
                        v-model="createForm.short_name"
                        label="Abbreviation or short name"
                        placeholder="e.g. GLoS"
                       
                    />
                    <Input
                        v-model="createForm.code"
                        label="System code key"
                        placeholder="e.g. glos"
                       
                        required
                    />
                </div>
                <Input
                    v-model="createForm.country"
                    label="Sovereign country or territory"
                    placeholder="e.g. Scotland"
                    required
                />
                <Input
                    v-model="createForm.website_url"
                    type="url"
                    label="Official website URL"
                    placeholder="https://www.grandlodgescotland.com"
                />
                <Textarea
                    v-model="createForm.description"
                    :rows="3"
                    label="Description"
                    placeholder="Summary of this Grand Lodge jurisdiction..."
                />

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <Button variant="secondary" @click="showCreateModal = false">Cancel</Button>
                    <Button type="submit" :disabled="createForm.processing">Add Grand Lodge</Button>
                </div>
            </form>
        </Modal>

        <Modal :open="Boolean(editingGrandLodge)" :title="editingGrandLodge ? `Edit ${editingGrandLodge.name}` : ''" size="lg" @close="editingGrandLodge = null">
            <form @submit.prevent="submitUpdate" class="space-y-4">
                <Input v-model="editForm.name" label="Grand Lodge name" required />
                <div class="grid grid-cols-2 gap-3">
                    <Input v-model="editForm.short_name" label="Abbreviation or short name" />
                    <Input v-model="editForm.country" label="Sovereign country" required />
                </div>
                <Input v-model="editForm.website_url" type="url" label="Official website URL" />
                <Textarea v-model="editForm.description" :rows="3" label="Description" />

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <Button variant="secondary" @click="editingGrandLodge = null">Cancel</Button>
                    <Button type="submit" :disabled="editForm.processing">Save changes</Button>
                </div>
            </form>
        </Modal>
    </SuperAdminLayout>
</template>

