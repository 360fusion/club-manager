<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import NoticePreview from '@/Components/Notices/NoticePreview.vue';
import { formatLocal } from '@/Utils/localDateTime';

const props = defineProps({
  club: Object,
  // The next published meeting ({ id, title, date }), or null: the diners and non-repliers are of this meeting.
  meeting: { type: Object, default: null },
  counts: Object,
  notices: Array,
});

const form = useForm({ audience: 'all', title: '', body: '', push: true, email: true });

const audiences = computed(() => [
  { key: 'all', label: 'All members' },
  { key: 'diners', label: props.meeting ? `Diners on ${props.meeting.date}` : 'Diners at the next meeting', needsMeeting: true },
  { key: 'not_replied', label: 'Not yet replied', needsMeeting: true },
  { key: 'officers', label: 'Officers' },
]);

const historyLabels = { all: 'All members', diners: 'Diners', not_replied: 'Not yet replied', officers: 'Officers' };

const people = (count) => `${count} ${count === 1 ? 'person' : 'people'}`;
const recipientCount = computed(() => props.counts[form.audience] ?? 0);
const canSend = computed(() => recipientCount.value > 0 && (form.push || form.email) && form.title.trim() && form.body.trim() && !form.processing);

const channels = [
  { key: 'push', label: 'Phone notification', hint: 'For members with the app' },
  { key: 'email', label: 'Email too', hint: 'For everyone else' },
];

const send = () => {
  form.post(route('admin.notices.store', { clubSlug: props.club.slug }), {
    preserveScroll: true,
    onSuccess: () => form.reset('title', 'body'),
  });
};
</script>

<template>
  <AdminLayout title="Send a notice" :club="club" active-tab="newsletters">
    <Head title="Send a notice" />

    <div class="mx-auto max-w-5xl">
      <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
          <h1 class="text-xl font-bold text-slate-900 dark:text-white">Send a notice</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A short message to members' phones and by email, such as a change of time on the night.</p>
        </div>
        <Button variant="secondary" size="sm" :href="route('admin.newsletters.index', { clubSlug: club.slug })">Back to newsletters</Button>
      </div>

      <form class="grid grid-cols-1 gap-4 lg:grid-cols-5 lg:gap-6" @submit.prevent="send">
        <div class="space-y-4 lg:col-span-3">
          <Card padding="sm">
            <div class="space-y-3">
              <div id="notice-send-to" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Send to</div>
              <div class="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="notice-send-to">
                <button
                  v-for="option in audiences"
                  :key="option.key"
                  type="button"
                  role="radio"
                  :aria-checked="form.audience === option.key"
                  :disabled="option.needsMeeting && !meeting"
                  :class="[
                    'inline-flex min-h-11 items-center rounded-full border px-4 text-[13px] font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                    form.audience === option.key
                      ? 'border-blue-600 bg-blue-600 text-white'
                      : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
                  ]"
                  @click="form.audience = option.key"
                >
                  {{ option.label }}
                </button>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ people(recipientCount) }}<template v-if="!meeting"> · no meeting is published yet, so diners and replies are not available</template>
              </p>
              <p v-if="form.errors.audience" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.audience }}</p>
            </div>
          </Card>

          <Card padding="sm">
            <div class="space-y-3">
              <Input v-model="form.title" label="Title" maxlength="100" required :error="form.errors.title" />
              <Textarea v-model="form.body" label="Message" :rows="4" maxlength="1000" required :error="form.errors.body" />
            </div>
          </Card>

          <Card padding="sm">
            <div class="space-y-1">
              <button
                v-for="channel in channels"
                :key="channel.key"
                type="button"
                role="switch"
                :aria-checked="form[channel.key]"
                class="flex min-h-11 w-full items-center gap-3 text-left"
                @click="form[channel.key] = !form[channel.key]"
              >
                <span class="min-w-0 flex-1">
                  <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ channel.label }}</span>
                  <span class="block text-xs text-slate-500 dark:text-slate-400">{{ channel.hint }}</span>
                </span>
                <span :class="['relative h-[26px] w-11 shrink-0 rounded-full transition-colors', form[channel.key] ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600']" aria-hidden="true">
                  <span :class="['absolute left-[3px] top-[3px] h-5 w-5 rounded-full bg-white shadow transition-transform', form[channel.key] ? 'translate-x-[18px]' : '']"></span>
                </span>
              </button>
              <p v-if="form.errors.push" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.push }}</p>
            </div>
          </Card>
        </div>

        <div class="space-y-4 lg:col-span-2">
          <div class="space-y-2">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">How it will look</div>
            <NoticePreview :club-name="club.name" :title="form.title" :body="form.body" />
          </div>

          <Button type="submit" block :disabled="!canSend" :loading="form.processing">Send to {{ people(recipientCount) }}</Button>
        </div>
      </form>

      <Card class="mt-6">
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Recent notices</h2>
        </template>
        <ul v-if="notices.length" class="divide-y divide-slate-100 dark:divide-slate-800">
          <li v-for="notice in notices" :key="notice.id" class="space-y-2 py-3 first:pt-0 last:pb-0">
            <div class="flex flex-wrap items-center gap-2">
              <Badge>{{ historyLabels[notice.audience] ?? notice.audience }}</Badge>
              <template v-if="notice.sent_at">
                <Badge variant="success">Sent {{ formatLocal(notice.sent_at) }}</Badge>
                <Badge>{{ people(notice.recipient_count) }}</Badge>
                <Badge v-if="notice.push_count">{{ notice.push_count }} on phones</Badge>
                <Badge v-if="notice.email_count">{{ notice.email_count }} by email</Badge>
              </template>
              <Badge v-else variant="warning">Sending</Badge>
            </div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ notice.title }}</h3>
            <p class="line-clamp-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ notice.body }}</p>
            <p v-if="notice.sender" class="text-xs text-slate-500 dark:text-slate-400">From {{ notice.sender }}</p>
          </li>
        </ul>
        <p v-else class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">No notices sent yet.</p>
      </Card>
    </div>
  </AdminLayout>
</template>
