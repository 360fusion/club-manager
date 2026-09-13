<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    club: Object,
    event: Object,
    subscribers: Array,
});

const searchQuery = ref('');
const statusFilter = ref('all'); // all, paid, unpaid, dining

const filteredSubscribers = computed(() => {
    return props.subscribers.filter(sub => {
        const query = searchQuery.value.toLowerCase().trim();
        const matchesQuery = !query || 
            (sub.name && sub.name.toLowerCase().includes(query)) ||
            (sub.email && sub.email.toLowerCase().includes(query)) ||
            (sub.home_club_lodge && sub.home_club_lodge.toLowerCase().includes(query)) ||
            (sub.rank && sub.rank.toLowerCase().includes(query));

        if (!matchesQuery) return false;

        if (statusFilter.value === 'paid') return sub.payment_status === 'paid';
        if (statusFilter.value === 'unpaid') return sub.payment_status === 'unpaid' || sub.payment_status === 'pending';
        if (statusFilter.value === 'dining') return sub.has_food_choice;

        return true;
    });
});

const stats = computed(() => {
    const total = props.subscribers.length;
    const paidCount = props.subscribers.filter(s => s.payment_status === 'paid').length;
    const unpaidCount = props.subscribers.filter(s => s.payment_status === 'unpaid' || s.payment_status === 'pending').length;
    const diningCount = props.subscribers.filter(s => s.has_food_choice).length;

    return { total, paidCount, unpaidCount, diningCount };
});

const updatePaymentStatus = (subscriberId, status) => {
    router.post(
        route('admin.events.subscribers.payment_status', {
            clubSlug: props.club.slug,
            id: props.event.id,
            userId: subscriberId,
        }),
        { payment_status: status },
        { preserveScroll: true }
    );
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};
</script>

