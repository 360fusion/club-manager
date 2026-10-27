<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
    district: { type: Object, required: true },
    grandLodges: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.district.name || '',
    code: props.district.code || '',
    type: props.district.type || 'district',
    grand_lodge_id: props.district.grand_lodge_id || null,
    country: props.district.country || '',
    region: props.district.region || '',
    website_url: props.district.website_url || '',
    district_grand_master: props.district.district_grand_master || '',
    district_grand_secretary: props.district.district_grand_secretary || '',
    address_line_1: props.district.address_line_1 || '',
    address_line_2: props.district.address_line_2 || '',
    town: props.district.town || '',
    county: props.district.county || '',
    postcode: props.district.postcode || '',
    telephone: props.district.telephone || '',
    email: props.district.email || '',
    twitter_url: props.district.twitter_url || '',
    facebook_url: props.district.facebook_url || '',
    description: props.district.description || '',
});

function submitUpdate() {
    form.put(route('superadmin.districts.update', props.district.id));
}

const TYPE_OPTIONS = [
    { value: 'district', label: 'District Grand Lodge' },
    { value: 'group', label: 'Group of lodges' },
    { value: 'dormant', label: 'Dormant' },
];
</script>

<template>
    <SuperAdminLayout :title="`Edit ${district.name}`">
        <Head :title="`Edit - ${district.name}`" />

        <div class="space-y-8 max-w-5xl mx-auto">
            <PageHeader
                :title="`Edit ${district.name}`"
                description="Update official details, executive leaders, contact information and online presence."
                :breadcrumbs="[
                    { label: 'Dashboard', href: route('superadmin.dashboard') },
                    { label: 'Districts and groups', href: route('superadmin.districts.index') },
                    { label: district.name, href: route('superadmin.districts.show', district.id) },
                    { label: 'Edit' },
                ]"
            >
                <template #actions>
                    <Button variant="secondary" :href="route('superadmin.districts.show', district.id)">
                        <template #icon><UiIcon name="arrow-left" /></template>
                        Cancel and go back
                    </Button>
                </template>
            </PageHeader>

            <form @submit.prevent="submitUpdate" class="space-y-8">
                <Card class="space-y-4">
                    <h3 class="text-base font-semibold text-slate-900 border-b border-slate-200 pb-3 dark:text-white dark:border-slate-800">
                        Identification and classification
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input v-model="form.name" label="Official name" :error="form.errors.name" required />

                        <Select v-model="form.type" label="Type" :options="TYPE_OPTIONS" :error="form.errors.type" required />

                        <div class="space-y-1.5">
                            <label for="edit-grand-lodge" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Governing Grand Lodge</label>
                            <select
                                id="edit-grand-lodge"
                                v-model="form.grand_lodge_id"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            >
                                <option :value="null">None (independent)</option>
                                <option v-for="gl in grandLodges" :key="gl.id" :value="gl.id">
                                    {{ gl.name }} ({{ gl.short_name || gl.country }})
                                </option>
                            </select>
                            <p v-if="form.errors.grand_lodge_id" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.grand_lodge_id }}</p>
                        </div>

                        <Input v-model="form.code" label="System code key" placeholder="e.g. singapore" class="font-mono" :error="form.errors.code" required />

                        <Input v-model="form.country" label="Country" placeholder="e.g. Singapore" :error="form.errors.country" />

                        <Input v-model="form.region" label="Geographic region" placeholder="e.g. South East Asia" :error="form.errors.region" />
                    </div>
                </Card>

                <Card class="space-y-4">
                    <h3 class="text-base font-semibold text-slate-900 border-b border-slate-200 pb-3 dark:text-white dark:border-slate-800">
                        District executive rulers
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input v-model="form.district_grand_master" label="District Grand Master" placeholder="e.g. W. Bro. John Smith" :error="form.errors.district_grand_master" />

                        <Input v-model="form.district_grand_secretary" label="District Grand Secretary" placeholder="e.g. W. Bro. James Brown" :error="form.errors.district_grand_secretary" />
                    </div>
                </Card>

                <Card class="space-y-4">
                    <h3 class="text-base font-semibold text-slate-900 border-b border-slate-200 pb-3 dark:text-white dark:border-slate-800">
                        Office address and contact information
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input v-model="form.address_line_1" label="Address line 1" placeholder="e.g. 10 Marina Boulevard" :error="form.errors.address_line_1" />

                        <Input v-model="form.address_line_2" label="Address line 2 (optional)" :error="form.errors.address_line_2" />

                        <Input v-model="form.town" label="Town / city" placeholder="e.g. Singapore City" :error="form.errors.town" />

                        <Input v-model="form.county" label="County / state / province" :error="form.errors.county" />

                        <Input v-model="form.postcode" label="Postal code" placeholder="e.g. 018983" class="font-mono" :error="form.errors.postcode" />

                        <Input v-model="form.telephone" label="Telephone number" placeholder="e.g. +65 6123 4567" class="font-mono" :error="form.errors.telephone" />

                        <Input v-model="form.email" type="email" label="Official email address" placeholder="e.g. secretary@freemasons.org.sg" :error="form.errors.email" />

                        <Input v-model="form.website_url" type="url" label="Official website URL" placeholder="https://www.freemasons.org.sg" class="font-mono" :error="form.errors.website_url" />
                    </div>
                </Card>

                <Card class="space-y-4">
                    <h3 class="text-base font-semibold text-slate-900 border-b border-slate-200 pb-3 dark:text-white dark:border-slate-800">
                        Social media handles and portals
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input v-model="form.twitter_url" type="url" label="Twitter / X URL" placeholder="https://x.com/..." class="font-mono" :error="form.errors.twitter_url" />

                        <Input v-model="form.facebook_url" type="url" label="Facebook page URL" placeholder="https://facebook.com/..." class="font-mono" :error="form.errors.facebook_url" />
                    </div>
                </Card>

                <Card class="space-y-4">
                    <h3 class="text-base font-semibold text-slate-900 border-b border-slate-200 pb-3 dark:text-white dark:border-slate-800">
                        Overview, background and notes
                    </h3>

                    <Textarea
                        v-model="form.description"
                        label="Description and historical notes"
                        :rows="4"
                        placeholder="Enter historical background, status notes (e.g. dormant reason), number of lodges, charity activities, etc."
                        :error="form.errors.description"
                    />
                </Card>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <Button variant="secondary" :href="route('superadmin.districts.show', district.id)">Cancel</Button>
                    <Button type="submit" :loading="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save changes' }}
                    </Button>
                </div>
            </form>
        </div>
    </SuperAdminLayout>
</template>
