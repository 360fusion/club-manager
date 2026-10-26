<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';

const props = defineProps({
  club: Object,
  type: Object,
});

const form = useForm({
  id: props.type.id || null,
  name: props.type.name || '',
  description: props.type.description || '',
  color: props.type.color || '#4f46e5',
  icon: props.type.icon || '✉️',
  is_external_subscribable: props.type.is_external_subscribable ?? true,
  require_approval: props.type.require_approval ?? false,
  is_mandatory: props.type.is_mandatory ?? false,
  require_home_club_info: props.type.require_home_club_info ?? true,
  default_roles: props.type.default_roles || ['member'],
  sender_name: props.type.sender_name || '',
  sender_email: props.type.sender_email || '',
  is_automated_digest: props.type.is_automated_digest ?? false,
  digest_frequency: props.type.digest_frequency || 'weekly',
  digest_send_day: props.type.digest_send_day || 'friday',
  digest_send_time: props.type.digest_send_time || '09:00',
  include_updates: props.type.include_updates ?? true,
  include_upcoming_meetings: props.type.include_upcoming_meetings ?? true,
  include_upcoming_events: props.type.include_upcoming_events ?? true,
  include_news_posts: props.type.include_news_posts ?? true,
});

const availableRoles = [
  { id: 'member', label: 'Members and brethren' },
  { id: 'coach', label: 'Coaches and officers' },
  { id: 'admin', label: 'Admins and executive board' },
  { id: 'treasurer', label: 'Treasurers' },
];

const toggleRole = (roleId) => {
  const index = form.default_roles.indexOf(roleId);
  if (index > -1) {
    form.default_roles.splice(index, 1);
  } else {
    form.default_roles.push(roleId);
  }
};

const saveChannel = () => {
  form.post(route('admin.newsletters.types.store', { clubSlug: props.club.slug }));
};

const FREQUENCY_OPTIONS = [
  { value: 'weekly', label: 'Weekly' },
  { value: 'biweekly', label: 'Every two weeks' },
  { value: 'monthly', label: 'Monthly' },
];
const DAY_OPTIONS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
  .map((day) => ({ value: day, label: day.charAt(0).toUpperCase() + day.slice(1) }));
const DIGEST_CONTENT = [
  ['include_updates', 'Approved updates and forwarded summonses'],
  ['include_upcoming_meetings', 'Upcoming lodge and committee meetings'],
  ['include_upcoming_events', 'Upcoming events and dining (next 30 days)'],
  ['include_news_posts', 'Recent news articles'],
];

const checkbox = 'mt-0.5 h-4 w-4 rounded accent-blue-600';
const optionBox = 'flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-800 dark:bg-slate-800/50';
const cardTitle = 'text-base font-semibold text-slate-900 dark:text-white';
</script>

