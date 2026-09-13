<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    club: Object,
    page: Object,
    navigation: Array,
    latestPosts: Array,
    upcomingEvents: Array,
    membershipPlans: Array,
    donations: Array,
});

const donationForm = useForm({
    donor_name: '',
    donor_email: '',
    amount: 50,
});

const showDonationModal = ref(false);
const activeDonation = ref(null);

const openDonation = (campaign) => {
    activeDonation.value = campaign;
    showDonationModal.value = true;
};

const submitDonation = () => {
    if (activeDonation.value) {
        donationForm.post(`/site/${props.club.slug}/donations/${activeDonation.value.id}`, {
            onSuccess: () => {
                showDonationModal.value = false;
            },
        });
    }
};
</script>

<template>
    <Head :title="`${page.title} - ${club.name}`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-sky-500 selection:text-white">
        <!-- Public Website Header / Nav -->
        <header class="sticky top-0 z-50 backdrop-blur-xl bg-slate-950/85 border-b border-slate-800">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <Link :href="`/site/${club.slug}`" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-sky-500/20 group-hover:scale-105 transition-transform">
                        🏆
                    </div>
                    <div>
                        <div class="font-extrabold text-lg text-white group-hover:text-sky-300 transition-colors">
                            {{ club.name }}
                        </div>
                        <div v-if="club.tagline" class="text-xs text-slate-400">
                            {{ club.tagline }}
                        </div>
                    </div>
                </Link>

                <!-- Navigation Links -->
                <nav class="flex items-center gap-1 sm:gap-2">
                    <Link 
                        v-for="item in navigation" 
                        :key="item.id"
                        :href="item.is_homepage ? `/site/${club.slug}` : `/site/${club.slug}/${item.slug}`"
                        :class="page.slug === item.slug || (page.is_homepage && item.is_homepage) ? 'bg-sky-500/10 text-sky-400 border-sky-500/30' : 'text-slate-400 hover:text-white border-transparent'"
                        class="px-3 py-1.5 rounded-xl text-sm font-semibold border transition-all"
                    >
                        {{ item.title }}
                    </Link>

                    <Link :href="`/clubs/${club.slug}/admin/subscriptions`" class="px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">
                        💳 Subscriptions
                    </Link>

                    <Link href="/admin/profile" class="px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">
                        ⚙️ Profile & 2FA
                    </Link>

                    <Link href="/login" class="px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">
                        Log In
                    </Link>

                    <Link 
                        :href="`/clubs/${club.slug}`"
                        class="ml-2 px-3.5 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-slate-300 hover:text-white text-xs font-semibold"
                    >
                        🔒 Admin Portal
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Dynamic Block Sections Engine -->
        <main class="space-y-16 py-8">
            <div v-for="(block, index) in page.blocks" :key="index" class="max-w-7xl mx-auto px-6">
                <!-- 1. Hero Block -->
                <section v-if="block.type === 'hero'" class="relative p-10 sm:p-16 rounded-3xl bg-gradient-to-r from-slate-900 via-sky-950/40 to-slate-900 border border-slate-800 text-center overflow-hidden">
                    <div class="relative z-10 max-w-3xl mx-auto space-y-6">
                        <div class="inline-block px-3 py-1 rounded-full bg-sky-500/10 border border-sky-500/20 text-sky-400 text-xs font-semibold uppercase tracking-wider">
                            Official Club Website
                        </div>
                        <h1 class="text-4xl sm:text-6xl font-black text-white leading-tight">
                            {{ block.title }}
                        </h1>
                        <p class="text-slate-300 text-lg sm:text-xl">
                            {{ block.subtitle }}
                        </p>
                    </div>
                </section>

                <!-- 2. Donation Campaign Block -->
                <section v-else-if="block.type === 'donation_campaign'" class="space-y-6">
                    <div v-for="d in donations" :key="d.id" class="p-8 sm:p-10 rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950/40 to-slate-900 border border-slate-800 space-y-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase tracking-wider">
                                    🎁 Active Fundraising Campaign
                                </span>
                                <h2 class="text-2xl sm:text-3xl font-black text-white mt-2">{{ d.campaign_name }}</h2>
                                <p class="text-sm text-slate-300 mt-1 max-w-2xl">{{ d.description }}</p>
                            </div>

                            <button @click="openDonation(d)" class="py-3.5 px-6 rounded-2xl bg-gradient-to-r from-amber-500 to-rose-500 hover:from-amber-400 hover:to-rose-400 text-white font-bold text-sm shadow-xl shadow-amber-500/20 hover:scale-105 transition-transform">
                                💖 Make a Contribution
                            </button>
                        </div>

                        <!-- Progress Bar -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-400 font-medium">Raised: <strong class="text-emerald-400 font-bold">£{{ d.current_amount }}</strong></span>
                                <span class="text-slate-400 font-medium">Target: <strong class="text-white font-bold">£{{ d.target_amount }}</strong></span>
                            </div>
                            <div class="w-full h-4 rounded-full bg-slate-950 border border-slate-800 overflow-hidden p-0.5">
                                <div class="h-full rounded-full bg-gradient-to-r from-amber-500 via-emerald-400 to-sky-400 transition-all duration-500" :style="{ width: `${d.percentage}%` }"></div>
                            </div>
                            <div class="text-right text-xs font-bold text-amber-400">
                                {{ d.percentage }}% Funded ({{ d.contributions_count }} Donors)
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 3. Dynamic News & Blog Feed Block -->
                <section v-else-if="block.type === 'news_feed'" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold text-white">{{ block.heading || 'Latest Club News & Articles' }}</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div v-for="post in latestPosts" :key="post.id" class="p-6 rounded-2xl bg-slate-900/70 border border-slate-800 space-y-3">
                            <div class="flex items-center justify-between text-xs text-slate-400">
                                <span>Published by {{ post.author_name }}</span>
                                <span>{{ post.published_at }}</span>
                            </div>
                            <h3 class="text-lg font-bold text-white">{{ post.title }}</h3>
                            <p class="text-sm text-slate-300">{{ post.excerpt || post.content }}</p>
                        </div>
                    </div>
                </section>

                <!-- 4. Dynamic Upcoming Events Block -->
                <section v-else-if="block.type === 'events_calendar'" class="space-y-6">
                    <h2 class="text-2xl font-bold text-white">{{ block.heading || 'Upcoming Events & Formal Dinners' }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div v-for="e in upcomingEvents" :key="e.id" class="p-6 rounded-2xl bg-slate-900/70 border border-slate-800 space-y-4">
                            <h3 class="text-lg font-bold text-white">{{ e.title }}</h3>
                            <p class="text-xs text-slate-400">📍 {{ e.location }} • 🕒 {{ e.starts_at }}</p>
                            <Link :href="`/clubs/${club.slug}`" class="block w-full py-2.5 px-4 rounded-xl bg-slate-800 text-center font-bold text-xs text-slate-200">
                                RSVP & Book Ticket →
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
        </main>

        <!-- Donation Modal -->
        <div v-if="showDonationModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-6 shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-800 pb-4">
                    <div>
                        <h3 class="text-xl font-bold text-white">Make a Donation</h3>
                        <p class="text-xs text-amber-400">{{ activeDonation?.campaign_name }}</p>
                    </div>
                    <button @click="showDonationModal = false" class="text-slate-400 hover:text-white font-bold">✕</button>
                </div>

                <form @submit.prevent="submitDonation" class="space-y-4 text-sm">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Your Full Name</label>
                        <input v-model="donationForm.donor_name" type="text" required placeholder="e.g. Lord Edward Spencer" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Email Address</label>
                        <input v-model="donationForm.donor_email" type="email" required placeholder="e.g. edward@spencer.co.uk" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Donation Amount (£)</label>
                        <input v-model="donationForm.amount" type="number" min="1" required class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white font-bold text-lg" />
                    </div>

                    <button type="submit" :disabled="donationForm.processing" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-rose-500 text-white font-bold text-sm shadow-lg shadow-amber-500/20">
                        Confirm & Process Donation
                    </button>
                </form>
            </div>
        </div>

        <!-- Public Footer -->
        <footer class="mt-20 border-t border-slate-800 py-10 bg-slate-950 text-center text-xs text-slate-500 space-y-2">
            <p>© 2026 {{ club.name }}. All rights reserved.</p>
            <p>Powered by <strong class="text-slate-400">ClubManager Multi-Tenant Platform</strong></p>
        </footer>
    </div>
</template>
