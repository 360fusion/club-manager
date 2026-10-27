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
  province: {
    type: Object,
    required: true,
  },
  grandLodges: {
    type: Array,
    default: () => [],
  },
});

const countriesList = ['England', 'Scotland', 'Wales', 'Ireland', 'Malta', 'Isle of Man', 'Channel Islands'];

const form = useForm({
  name: props.province.name || '',
  code: props.province.code || '',
  grand_lodge_id: props.province.grand_lodge_id || null,
  country: props.province.country || 'England',
  region: props.province.region || '',
  website_url: props.province.website_url || '',
  provincial_grand_master: props.province.provincial_grand_master || '',
  provincial_grand_secretary: props.province.provincial_grand_secretary || '',
  address_line_1: props.province.address_line_1 || '',
  address_line_2: props.province.address_line_2 || '',
  town: props.province.town || '',
  county: props.province.county || '',
  postcode: props.province.postcode || '',
  telephone: props.province.telephone || '',
  email: props.province.email || '',
  twitter_url: props.province.twitter_url || '',
  facebook_url: props.province.facebook_url || '',
  description: props.province.description || '',
  per_capita_rate: props.province.per_capita_rate ?? '',
  festival_contribution_rate: props.province.festival_contribution_rate ?? '',
});

function submitUpdate() {
  form.put(route('superadmin.provinces.update', props.province.id));
}
</script>

<template>
  <SuperAdminLayout :title="`Edit ${province.name}`">
    <Head :title="`Edit - ${province.name}`" />

    <div class="space-y-6 max-w-5xl mx-auto">
      <PageHeader
        :title="`Edit ${province.name}`"
        description="Update the office address, executive, contact details and websites."
        :breadcrumbs="[
          { label: 'Dashboard', href: route('superadmin.dashboard') },
          { label: 'Provinces', href: route('superadmin.provinces.index') },
          { label: province.name, href: route('superadmin.provinces.show', province.id) },
          { label: 'Edit' },
        ]"
      >
        <template #actions>
          <Button variant="secondary" :href="route('superadmin.provinces.show', province.id)">
            <template #icon><UiIcon name="arrow-left" /></template>
            Back to province
          </Button>
        </template>
      </PageHeader>

      <form @submit.prevent="submitUpdate" class="space-y-6">
        <Card>
          <template #header>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Identification and Grand Lodge</h3>
          </template>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input v-model="form.name" label="Official name" required />

            <Select
              v-model.number="form.grand_lodge_id"
              label="Governing Grand Lodge"
              :options="grandLodges.map(gl => ({ value: gl.id, label: `${gl.name} (${gl.short_name || gl.country})` }))"
            />

            <Select
              v-model="form.country"
              label="Country"
              :options="countriesList.map(c => ({ value: c, label: c }))"
              required
            />

            <Input v-model="form.region" label="Geographic region" placeholder="e.g. South West, East Midlands, Lothians" />
          </div>
        </Card>

        <Card>
          <template #header>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Provincial executive</h3>
          </template>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input v-model="form.provincial_grand_master" label="Provincial Grand Master" placeholder="e.g. David G. Maskell" />
            <Input v-model="form.provincial_grand_secretary" label="Provincial Grand Secretary" placeholder="e.g. Trevor Conroy" />
          </div>
        </Card>

        <Card>
          <template #header>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Office address and contact details</h3>
          </template>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input v-model="form.address_line_1" label="Address line 1" placeholder="e.g. 7 New Bridge Street" />
            <Input v-model="form.address_line_2" label="Address line 2 (optional)" />
            <Input v-model="form.town" label="Town or city" placeholder="e.g. Truro, Oxford, Durham" />
            <Input v-model="form.county" label="County" placeholder="e.g. Cornwall, Oxfordshire" />
            <Input v-model="form.postcode" label="Postcode" placeholder="e.g. TR1 2AA, OX2 7PP" class="font-mono" />
            <Input v-model="form.telephone" label="Telephone number" placeholder="e.g. 01872 276191" class="font-mono" />
            <Input v-model="form.email" type="email" label="Official email address" placeholder="e.g. secretary@cornwallfreemasons.org.uk" />
            <Input v-model="form.website_url" type="url" label="Official website URL" placeholder="https://pglcornwall.org.uk" class="font-mono" />
          </div>
        </Card>

        <Card>
          <template #header>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Dues rates (per member, per year)</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Lodges under this Province see what they owe on their accounting worksheet. Leave a rate empty if it is not set: lodges then see "rate not set" rather than a zero.</p>
          </template>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              v-model.number="form.per_capita_rate"
              type="number"
              step="0.01"
              min="0"
              label="Per-capita rate"
              :error="form.errors.per_capita_rate"
              class="font-mono"
            />
            <Input
              v-model.number="form.festival_contribution_rate"
              type="number"
              step="0.01"
              min="0"
              label="Festival contribution rate"
              :error="form.errors.festival_contribution_rate"
              class="font-mono"
            />
          </div>
        </Card>

        <Card>
          <template #header>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Social media</h3>
          </template>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input v-model="form.twitter_url" type="url" label="Twitter / X URL" placeholder="https://x.com/CornwallMasons" class="font-mono" />
            <Input v-model="form.facebook_url" type="url" label="Facebook page URL" placeholder="https://facebook.com/CornwallFreemasons" class="font-mono" />
          </div>
        </Card>

        <Card>
          <template #header>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Summary and background</h3>
          </template>

          <Textarea
            v-model="form.description"
            label="Description and history"
            placeholder="Historical background, number of lodges, charity relief notes and so on"
          />
        </Card>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
          <Button variant="secondary" :href="route('superadmin.provinces.show', province.id)">Cancel</Button>
          <Button type="submit" :loading="form.processing">
            {{ form.processing ? 'Saving...' : 'Save province changes' }}
          </Button>
        </div>
      </form>
    </div>
  </SuperAdminLayout>
</template>