<template>
  <AdminLayout :title="type.id ? 'Edit newsletter channel' : 'Create a newsletter channel'" :club="club" active-tab="newsletters">
    <Head :title="type.id ? 'Edit newsletter channel' : 'Create a newsletter channel'" />

    <div class="mx-auto max-w-4xl space-y-6">
      <Card>
        <Button variant="ghost" size="sm" :href="route('admin.newsletters.types', club.slug)">Back to channels</Button>
        <h2 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">
          {{ type.id ? `Edit channel settings: ${type.name}` : 'Create a newsletter channel' }}
        </h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          Set the channel's name and look, whether visiting brethren can subscribe, Secretary approval, and delivery settings.
        </p>
      </Card>

      <form class="space-y-6" @submit.prevent="saveChannel">
        <Card>
          <template #header><h3 :class="cardTitle">Channel identity and branding</h3></template>
          <div class="space-y-4">
            <div class="grid grid-cols-4 gap-4">
              <Input v-model="form.icon" class="col-span-1" label="Channel icon" required maxlength="10" :error="form.errors.icon" />
              <Input v-model="form.name" class="col-span-3" label="Channel name" required maxlength="255" placeholder="For example: meeting summonses, general news, social bulletins" :error="form.errors.name" />
            </div>

            <Textarea v-model="form.description" label="Channel description" :rows="2" placeholder="Briefly describe what this channel is for" :error="form.errors.description" />

            <div>
              <label for="channel-colour" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Badge colour</label>
              <div class="flex flex-wrap items-center gap-3">
                <input id="channel-colour" v-model="form.color" type="color" class="h-9 w-12 cursor-pointer rounded-xl border border-slate-300 dark:border-slate-700" />
                <input v-model="form.color" type="text" aria-label="Badge colour code" class="w-32 rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
                <Badge>
                  <span :style="{ backgroundColor: form.color }" class="inline-block h-2 w-2 rounded-full" aria-hidden="true"></span>
                  {{ form.icon }} {{ form.name || 'Sample badge' }}
                </Badge>
              </div>
              <p v-if="form.errors.color" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ form.errors.color }}</p>
            </div>
          </div>
        </Card>

        <Card>
          <template #header><h3 :class="cardTitle">Visibility and external subscriptions</h3></template>
          <div class="space-y-3">
            <label :class="optionBox">
              <input v-model="form.is_external_subscribable" type="checkbox" :class="checkbox" />
              <span>
                <span class="font-medium text-slate-900 dark:text-white">Allow external subscriptions (visiting brethren and other lodges)</span>
                <span class="mt-0.5 block text-slate-500 dark:text-slate-400">When on, this channel is listed in the national directory so brethren from other lodges can subscribe.</span>
              </span>
            </label>

            <label v-if="form.is_external_subscribable" :class="[optionBox, 'ml-4']">
              <input v-model="form.require_approval" type="checkbox" :class="checkbox" />
              <span>
                <span class="font-medium text-slate-900 dark:text-white">Require Secretary approval for visiting brethren</span>
                <span class="mt-0.5 block text-slate-500 dark:text-slate-400">Requests from outside the lodge wait for approval before they receive anything.</span>
              </span>
            </label>

            <label :class="optionBox">
              <input v-model="form.is_mandatory" type="checkbox" :class="checkbox" />
              <span>
                <span class="font-medium text-slate-900 dark:text-white">Mandatory official notice (members cannot unsubscribe)</span>
                <span class="mt-0.5 block text-slate-500 dark:text-slate-400">In line with the lodge by-laws, active members cannot unsubscribe from official notices such as summonses.</span>
              </span>
            </label>
          </div>
        </Card>

        <Card>
          <template #header><h3 :class="cardTitle">Default recipient roles</h3></template>
          <fieldset>
            <legend class="sr-only">Default recipient roles</legend>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
              <label v-for="role in availableRoles" :key="role.id" :class="[optionBox, 'items-center p-3']">
                <input type="checkbox" :checked="form.default_roles.includes(role.id)" :class="checkbox" @change="toggleRole(role.id)" />
                <span class="font-medium text-slate-900 dark:text-white">{{ role.label }}</span>
              </label>
            </div>
          </fieldset>
        </Card>

        <Card>
          <template #header><h3 :class="cardTitle">Automated digest and content sources</h3></template>
          <div class="space-y-4">
            <label :class="optionBox">
              <input v-model="form.is_automated_digest" type="checkbox" :class="checkbox" />
              <span>
                <span class="font-medium text-slate-900 dark:text-white">Send an automated digest on a schedule</span>
                <span class="mt-0.5 block text-slate-500 dark:text-slate-400">Puts together and sends one combined newsletter on a set day and time.</span>
              </span>
            </label>

            <div v-if="form.is_automated_digest" class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-800 sm:grid-cols-3">
              <Select v-model="form.digest_frequency" label="How often" :options="FREQUENCY_OPTIONS" :error="form.errors.digest_frequency" />
              <Select v-model="form.digest_send_day" label="Send on" :options="DAY_OPTIONS" :error="form.errors.digest_send_day" />
              <Input v-model="form.digest_send_time" type="time" label="Send at" :error="form.errors.digest_send_time" />
            </div>

            <fieldset class="space-y-2">
              <legend class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Content to include</legend>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label v-for="[field, label] in DIGEST_CONTENT" :key="field" :class="[optionBox, 'items-center p-3']">
                  <input v-model="form[field]" type="checkbox" :class="checkbox" />
                  <span class="font-medium text-slate-900 dark:text-white">{{ label }}</span>
                </label>
              </div>
            </fieldset>
          </div>
        </Card>

        <Card>
          <div class="flex items-center justify-end gap-3">
            <Button variant="secondary" :href="route('admin.newsletters.types', club.slug)">Cancel</Button>
            <Button type="submit" :loading="form.processing">Save channel</Button>
          </div>
        </Card>
      </form>
    </div>
  </AdminLayout>
</template>
