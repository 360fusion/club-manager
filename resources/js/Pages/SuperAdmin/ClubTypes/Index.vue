<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import OrderColourPicker from '@/Components/OrderColourPicker.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import Modal from '@/Components/Ui/Modal.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
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
    <SuperAdminLayout title="Masonic orders and club types">
        <Head title="Superadmin - Masonic orders" />

        <div class="space-y-6">
            <PageHeader title="Masonic orders and club types" description="Set the preset ladders, terminology and Provincial rulers for each Masonic order.">
                <template #actions>
                    <Button @click="showCreateModal = true">
                        <template #icon><UiIcon name="plus" /></template>
                        Add a Masonic order preset
                    </Button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <Card padding="sm" class="lg:col-span-4">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 px-2 dark:text-slate-400">Configured orders ({{ clubTypes.length }})</h2>
                    <div class="space-y-2">
                        <button
                            v-for="ct in clubTypes"
                            :key="ct.id"
                            type="button"
                            :class="[
                                'block w-full text-left p-3.5 rounded-xl border transition-colors',
                                selectedType && selectedType.id === ct.id
                                    ? 'bg-blue-50 border-blue-400 text-slate-900 dark:bg-blue-950/60 dark:border-blue-600 dark:text-white'
                                    : 'bg-slate-50 border-slate-200 text-slate-700 hover:border-slate-400 hover:text-slate-900 dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-white'
                            ]"
                            @click="selectClubType(ct)"
                        >
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-sm font-semibold"><span :class="['h-2.5 w-2.5 shrink-0 rounded-full', orderColour(ct.colour).dot]" aria-hidden="true" />{{ ct.name }}</span>
                                <Badge class="font-mono">{{ ct.code }}</Badge>
                            </div>
                            <div class="flex items-center gap-3 mt-2 text-xs text-slate-500 dark:text-slate-400">
                                <span>{{ ct.default_officer_roles?.length || 0 }} officer roles</span>
                                <span aria-hidden="true">•</span>
                                <span>{{ ct.default_ranks?.length || 0 }} ranks</span>
                            </div>
                        </button>
                    </div>
                </Card>

                <Card v-if="selectedType" class="lg:col-span-8 space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4 dark:border-slate-800">
                        <div>
                            <div class="flex items-center gap-3">
                                <span :class="['h-4 w-4 shrink-0 rounded-full', orderColour(selectedType.colour).dot]" :title="ORDER_COLOURS.find((c) => c.key === selectedType.colour)?.label" aria-hidden="true" />
                                <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ selectedType.name }}</h2>
                                <Badge variant="info" class="font-mono">{{ selectedType.code }}</Badge>
                            </div>
                            <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">Set the default terminology, executive positions and module access.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="secondary" size="sm" :href="route('superadmin.club_types.show', selectedType.id)">View order page</Button>
                            <Button v-if="!isEditing" variant="secondary" size="sm" @click="isEditing = true">
                                <template #icon><UiIcon name="pencil" :size="14" /></template>
                                Edit preset
                            </Button>
                            <template v-else>
                                <Button variant="ghost" size="sm" @click="isEditing = false">Cancel</Button>
                                <Button size="sm" :disabled="editForm.processing" @click="updateClubType">
                                    {{ editForm.processing ? 'Saving...' : 'Save changes' }}
                                </Button>
                            </template>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <section class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-4 dark:bg-slate-900/60 dark:border-slate-800">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Order information and official links</h3>
                            <div class="space-y-3 text-sm">
                                <div>
                                    <p class="block text-xs font-medium text-slate-700 mb-1.5 dark:text-slate-300">Order colour</p>
                                    <OrderColourPicker v-if="isEditing" v-model="editForm.colour" />
                                    <div v-else class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                        <span :class="['h-4 w-4 rounded-full', orderColour(selectedType.colour).dot]" aria-hidden="true" />
                                        {{ ORDER_COLOURS.find((c) => c.key === selectedType.colour)?.label ?? 'Black' }}
                                        <span class="text-slate-500 dark:text-slate-400">· used for date tiles, calendars and chips for every club in this order</span>
                                    </div>
                                </div>
                                <Textarea
                                    v-if="isEditing"
                                    v-model="editForm.description"
                                    :rows="3"
                                    label="Order description"
                                    placeholder="Background, degree progression and eligibility requirements..."
                                />
                                <div v-else>
                                    <p class="block text-xs font-medium text-slate-700 mb-1.5 dark:text-slate-300">Order description</p>
                                    <p class="text-xs text-slate-700 leading-relaxed dark:text-slate-300">{{ selectedType.description || 'No description added yet.' }}</p>
                                </div>
                                <Input
                                    v-if="isEditing"
                                    v-model="editForm.website_url"
                                    type="url"
                                    label="Official website URL"
                                    placeholder="e.g. https://markmasonshall.org"
                                />
                                <div v-else>
                                    <p class="block text-xs font-medium text-slate-700 mb-1.5 dark:text-slate-300">Official website URL</p>
                                    <a
                                        v-if="selectedType.website_url"
                                        :href="selectedType.website_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700 font-mono underline dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        <span>{{ selectedType.website_url }}</span>
                                        <UiIcon name="external-link" :size="12" />
                                    </a>
                                    <span v-else class="text-xs text-slate-500 italic dark:text-slate-400">No official website link set.</span>
                                </div>
                            </div>
                        </section>

                        <section class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-4 dark:bg-slate-900/60 dark:border-slate-800">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Terminology</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <Input v-if="isEditing" v-model="editForm.terminology.club" label="Name for the body (e.g. Lodge, Chapter, Conclave)" />
                                <div v-else>
                                    <p class="block text-xs font-medium text-slate-700 mb-1.5 dark:text-slate-300">Name for the body (e.g. Lodge, Chapter, Conclave)</p>
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ selectedType.terminology?.club || 'Lodge' }}</span>
                                </div>
                                <Input v-if="isEditing" v-model="editForm.terminology.master" label="Presiding officer (e.g. Worshipful Master, M.E.Z.)" />
                                <div v-else>
                                    <p class="block text-xs font-medium text-slate-700 mb-1.5 dark:text-slate-300">Presiding officer (e.g. Worshipful Master, M.E.Z.)</p>
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ selectedType.terminology?.master || 'Worshipful Master' }}</span>
                                </div>
                                <Input v-if="isEditing" v-model="editForm.terminology.summons" label="Summons or circular" />
                                <div v-else>
                                    <p class="block text-xs font-medium text-slate-700 mb-1.5 dark:text-slate-300">Summons or circular</p>
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ selectedType.terminology?.summons || 'Summons' }}</span>
                                </div>
                                <Input v-if="isEditing" v-model="editForm.terminology.members" label="Members together (e.g. Brethren, Companions, Knights)" />
                                <div v-else>
                                    <p class="block text-xs font-medium text-slate-700 mb-1.5 dark:text-slate-300">Members together (e.g. Brethren, Companions, Knights)</p>
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ selectedType.terminology?.members || 'Brethren' }}</span>
                                </div>
                            </div>
                        </section>

                        <section class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3 dark:bg-slate-900/60 dark:border-slate-800">
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Provincial executive and rulers</h3>
                                <Button v-if="isEditing" variant="ghost" size="sm" @click="addRulerField">
                                    <template #icon><UiIcon name="plus" :size="14" /></template>
                                    Add a field
                                </Button>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">The ruler titles shown on the Province settings page for clubs of this type.</p>

                            <div v-if="isEditing" class="space-y-2">
                                <div v-for="(ruler, idx) in editForm.rulers_schema" :key="idx" class="flex items-center gap-2">
                                    <Input v-model="editForm.rulers_schema[idx]" class="flex-1" :aria-label="`Ruler title ${idx + 1}`" />
                                    <Button variant="ghost" size="sm" :aria-label="`Remove ${ruler}`" @click="removeRulerField(idx)">
                                        <UiIcon name="trash" />
                                    </Button>
                                </div>
                            </div>
                            <div v-else class="flex flex-wrap gap-2 pt-1">
                                <Badge v-for="(ruler, idx) in selectedType.rulers_schema || []" :key="idx">{{ ruler }}</Badge>
                                <span v-if="!selectedType.rulers_schema?.length" class="text-xs text-slate-500 italic dark:text-slate-400">No ruler fields set.</span>
                            </div>
                        </section>

                        <section class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3 dark:bg-slate-900/60 dark:border-slate-800">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Default officer ladder</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-700 dark:text-slate-300">
                                <div
                                    v-for="role in selectedType.default_officer_roles || []"
                                    :key="role.id"
                                    class="p-2 bg-white border border-slate-200 rounded-xl flex items-center justify-between dark:bg-slate-900 dark:border-slate-800"
                                >
                                    <span class="font-medium">{{ role.title }} ({{ role.short_code }})</span>
                                    <Badge v-if="role.is_executive" variant="warning">Key officer</Badge>
                                </div>
                                <span v-if="!selectedType.default_officer_roles?.length" class="text-xs text-slate-500 italic col-span-2 dark:text-slate-400">No officer roles set up.</span>
                            </div>
                        </section>
                    </div>
                </Card>
            </div>
        </div>

        <Modal :open="showCreateModal" title="Create a Masonic order preset" size="lg" @close="showCreateModal = false">
            <form class="space-y-4" @submit.prevent="submitCreate">
                <Input v-model="createForm.name" label="Order name" placeholder="e.g. Knights Templar Preceptory" required />
                <Input v-model="createForm.code" label="System code" placeholder="e.g. kt_preceptory" class="font-mono" required />
                <div>
                    <p class="block text-xs font-medium text-slate-700 mb-1.5 dark:text-slate-300">Order colour</p>
                    <OrderColourPicker v-model="createForm.colour" />
                </div>
                <Input v-model="createForm.terminology.master" label="Presiding officer" placeholder="e.g. Eminent Preceptor" />
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <Button variant="secondary" @click="showCreateModal = false">Cancel</Button>
                    <Button type="submit" :disabled="createForm.processing">Create order</Button>
                </div>
            </form>
        </Modal>
    </SuperAdminLayout>
</template>
