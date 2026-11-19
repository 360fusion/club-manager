<script setup>
// The signing-in book at the door: tap a name to sign them in (tap again to undo). Made for a phone.
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import StatCard from '@/Components/Ui/StatCard.vue';

const props = defineProps({
  club: { type: Object, required: true },
  meeting: { type: Object, required: true },
  book: { type: Object, required: true },
});

const search = ref('');
const matches = (text) => (text || '').toLowerCase().includes(search.value.trim().toLowerCase());
const members = computed(() => props.book.members.filter((person) => matches(person.name)));
const guests = computed(() => props.book.guests.filter((guest) => matches(guest.name) || matches(guest.host)));

const busy = ref(null);
const toggle = (kind, id, present) => router.post(
  route('admin.meetings.signing_in.update', { clubSlug: props.club.slug, id: props.meeting.id }),
  { kind, id, present },
  { preserveScroll: true, preserveState: true, only: ['book'], onStart: () => { busy.value = `${kind}-${id}`; }, onFinish: () => { busy.value = null; } },
);

const visitor = useForm({ name: '', rank: '', lodge: '' });
const addVisitor = () => visitor.post(route('admin.meetings.signing_in.visitors.store', { clubSlug: props.club.slug, id: props.meeting.id }), {
  preserveScroll: true,
  onSuccess: () => visitor.reset(),
});
const removeVisitor = (walkIn) => router.delete(route('admin.meetings.signing_in.visitors.destroy', { clubSlug: props.club.slug, id: props.meeting.id, walkIn }), { preserveScroll: true, only: ['book'] });

const rowClass = (signedIn) => [
  'flex min-h-12 w-full items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500',
  signedIn
    ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100'
    : 'border-slate-200 bg-white text-slate-800 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800',
];
</script>

<template>
  <AdminLayout title="Signing-in book" :club="club" active-tab="meetings">
    <div class="mx-auto max-w-3xl space-y-5">
      <Card>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Signing-in book</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ meeting.title }} · {{ meeting.meeting_date }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button variant="secondary" size="sm" :href="route('member.meetings.collections', { slug: club.slug, id: meeting.id })">Alms and raffle</Button>
            <Button variant="secondary" size="sm" :href="route('admin.meetings.show', { clubSlug: club.slug, id: meeting.id })">Back to the meeting</Button>
          </div>
        </div>
      </Card>

      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <StatCard label="Signed in" :value="book.counts.total" />
        <StatCard label="Members" :value="book.counts.members" />
        <StatCard label="Guests" :value="book.counts.guests" />
        <StatCard label="Visitors" :value="book.counts.visitors" />
      </div>

      <Input v-model="search" type="search" label="Find a name" placeholder="Start typing a name" />

      <section class="space-y-2" aria-labelledby="members-heading">
        <h3 id="members-heading" class="text-sm font-semibold text-slate-900 dark:text-white">Members</h3>
        <p v-if="!members.length" class="text-sm text-slate-500 dark:text-slate-400">No one matches.</p>
        <button
          v-for="person in members"
          :key="person.user_id"
          type="button"
          :class="rowClass(person.signed_in)"
          :aria-pressed="person.signed_in"
          :disabled="busy === `member-${person.user_id}`"
          @click="toggle('member', person.user_id, !person.signed_in)"
        >
          <span class="min-w-0">
            <span class="block truncate font-medium">{{ person.name }}</span>
            <span class="block text-xs opacity-75">{{ person.visitor ? 'Visitor, ' : '' }}replied {{ person.reply }}</span>
          </span>
          <span class="shrink-0 text-xs font-semibold">{{ person.signed_in ? 'Signed in' : 'Sign in' }}</span>
        </button>
      </section>

      <section v-if="book.guests.length" class="space-y-2" aria-labelledby="guests-heading">
        <h3 id="guests-heading" class="text-sm font-semibold text-slate-900 dark:text-white">Guests of members</h3>
        <button
          v-for="guest in guests"
          :key="guest.id"
          type="button"
          :class="rowClass(guest.signed_in)"
          :aria-pressed="guest.signed_in"
          :disabled="busy === `guest-${guest.id}`"
          @click="toggle('guest', guest.id, !guest.signed_in)"
        >
          <span class="min-w-0">
            <span class="block truncate font-medium">{{ guest.name }}</span>
            <span class="block text-xs opacity-75">Guest of {{ guest.host }}<template v-if="guest.lodge"> · {{ guest.lodge }}</template></span>
          </span>
          <span class="shrink-0 text-xs font-semibold">{{ guest.signed_in ? 'Signed in' : 'Sign in' }}</span>
        </button>
      </section>

      <section class="space-y-2" aria-labelledby="visitors-heading">
        <h3 id="visitors-heading" class="text-sm font-semibold text-slate-900 dark:text-white">Visitors at the door</h3>
        <Card>
          <form class="grid gap-3 sm:grid-cols-[1fr_7rem_1fr_auto] sm:items-end" @submit.prevent="addVisitor">
            <Input v-model="visitor.name" label="Name" required :error="visitor.errors.name" />
            <Input v-model="visitor.rank" label="Rank" placeholder="WBro" :error="visitor.errors.rank" />
            <Input v-model="visitor.lodge" label="Lodge and number" placeholder="St John's No 1234" :error="visitor.errors.lodge" />
            <Button type="submit" :loading="visitor.processing">Sign in</Button>
          </form>
        </Card>
        <ul v-if="book.walk_ins.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white text-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
          <li v-for="walkIn in book.walk_ins" :key="walkIn.id" class="flex items-center justify-between gap-3 px-3 py-2">
            <span class="min-w-0 text-slate-800 dark:text-slate-100">
              <span class="block truncate font-medium">{{ walkIn.name }}</span>
              <span v-if="walkIn.lodge" class="block text-xs text-slate-500 dark:text-slate-400">{{ walkIn.lodge }}</span>
            </span>
            <Button variant="ghost" size="sm" :aria-label="`Remove ${walkIn.name}`" @click="removeVisitor(walkIn.id)">Remove</Button>
          </li>
        </ul>
      </section>
    </div>
  </AdminLayout>
</template>
