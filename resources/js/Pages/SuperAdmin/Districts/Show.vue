<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
    district: { type: Object, required: true },
    grandLodges: { type: Array, default: () => [] },
});

const deleteForm = useForm({});

function confirmDelete() {
    if (confirm(`Are you sure you want to permanently delete '${props.district.name}'? This action cannot be undone.`)) {
        deleteForm.delete(route('superadmin.districts.destroy', props.district.id));
    }
}

function typeBadge(type) {
    switch (type) {
        case 'district': return { label: 'Active district', variant: 'info' };
        case 'group':    return { label: 'Group of lodges', variant: 'success' };
        case 'dormant':  return { label: 'Dormant', variant: 'danger' };
        default:         return { label: type, variant: 'neutral' };
    }
}

const badge = typeBadge(props.district.type);
</script>

<template>
    <SuperAdminLayout :title="district.name">
        <Head :title="`${district.name} - Districts and groups`" />

        <div class="space-y-6 max-w-5xl mx-auto">
            <PageHeader
                :title="district.name"
                :breadcrumbs="[
                    { label: 'Dashboard', href: route('superadmin.dashboard') },
                    { label: 'Districts and groups', href: route('superadmin.districts.index') },
                    { label: district.name },
                ]"
            >
                <template #actions>
                    <Button :href="route('superadmin.districts.edit', district.id)">
                        <template #icon><UiIcon name="pencil" /></template>
                        Edit district
                    </Button>
                    <Button variant="danger" :disabled="deleteForm.processing" @click="confirmDelete">
                        <template #icon><UiIcon name="trash" /></template>
                        Delete
                    </Button>
                </template>
            </PageHeader>

            <div class="flex flex-wrap items-center gap-3 -mt-3">
                <Badge :variant="badge.variant">{{ badge.label }}</Badge>
                <span class="font-mono text-xs text-slate-600 dark:text-slate-400">{{ district.code }}</span>
                <span v-if="district.grand_lodge" class="text-xs text-slate-500 dark:text-slate-400">
                    via <span class="text-slate-900 font-medium dark:text-white">{{ district.grand_lodge.short_name || district.grand_lodge.name }}</span>
                </span>
            </div>

            <Alert v-if="district.type === 'dormant'" variant="danger" title="Dormant district">
                {{ district.description || 'Freemasonry is banned or suspended in this jurisdiction.' }}
            </Alert>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <Card class="space-y-4">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200 pb-3 dark:text-slate-400 dark:border-slate-800">
                        Identification
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">Official name</dt>
                            <dd class="text-slate-900 font-semibold text-right max-w-xs dark:text-white">{{ district.name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">Type</dt>
                            <dd><Badge :variant="badge.variant">{{ badge.label }}</Badge></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">System code</dt>
                            <dd class="font-mono text-slate-700 dark:text-slate-300">{{ district.code }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">Country</dt>
                            <dd class="text-slate-800 font-medium dark:text-slate-200">{{ district.country || '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">Region</dt>
                            <dd class="text-slate-800 dark:text-slate-200">{{ district.region || '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">Governing Grand Lodge</dt>
                            <dd class="text-slate-900 font-medium dark:text-white">{{ district.grand_lodge?.name || '—' }}</dd>
                        </div>
                    </dl>
                </Card>

                <Card class="space-y-4">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200 pb-3 dark:text-slate-400 dark:border-slate-800">
                        District executive rulers
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">District Grand Master</dt>
                            <dd class="text-slate-900 font-medium dark:text-white">{{ district.district_grand_master || '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">District Grand Secretary</dt>
                            <dd class="text-slate-900 font-medium dark:text-white">{{ district.district_grand_secretary || '—' }}</dd>
                        </div>
                    </dl>
                    <p v-if="!district.district_grand_master && !district.district_grand_secretary" class="text-xs text-slate-500 italic mt-2 dark:text-slate-400">
                        No leadership details recorded yet. <Link :href="route('superadmin.districts.edit', district.id)" class="text-blue-600 hover:underline dark:text-blue-400">Add them →</Link>
                    </p>
                </Card>

                <Card class="space-y-4">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200 pb-3 dark:text-slate-400 dark:border-slate-800">
                        Office address and contact
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div v-if="district.address_line_1" class="space-y-0.5">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">Address</dt>
                            <dd class="text-slate-800 dark:text-slate-200">
                                <div>{{ district.address_line_1 }}</div>
                                <div v-if="district.address_line_2">{{ district.address_line_2 }}</div>
                                <div v-if="district.town || district.county">{{ [district.town, district.county].filter(Boolean).join(', ') }}</div>
                                <div v-if="district.postcode" class="font-mono">{{ district.postcode }}</div>
                            </dd>
                        </div>
                        <div v-else class="text-slate-500 italic dark:text-slate-400">No address recorded.</div>
                        <div v-if="district.telephone" class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">Telephone</dt>
                            <dd class="text-slate-800 font-mono dark:text-slate-200">{{ district.telephone }}</dd>
                        </div>
                        <div v-if="district.email" class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">Email</dt>
                            <dd><a :href="`mailto:${district.email}`" class="text-blue-600 hover:underline dark:text-blue-400">{{ district.email }}</a></dd>
                        </div>
                        <div v-if="district.website_url" class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">Website</dt>
                            <dd>
                                <a :href="district.website_url" target="_blank" class="text-blue-600 hover:underline inline-flex items-center gap-1 dark:text-blue-400">
                                    <span>Visit</span>
                                    <UiIcon name="external-link" />
                                </a>
                            </dd>
                        </div>
                    </dl>
                </Card>

                <Card class="space-y-4">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200 pb-3 dark:text-slate-400 dark:border-slate-800">
                        Social media and online
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div v-if="district.twitter_url" class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">Twitter / X</dt>
                            <dd>
                                <a :href="district.twitter_url" target="_blank" class="text-blue-600 hover:underline inline-flex items-center gap-1 dark:text-blue-400">
                                    <span>View profile</span>
                                    <UiIcon name="external-link" />
                                </a>
                            </dd>
                        </div>
                        <div v-if="district.facebook_url" class="flex justify-between">
                            <dt class="text-slate-500 font-medium dark:text-slate-400">Facebook</dt>
                            <dd>
                                <a :href="district.facebook_url" target="_blank" class="text-blue-600 hover:underline inline-flex items-center gap-1 dark:text-blue-400">
                                    <span>View page</span>
                                    <UiIcon name="external-link" />
                                </a>
                            </dd>
                        </div>
                        <p v-if="!district.twitter_url && !district.facebook_url" class="text-xs text-slate-500 italic dark:text-slate-400">
                            No social media profiles recorded. <Link :href="route('superadmin.districts.edit', district.id)" class="text-blue-600 hover:underline dark:text-blue-400">Add them →</Link>
                        </p>
                    </dl>
                </Card>
            </div>

            <Card v-if="district.description">
                <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200 pb-3 mb-4 dark:text-slate-400 dark:border-slate-800">
                    Overview and notes
                </h2>
                <p class="text-sm text-slate-700 leading-relaxed dark:text-slate-300">{{ district.description }}</p>
            </Card>

            <div class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-800">
                <Button variant="ghost" size="sm" :href="route('superadmin.districts.index')">
                    <template #icon><UiIcon name="arrow-left" /></template>
                    Back to districts and groups
                </Button>
                <Button variant="secondary" :href="route('superadmin.districts.edit', district.id)">
                    <template #icon><UiIcon name="pencil" /></template>
                    Edit this entry
                </Button>
            </div>
        </div>
    </SuperAdminLayout>
</template>
