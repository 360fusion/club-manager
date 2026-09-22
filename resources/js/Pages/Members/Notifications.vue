<script setup>
import { Link, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';

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
    { value: 'notice', label: 'Notices' },
    { value: 'membership', label: 'Membership' },
    { value: 'signature', label: 'Sign' },
];

const VARIANTS = { summons: 'danger', news: 'info', event: 'success', notice: 'warning', membership: 'neutral', signature: 'info' };

const chip = (active) => ['rounded-full border px-3 py-1 text-xs font-medium transition-colors', active ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300' : 'border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'];

const when = (iso) => new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

const markAllRead = () => router.post(route('members.notifications.read_all'), {}, { preserveScroll: true });
</script>

<template>
    <MembersLayout title="Notifications" active-tab="">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Notifications</h1>
                <Button v-if="unreadCount" size="sm" variant="secondary" @click="markAllRead">Mark all {{ unreadCount }} as read</Button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Link v-for="category in CATEGORIES" :key="category.value" :href="route('members.notifications')" :data="{ category: category.value || undefined, unread: filters.unread ? 1 : undefined }" :class="chip(filters.category === category.value)" preserve-scroll>{{ category.label }}</Link>
                <span class="mx-1 h-4 w-px bg-slate-300 dark:bg-slate-700" aria-hidden="true" />
                <Link :href="route('members.notifications')" :data="{ category: filters.category || undefined, unread: filters.unread ? undefined : 1 }" :class="chip(filters.unread)" preserve-scroll>Unread only</Link>
            </div>

            <Card v-if="notifications.data.length" padding="none">
                <ul>
                    <li v-for="item in notifications.data" :key="item.id" :class="['border-b border-slate-200 last:border-b-0 dark:border-slate-800', item.read ? '' : 'bg-blue-50/50 dark:bg-blue-950/20']">
                        <Link :href="route('members.notifications.open', { id: item.id })" class="flex gap-3 p-4 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <span :class="['mt-2 h-2 w-2 shrink-0 rounded-full', item.read ? 'bg-transparent' : 'bg-blue-600']" aria-hidden="true" />
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                    <Badge :variant="VARIANTS[item.category] ?? 'neutral'">{{ CATEGORIES.find((c) => c.value === item.category)?.label ?? item.category }}</Badge>
                                    <Badge v-if="item.important" variant="danger">Important</Badge>
                                    <span>{{ item.club?.name }}</span>
                                    <span aria-hidden="true">·</span>
                                    <time :datetime="item.at">{{ when(item.at) }}</time>
                                    <span v-if="!item.read" class="sr-only">Unread</span>
                                </span>
                                <span class="mt-1 block text-sm font-semibold text-slate-900 dark:text-white">{{ item.title }}</span>
                                <span v-if="item.body" class="block text-sm text-slate-500 dark:text-slate-400">{{ item.body }}</span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </Card>
            <Card v-else><p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ filters.unread || filters.category ? 'Nothing matches those filters.' : "No notifications yet. Summonses, news and notices from your clubs will appear here." }}</p></Card>

            <div v-if="notifications.prev_page_url || notifications.next_page_url" class="flex justify-between">
                <Link v-if="notifications.prev_page_url" :href="notifications.prev_page_url" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">← Newer</Link><span v-else />
                <Link v-if="notifications.next_page_url" :href="notifications.next_page_url" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Older →</Link>
            </div>
        </div>
    </MembersLayout>
</template>