<template>
    <AdminLayout :club="club">
        <Head :title="`Event Subscriptions - ${event.title}`" />

        <div class="space-y-6">
            <!-- Top Navigation & Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <Link
                        :href="route('admin.events.index', club.slug)"
                        class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-2 transition"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Events
                    </Link>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>👥 Event Subscriptions</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">
                        Viewing attendee roster & RSVPs for <strong class="text-slate-800 dark:text-slate-200">{{ event.title }}</strong>
                    </p>
                </div>
            </div>

            <!-- Event Details Banner & Stats -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                <!-- Event Meta Card -->
                <div class="lg:col-span-1 bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Event Info</h2>
                    <div>
                        <p class="font-medium text-slate-900 dark:text-white text-base">{{ event.title }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1">
                            📅 {{ formatDate(event.start_date) }}
                            <span v-if="event.start_time">at {{ event.start_time }}</span>
                        </p>
                        <p v-if="event.location_name" class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            📍 {{ event.location_name }}
                        </p>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="lg:col-span-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Subscribed</span>
                        <div class="mt-2 flex items-baseline justify-between">
                            <span class="text-3xl font-extrabold text-slate-900 dark:text-white">{{ stats.total }}</span>
                            <span class="text-xs px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-medium">Attendees</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Dining Choices</span>
                        <div class="mt-2 flex items-baseline justify-between">
                            <span class="text-3xl font-extrabold text-slate-900 dark:text-white">{{ stats.diningCount }}</span>
                            <span class="text-xs px-2 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-medium">Dining</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Payment Breakdown</span>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div>
                                <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ stats.paidCount }} Paid</span>
                                <span class="text-slate-400 mx-1">/</span>
                                <span class="text-lg font-semibold text-amber-600 dark:text-amber-400">{{ stats.unpaidCount }} Unpaid</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & Search Toolbar -->
            <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <!-- Tabs -->
                <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 p-1 rounded-lg">
                    <button
                        @click="statusFilter = 'all'"
                        :class="['px-3 py-1.5 text-xs font-medium rounded-md transition', statusFilter === 'all' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']"
                    >
                        All ({{ subscribers.length }})
                    </button>
                    <button
                        @click="statusFilter = 'paid'"
                        :class="['px-3 py-1.5 text-xs font-medium rounded-md transition', statusFilter === 'paid' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']"
                    >
                        Paid ({{ stats.paidCount }})
                    </button>
                    <button
                        @click="statusFilter = 'unpaid'"
                        :class="['px-3 py-1.5 text-xs font-medium rounded-md transition', statusFilter === 'unpaid' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']"
                    >
                        Unpaid ({{ stats.unpaidCount }})
                    </button>
                    <button
                        @click="statusFilter = 'dining'"
                        :class="['px-3 py-1.5 text-xs font-medium rounded-md transition', statusFilter === 'dining' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']"
                    >
                        Dining ({{ stats.diningCount }})
                    </button>
                </div>

                <!-- Search Input -->
                <div class="relative w-full sm:w-72">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search subscriber, email, lodge..."
                        class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <!-- Table of Subscribers -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-3 px-4">Subscriber Details</th>
                                <th class="py-3 px-4">Club / Lodge</th>
                                <th class="py-3 px-4">Ticket Tier</th>
                                <th class="py-3 px-4">Food Choices & Menu</th>
                                <th class="py-3 px-4">Dietary Notes</th>
                                <th class="py-3 px-4">Payment Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
                            <tr
                                v-for="sub in filteredSubscribers"
                                :key="sub.id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition"
                            >
                                <!-- Subscriber Details -->
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-900 dark:text-white text-sm">
                                        {{ sub.name }}
                                        <span v-if="sub.rank" class="text-xs font-normal text-slate-500 dark:text-slate-400 ml-1">
                                            ({{ sub.rank }})
                                        </span>
                                    </div>
                                    <div class="text-slate-500 dark:text-slate-400 text-xs mt-0.5">
                                        {{ sub.email }}
                                    </div>
                                    <div v-if="sub.member_number" class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                        Mem #: {{ sub.member_number }}
                                    </div>
                                </td>

                                <!-- Club / Lodge -->
                                <td class="py-3 px-4">
                                    <span v-if="sub.home_club_lodge" class="font-medium text-slate-700 dark:text-slate-300">
                                        {{ sub.home_club_lodge }}
                                    </span>
                                    <span v-else class="text-slate-400 italic">Not specified</span>
                                </td>

                                <!-- Ticket Tier -->
                                <td class="py-3 px-4">
                                    <div class="font-medium text-slate-800 dark:text-slate-200">
                                        {{ sub.ticket_tier_name }}
                                    </div>
                                    <div class="text-slate-500 text-[11px]">
                                        £{{ Number(sub.ticket_tier_price).toFixed(2) }}
                                    </div>
                                </td>

                                <!-- Food Choices -->
                                <td class="py-3 px-4">
                                    <div v-if="sub.has_food_choice" class="space-y-1">
                                        <div v-if="sub.menu_selections.starter" class="text-slate-700 dark:text-slate-300">
                                            <span class="font-semibold text-slate-400">Starter:</span> {{ sub.menu_selections.starter }}
                                        </div>
                                        <div v-if="sub.menu_selections.main" class="text-slate-700 dark:text-slate-300">
                                            <span class="font-semibold text-slate-400">Main:</span> {{ sub.menu_selections.main }}
                                        </div>
                                        <div v-if="sub.menu_selections.dessert" class="text-slate-700 dark:text-slate-300">
                                            <span class="font-semibold text-slate-400">Dessert:</span> {{ sub.menu_selections.dessert }}
                                        </div>
                                    </div>
                                    <span v-else class="text-slate-400 italic">No food selected / No dining</span>
                                </td>

                                <!-- Dietary Notes -->
                                <td class="py-3 px-4">
                                    <span v-if="sub.dietary_requirements" class="px-2 py-1 rounded bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 font-medium">
                                        ⚠️ {{ sub.dietary_requirements }}
                                    </span>
                                    <span v-else class="text-slate-400 italic">None</span>
                                </td>

                                <!-- Payment Status Action -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <select
                                            :value="sub.payment_status || 'unpaid'"
                                            @change="updatePaymentStatus(sub.user_id, $event.target.value)"
                                            class="text-xs font-semibold rounded-lg px-2.5 py-1 border transition cursor-pointer focus:ring-2 focus:ring-blue-500"
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-700': sub.payment_status === 'paid',
                                                'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-700': sub.payment_status === 'unpaid' || sub.payment_status === 'pending',
                                                'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700': sub.payment_status === 'waived',
                                                'bg-rose-50 text-rose-700 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-700': sub.payment_status === 'refunded'
                                            }"
                                        >
                                            <option value="paid">✅ Paid</option>
                                            <option value="unpaid">⏳ Unpaid</option>
                                            <option value="waived">🤝 Waived</option>
                                            <option value="refunded">↩️ Refunded</option>
                                        </select>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filteredSubscribers.length === 0">
                                <td colspan="6" class="py-8 text-center text-slate-500 dark:text-slate-400">
                                    No subscribers found matching your criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
