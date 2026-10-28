<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
    clubs: Array,
    clubTypes: Array,
});

const page = usePage();
const userClubs = computed(() => page.props.auth?.clubs || []);
const currentUser = computed(() => page.props.auth?.user);

const selectedType = ref('all');

const defaultClubSlug = computed(() => {
    return props.clubs && props.clubs.length > 0 ? props.clubs[0].slug : 'lodge-of-fraternity';
});

const filterClubs = () => {
    if (selectedType.value === 'all') return props.clubs;
    return props.clubs.filter(c => c.type_code === selectedType.value);
};
</script>

<template>
    <Head title="Club Manager hub - Multi-tenant platform" />

    <!-- The landing page is always dark, so the kit components take their dark look on it. -->
    <div class="dark min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-blue-500 selection:text-white">
        <!-- Background gradient glows -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -left-40 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl"></div>
            <div class="absolute top-1/3 -right-40 w-96 h-96 bg-blue-600/15 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 left-1/3 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl"></div>
        </div>

        <!-- Header / navbar -->
        <header class="sticky top-0 z-50 backdrop-blur-xl bg-slate-950/80 border-b border-slate-800/80">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-lg shadow-blue-500/20">
                        <UiIcon name="lightning" :size="20" />
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-xl tracking-tight text-white">
                            ClubManager
                        </span>
                        <Badge variant="info">PostgreSQL powered</Badge>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-sm font-medium">
                    <Link :href="`/${defaultClubSlug}/admin/pages`" class="text-slate-300 hover:text-white transition-colors text-xs font-medium flex items-center gap-1">
                        <UiIcon name="palette" class="inline-block align-text-bottom" /> CMS pages
                    </Link>
                    <a :href="`/site/${defaultClubSlug}`" target="_blank" class="text-slate-300 hover:text-white transition-colors text-xs font-medium flex items-center gap-1">
                        <UiIcon name="globe" class="inline-block align-text-bottom" /> Live site
                    </a>
                    <Link :href="`/${defaultClubSlug}/admin/subscriptions`" class="text-slate-300 hover:text-white transition-colors text-xs font-medium flex items-center gap-1">
                        <UiIcon name="cash" class="inline-block align-text-bottom" /> Subscriptions
                    </Link>
                    <Link href="/admin/profile" class="text-slate-300 hover:text-white transition-colors text-xs font-medium flex items-center gap-1">
                        <UiIcon name="settings" class="inline-block align-text-bottom" /> Profile & 2FA
                    </Link>
                    <template v-if="currentUser">
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-300 bg-slate-800 px-3 py-1.5 rounded-full border border-slate-700">
                            <UiIcon name="user" /> {{ currentUser.name }}
                        </span>
                    </template>
                    <template v-else>
                        <Button variant="secondary" size="sm" href="/register">Register</Button>
                        <Button size="sm" href="/login">Log in</Button>
                    </template>
                </div>
            </div>
        </header>

        <!-- Main content -->
        <main class="relative z-10 max-w-7xl mx-auto px-6 py-12 space-y-12">

            <!-- Logged-in user's workspaces -->
            <div v-if="currentUser && userClubs.length > 0" class="bg-slate-900/90 border border-blue-500/30 rounded-2xl p-8 shadow-2xl backdrop-blur-xl space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-6">
                    <div>
                        <h2 class="text-2xl font-semibold text-white tracking-tight">My active workspaces</h2>
                        <p class="text-xs text-slate-400 mt-1">You belong to {{ userClubs.length }} club workspaces across different roles.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="userClub in userClubs"
                        :key="userClub.id"
                        class="bg-slate-950/80 rounded-2xl p-6 border border-slate-800 hover:border-blue-500/50 transition-all flex flex-col justify-between space-y-6 group"
                    >
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Badge variant="info" class="capitalize">{{ userClub.role }}</Badge>
                                <span v-if="userClub.member_number" class="text-xs font-mono font-medium text-slate-400 bg-slate-900 px-2 py-0.5 rounded-full border border-slate-800">
                                    {{ userClub.member_number }}
                                </span>
                            </div>

                            <h3 class="text-xl font-semibold text-white group-hover:text-blue-400 transition-colors">{{ userClub.name }}</h3>
                            <p class="text-xs text-slate-400">Status: <Badge :variant="userClub.status === 'active' ? 'success' : userClub.status === 'pending' ? 'warning' : 'neutral'" class="capitalize">{{ userClub.status }}</Badge></p>
                        </div>

                        <div class="pt-4 border-t border-slate-900 flex items-center gap-2">
                            <Button
                                v-if="['admin', 'owner'].includes(userClub.role)"
                                variant="secondary"
                                block
                                :href="route('admin.analytics', { slug: userClub.slug })"
                            >
                                Open admin dashboard
                            </Button>
                            <Button
                                v-else
                                variant="secondary"
                                block
                                :href="route('member.dashboard', { slug: userClub.slug })"
                            >
                                Enter member portal
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center max-w-3xl mx-auto space-y-6">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-slate-900/80 border border-slate-800 text-xs font-medium text-slate-300 backdrop-blur-md">
                    <UiIcon name="sparkles" class="text-gold-400" />
                    <span>Multi-type club management</span>
                </div>

                <h1 class="text-4xl sm:text-6xl font-semibold tracking-tight leading-tight">
                    One platform. <br />
                    <span class="text-blue-400">
                        Tailored for every club type.
                    </span>
                </h1>

                <p class="text-slate-400 text-lg sm:text-xl leading-relaxed">
                    Rowing clubs need boat allocations and erg splits. Rugby clubs need pitch bookings and 15-player lineups.
                    ClubManager dynamically activates domain-specific feature modules per organization.
                </p>

                <!-- Filter pills -->
                <div class="pt-6 flex flex-wrap items-center justify-center gap-2">
                    <button
                        @click="selectedType = 'all'"
                        :class="selectedType === 'all'
                            ? 'bg-blue-600 text-white border-blue-500'
                            : 'bg-slate-900/80 text-slate-400 hover:text-slate-200 border-slate-800 hover:border-slate-700'"
                        class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors"
                    >
                        All clubs ({{ clubs.length }})
                    </button>
                    <button
                        v-for="type in clubTypes"
                        :key="type.code"
                        @click="selectedType = type.code"
                        :class="selectedType === type.code
                            ? 'bg-blue-600 text-white border-blue-500'
                            : 'bg-slate-900/80 text-slate-400 hover:text-slate-200 border-slate-800 hover:border-slate-700'"
                        class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors"
                    >
                        {{ type.name }}
                    </button>
                </div>
            </div>

            <!-- Club cards grid -->
            <div class="mt-14 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="club in filterClubs()"
                    :key="club.id"
                    class="group relative rounded-2xl bg-slate-900/60 border border-slate-800/80 hover:border-slate-700 p-6 backdrop-blur-md transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-blue-500/10 flex flex-col justify-between"
                >
                    <div>
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div class="w-12 h-12 rounded-xl bg-slate-800/80 border border-slate-700/60 text-slate-300 flex items-center justify-center">
                                <UiIcon name="building" :size="20" />
                            </div>
                            <Badge variant="info">{{ club.type_name }}</Badge>
                        </div>

                        <h3 class="text-xl font-semibold text-slate-100 group-hover:text-blue-300 transition-colors">
                            {{ club.name }}
                        </h3>
                        <p v-if="club.tagline" class="mt-1 text-xs italic text-slate-400">
                            "{{ club.tagline }}"
                        </p>

                        <!-- Stats row -->
                        <div class="mt-4 py-3 px-4 rounded-xl bg-slate-950/60 border border-slate-800/60 flex items-center justify-between text-xs text-slate-300">
                            <div>
                                <span class="text-slate-400">Active members:</span>
                                <span class="font-semibold text-white ml-1">{{ club.members_count }}</span>
                            </div>
                            <div class="h-3 w-px bg-slate-800"></div>
                            <div>
                                <span class="text-slate-400">Membership tiers:</span>
                                <span class="font-semibold text-white ml-1">{{ club.plans_count }}</span>
                            </div>
                        </div>

                        <!-- Enabled feature modules -->
                        <div class="mt-5 space-y-2">
                            <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">
                                Enabled feature modules
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="mod in club.enabled_modules"
                                    :key="mod"
                                    class="px-2.5 py-1 rounded-full bg-slate-800/90 text-slate-300 border border-slate-700/60 text-xs font-mono"
                                >
                                    {{ mod }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-4 border-t border-slate-800/80">
                        <Button variant="secondary" block :href="`/${club.slug}/overview`">
                            Enter club hub
                        </Button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
