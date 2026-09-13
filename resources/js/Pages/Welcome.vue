<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    clubs: Array,
    clubTypes: Array,
});

const page = usePage();
const userClubs = computed(() => page.props.auth?.clubs || []);
const currentUser = computed(() => page.props.auth?.user);

const selectedType = ref('all');

const filterClubs = () => {
    if (selectedType.value === 'all') return props.clubs;
    return props.clubs.filter(c => c.type_code === selectedType.value);
};

const getBadgeColor = (typeCode) => {
    switch (typeCode) {
        case 'rowing': return 'bg-sky-500/10 text-sky-400 border-sky-500/20';
        case 'rugby': return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
        case 'tennis': return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
        default: return 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20';
    }
};

const getIcon = (typeCode) => {
    switch (typeCode) {
        case 'rowing': return '🌊';
        case 'rugby': return '🏉';
        case 'tennis': return '🎾';
        default: return '🏆';
    }
};
</script>

<template>
    <Head title="Club Manager Hub - Multi-Tenant Platform" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-indigo-500 selection:text-white">
        <!-- Background Gradient Glows -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl"></div>
            <div class="absolute top-1/3 -right-40 w-96 h-96 bg-sky-600/15 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 left-1/3 w-96 h-96 bg-emerald-600/15 rounded-full blur-3xl"></div>
        </div>

        <!-- Header / Navbar -->
        <header class="sticky top-0 z-50 backdrop-blur-xl bg-slate-950/80 border-b border-slate-800/80">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 via-sky-500 to-emerald-400 flex items-center justify-center text-xl font-bold shadow-lg shadow-indigo-500/20">
                        ⚡
                    </div>
                    <div>
                        <span class="font-extrabold text-xl tracking-tight bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">
                            ClubManager
                        </span>
                        <span class="ml-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            PostgreSQL Powered
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-sm font-medium">
                    <Link href="/clubs/oxford-boating/admin/pages" class="text-slate-300 hover:text-white transition-colors text-xs font-semibold flex items-center gap-1">
                        🎨 CMS Pages
                    </Link>
                    <Link href="/site/oxford-boating" target="_blank" class="text-slate-300 hover:text-white transition-colors text-xs font-semibold flex items-center gap-1">
                        🌐 Live Site
                    </Link>
                    <Link href="/clubs/oxford-boating/admin/subscriptions" class="text-slate-300 hover:text-white transition-colors text-xs font-semibold flex items-center gap-1">
                        💳 Subscriptions
                    </Link>
                    <Link href="/admin/profile" class="text-slate-300 hover:text-white transition-colors text-xs font-semibold flex items-center gap-1">
                        ⚙️ Profile & 2FA
                    </Link>
                    <template v-if="currentUser">
                        <span class="text-xs font-bold text-slate-300 bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-700">
                            👋 {{ currentUser.name }}
                        </span>
                    </template>
                    <template v-else>
                        <Link href="/register" class="px-3 py-1.5 rounded-lg bg-indigo-600/20 text-indigo-300 hover:bg-indigo-600/30 border border-indigo-500/30 text-xs font-semibold transition-all">
                            Register
                        </Link>
                        <Link href="/login" class="px-3.5 py-1.5 rounded-lg bg-gradient-to-r from-indigo-600 to-sky-600 hover:from-indigo-500 hover:to-sky-500 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20">
                            Log In
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="relative z-10 max-w-7xl mx-auto px-6 py-12 space-y-12">
            
            <!-- Logged In User Workspace Hub -->
            <div v-if="currentUser && userClubs.length > 0" class="bg-slate-900/90 border border-indigo-500/30 rounded-3xl p-8 shadow-2xl backdrop-blur-xl space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <h2 class="text-2xl font-black text-white tracking-tight">My Active Workspaces</h2>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">You belong to {{ userClubs.length }} club workspaces across different roles.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="userClub in userClubs"
                        :key="userClub.id"
                        class="bg-slate-950/80 rounded-2xl p-6 border border-slate-800 hover:border-indigo-500/50 transition-all flex flex-col justify-between space-y-6 group"
                    >
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 tracking-wider">
                                    {{ userClub.role }}
                                </span>
                                <span v-if="userClub.member_number" class="text-xs font-mono font-semibold text-slate-400 bg-slate-900 px-2 py-0.5 rounded border border-slate-800">
                                    {{ userClub.member_number }}
                                </span>
                            </div>

                            <h3 class="text-xl font-bold text-white group-hover:text-indigo-400 transition-colors">{{ userClub.name }}</h3>
                            <p class="text-xs text-slate-400">Status: <strong class="text-emerald-400 uppercase tracking-wider">{{ userClub.status }}</strong></p>
                        </div>

                        <div class="pt-4 border-t border-slate-900 flex items-center gap-2">
                            <Link
                                v-if="['admin', 'owner'].includes(userClub.role)"
                                :href="route('admin.analytics', { slug: userClub.slug })"
                                class="flex-1 text-center py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all"
                            >
                                Open Admin Dashboard &rarr;
                            </Link>
                            <Link
                                v-else
                                :href="route('member.dashboard', { slug: userClub.slug })"
                                class="flex-1 text-center py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition-all"
                            >
                                Enter Member Portal &rarr;
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center max-w-3xl mx-auto space-y-6">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-slate-900/80 border border-slate-800 text-xs font-medium text-slate-300 backdrop-blur-md">
                    <span>🚀 Multi-Type Club Management Architecture</span>
                </div>

                <h1 class="text-4xl sm:text-6xl font-black tracking-tight leading-tight">
                    One Platform. <br />
                    <span class="bg-gradient-to-r from-indigo-400 via-sky-400 to-emerald-400 bg-clip-text text-transparent">
                        Tailored for Every Club Type.
                    </span>
                </h1>

                <p class="text-slate-400 text-lg sm:text-xl leading-relaxed">
                    Rowing clubs need boat allocations and erg splits. Rugby clubs need pitch bookings and 15-player lineups. 
                    ClubManager dynamically activates domain-specific feature modules per organization.
                </p>

                <!-- Filter Pills -->
                <div class="pt-6 flex flex-wrap items-center justify-center gap-2">
                    <button 
                        @click="selectedType = 'all'"
                        :class="selectedType === 'all' 
                            ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 border-indigo-500' 
                            : 'bg-slate-900/80 text-slate-400 hover:text-slate-200 border-slate-800 hover:border-slate-700'"
                        class="px-4 py-2 rounded-xl text-sm font-semibold border transition-all duration-200"
                    >
                        All Clubs ({{ clubs.length }})
                    </button>
                    <button 
                        v-for="type in clubTypes" 
                        :key="type.code"
                        @click="selectedType = type.code"
                        :class="selectedType === type.code 
                            ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 border-indigo-500' 
                            : 'bg-slate-900/80 text-slate-400 hover:text-slate-200 border-slate-800 hover:border-slate-700'"
                        class="px-4 py-2 rounded-xl text-sm font-semibold border transition-all duration-200 flex items-center gap-2"
                    >
                        <span>{{ getIcon(type.code) }}</span>
                        <span>{{ type.name }}</span>
                    </button>
                </div>
            </div>

            <!-- Club Cards Grid -->
            <div class="mt-14 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div 
                    v-for="club in filterClubs()" 
                    :key="club.id"
                    class="group relative rounded-2xl bg-slate-900/60 border border-slate-800/80 hover:border-slate-700 p-6 backdrop-blur-md transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-indigo-500/10 flex flex-col justify-between"
                >
                    <div>
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div class="w-12 h-12 rounded-xl bg-slate-800/80 border border-slate-700/60 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                                {{ getIcon(club.type_code) }}
                            </div>
                            <span :class="['px-3 py-1 rounded-full text-xs font-semibold border', getBadgeColor(club.type_code)]">
                                {{ club.type_name }}
                            </span>
                        </div>

                        <h3 class="text-xl font-bold text-slate-100 group-hover:text-indigo-300 transition-colors">
                            {{ club.name }}
                        </h3>
                        <p v-if="club.tagline" class="mt-1 text-xs italic text-slate-400">
                            "{{ club.tagline }}"
                        </p>

                        <!-- Stats row -->
                        <div class="mt-4 py-3 px-4 rounded-xl bg-slate-950/60 border border-slate-800/60 flex items-center justify-between text-xs text-slate-300">
                            <div>
                                <span class="text-slate-400">Active Members:</span> 
                                <span class="font-bold text-white ml-1">{{ club.members_count }}</span>
                            </div>
                            <div class="h-3 w-px bg-slate-800"></div>
                            <div>
                                <span class="text-slate-400">Membership Tiers:</span> 
                                <span class="font-bold text-white ml-1">{{ club.plans_count }}</span>
                            </div>
                        </div>

                        <!-- Active Feature Modules Pills -->
                        <div class="mt-5 space-y-2">
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Enabled Feature Modules:
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span 
                                    v-for="mod in club.enabled_modules" 
                                    :key="mod"
                                    class="px-2.5 py-1 rounded-md bg-slate-800/90 text-slate-300 border border-slate-700/60 text-xs font-mono"
                                >
                                    ⚡ {{ mod }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-4 border-t border-slate-800/80">
                        <Link 
                            :href="`/clubs/${club.slug}`"
                            class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-sky-600 hover:from-indigo-500 hover:to-sky-500 text-white font-semibold text-sm flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20 group/btn transition-all"
                        >
                            <span>Enter Club Hub</span>
                            <span class="group-hover/btn:translate-x-1 transition-transform">→</span>
                        </Link>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
