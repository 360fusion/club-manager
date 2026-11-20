<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Card from '@/Components/Ui/Card.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { isNativeApp } from '@/Support/nativeApp';

const props = defineProps({
    notifications: { type: Object, required: true },
    unreadCount: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({ category: '', unread: false }) },
});


const CATEGORIES = [
    { value: '', label: 'All' },
    { value: 'summons', label: 'Summonses' },
    { value: 'news', label: 'News' },
    { value: 'event', label: 'Events' },
    { value: 'reminder', label: 'Reminders' },
    { value: 'notice', label: 'Notices' },
    { value: 'membership', label: 'Membership' },
    { value: 'signature', label: 'Sign' },
    { value: 'passed_on', label: 'Other lodges' },
];

const ICONS = { summons: 'document', news: 'mail', event: 'calendar', reminder: 'clock', notice: 'info', membership: 'user', signature: 'pencil', passed_on: 'document' };

const categoryLabel = (value) => CATEGORIES.find((c) => c.value === value)?.label ?? value;

const chip = (active) => ['inline-flex min-h-11 shrink-0 items-center whitespace-nowrap rounded-full border px-3.5 text-[13px] font-medium transition-colors sm:min-h-9', active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'];

const isToday = (iso) => new Date(iso).toDateString() === new Date().toDateString();

// Today's items show the time; older ones the date.
const when = (iso) => (isToday(iso)
    ? new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
    : new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }));

const groups = computed(() => [
    { label: 'Today', items: props.notifications.data.filter((item) => isToday(item.at)) },
    { label: 'Earlier', items: props.notifications.data.filter((item) => !isToday(item.at)) },
].filter((group) => group.items.length));

const markAllRead = () => router.post(route('members.notifications.read_all'), {}, { preserveScroll: true });
</script>

<template>
    <MembersLayout title="Inbox" active-tab="inbox">
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Inbox</h2>
                <button v-if="unreadCount" type="button" class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="markAllRead">Mark all read</button>
            </div>

            <nav aria-label="Filter the inbox" class="-mx-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0">
                <Link v-for="category in CATEGORIES" :key="category.value" :href="route('members.notifications')" :data="{ category: category.value || undefined, unread: filters.unread ? 1 : undefined }" :class="chip(filters.category === category.value)" :aria-current="filters.category === category.value ? 'true' : undefined" preserve-scroll>{{ category.label }}</Link>
                <Link :href="route('members.notifications')" :data="{ category: filters.category || undefined, unread: filters.unread ? undefined : 1 }" :class="chip(filters.unread)" :aria-current="filters.unread ? 'true' : undefined" preserve-scroll>Unread only</Link>
            </nav>
            <Link :href="route('members.passed_on.choices')" class="inline-block text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Choose what is passed on to you from other lodges</Link>

            <section v-for="group in groups" :key="group.label" :aria-labelledby="`inbox-${group.label}`" class="space-y-2">
                <h3 :id="`inbox-${group.label}`" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ group.label }}</h3>
                <Card padding="none" class="overflow-hidden">
                    <ul>
                        <li v-for="item in group.items" :key="item.id" class="border-b border-slate-200 last:border-b-0 dark:border-slate-800">
                            <Link :href="route('members.notifications.open', { id: item.id })" class="flex gap-3 px-4 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    <UiIcon :name="ICONS[item.category] ?? 'info'" :size="18" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-start gap-2">
                                        <span :class="['min-w-0 flex-1 text-sm text-slate-900 dark:text-white', item.read ? 'font-medium' : 'font-semibold']">
                                            {{ item.title }}<span v-if="!item.read" class="ml-2 inline-block h-2 w-2 rounded-full bg-blue-600 align-middle dark:bg-blue-400" aria-hidden="true" /><span v-if="!item.read" class="sr-only">, unread</span>
                                        </span>
                                        <time :datetime="item.at" class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ when(item.at) }}</time>
                                    </span>
                                    <span class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                        <Badge v-if="item.important" variant="danger">Important</Badge>
                                        <span>{{ categoryLabel(item.category) }}<template v-if="item.club?.name"> · {{ item.club.name }}</template></span>
                                    </span>
                                    <span v-if="item.body" class="mt-1 line-clamp-2 block text-[13px] leading-[18px] text-slate-600 dark:text-slate-300">{{ item.body }}</span>
                                </span>
                            </Link>
                        </li>
                    </ul>
                </Card>
            </section>

            <Card v-if="!notifications.data.length" padding="sm"><p class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">{{ filters.unread || filters.category ? 'Nothing matches those filters.' : "No notifications yet. Summonses, news and notices from your clubs will appear here." }}</p></Card>

            <p v-if="isNativeApp" class="text-center text-xs text-slate-500 dark:text-slate-400">
                <Link :href="route('members.app_settings')" class="inline-flex min-h-11 items-center font-medium text-blue-600 hover:underline dark:text-blue-400">Choose which notifications alert this phone</Link>
            </p>

            <div v-if="notifications.prev_page_url || notifications.next_page_url" class="flex justify-between">
                <Link v-if="notifications.prev_page_url" :href="notifications.prev_page_url" class="inline-flex min-h-11 items-center text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">← Newer</Link><span v-else />
                <Link v-if="notifications.next_page_url" :href="notifications.next_page_url" class="inline-flex min-h-11 items-center text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Older →</Link>
            </div>
        </div>
    </MembersLayout>
</template>
