<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import ObfuscatedEmail from '@/Components/ObfuscatedEmail.vue';

const props = defineProps({
    club: Object,
    page: Object,
    navigation: Array,
    latestPosts: Array,
    upcomingEvents: Array,
    membershipPlans: Array,
    donations: Array,
    previewTheme: String,
});

const currentThemeKey = computed(() => props.previewTheme || props.club?.settings?.website_theme || 'classic');

const themeClasses = computed(() => {
    const key = currentThemeKey.value;
    if (key === 'light_navy') {
        return {
            wrapper: 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-sans selection:bg-blue-500 selection:text-white',
            header: 'sticky top-0 z-50 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200/90 dark:border-slate-800/90 shadow-sm',
            navActive: 'bg-slate-900 dark:bg-slate-700 text-white font-bold shadow-md shadow-slate-900/10',
            navInactive: 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border-transparent',
            heroBg: 'bg-gradient-to-br from-slate-900 via-slate-800 to-blue-950 text-white border border-slate-800 shadow-xl',
            heroPill: 'bg-blue-500/20 border-blue-400/30 text-blue-300 font-semibold',
            heroCta: 'bg-blue-500 hover:bg-blue-600 text-white font-bold shadow-blue-500/25',
            cardBg: 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white shadow-sm hover:shadow-md transition-shadow',
            accentText: 'text-blue-600 dark:text-blue-400',
            accentBg: 'bg-blue-600',
            footer: 'bg-slate-900 dark:bg-slate-700 border-t border-slate-800 text-slate-400',
        };
    }
    if (key === 'executive_light') {
        return {
            wrapper: 'bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white font-sans selection:bg-blue-600 selection:text-white',
            header: 'sticky top-0 z-50 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800/80 shadow-sm',
            navActive: 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60 font-bold shadow-sm',
            navInactive: 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border-transparent',
            heroBg: 'bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800/90 shadow-md text-slate-900 dark:text-white',
            heroPill: 'bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-blue-700 dark:text-blue-300 font-semibold',
            heroCta: 'bg-gradient-to-r from-blue-600 to-blue-600 text-white font-bold shadow-blue-600/20',
            cardBg: 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white shadow-sm hover:border-blue-200 dark:hover:border-blue-800/60 transition-colors',
            accentText: 'text-blue-600 dark:text-blue-400',
            accentBg: 'bg-blue-600',
            footer: 'bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300',
        };
    }
    if (key === 'masonic_light') {
        return {
            wrapper: 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-serif selection:bg-amber-500 selection:text-slate-950 dark:selection:text-white',
            header: 'sticky top-0 z-50 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b-2 border-amber-500/40 shadow-sm',
            navActive: 'bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 border-amber-300 dark:border-amber-700/60 font-sans font-bold shadow-sm',
            navInactive: 'text-slate-700 dark:text-slate-200 hover:text-amber-800 dark:hover:text-amber-200 font-sans border-transparent',
            heroBg: 'bg-gradient-to-br from-blue-950 via-blue-950 to-slate-950 text-white border-2 border-amber-500/40 shadow-xl',
            heroPill: 'bg-amber-500/20 border-amber-400/40 text-amber-300 font-sans tracking-widest',
            heroCta: 'bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 dark:text-white font-sans font-black shadow-amber-500/30',
            cardBg: 'bg-white dark:bg-slate-900 border border-amber-500/30 text-slate-900 dark:text-white shadow-sm hover:border-amber-500/50 transition-colors',
            accentText: 'text-amber-700 dark:text-amber-300',
            accentBg: 'bg-amber-600',
            footer: 'bg-slate-900 dark:bg-slate-700 border-t-2 border-amber-500/40 text-slate-400 font-sans',
        };
    }
    if (key === 'warm_light') {
        return {
            wrapper: 'bg-amber-50/30 dark:bg-amber-950/30 text-slate-900 dark:text-white font-sans selection:bg-amber-500 selection:text-white',
            header: 'sticky top-0 z-50 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-amber-200/60 dark:border-amber-800/60 shadow-sm',
            navActive: 'bg-amber-100/70 dark:bg-amber-900/70 text-amber-900 dark:text-amber-200 border-amber-300 dark:border-amber-700/60 font-bold',
            navInactive: 'text-slate-600 dark:text-slate-300 hover:text-amber-900 dark:hover:text-amber-200 border-transparent',
            heroBg: 'bg-white dark:bg-slate-900 border border-amber-200/80 dark:border-amber-800/80 shadow-sm text-slate-900 dark:text-white',
            heroPill: 'bg-amber-100/60 dark:bg-amber-900/60 border border-amber-300/60 dark:border-amber-700/60 text-amber-800 dark:text-amber-200 font-semibold',
            heroCta: 'bg-gradient-to-r from-amber-600 to-orange-600 text-white font-bold shadow-amber-600/20',
            cardBg: 'bg-white dark:bg-slate-900 border border-amber-200/80 dark:border-amber-800/80 text-slate-900 dark:text-white shadow-sm hover:border-amber-300 dark:hover:border-amber-700/60 transition-colors',
            accentText: 'text-amber-700 dark:text-amber-300',
            accentBg: 'bg-amber-600',
            footer: 'bg-white dark:bg-slate-900 border-t border-amber-200/60 dark:border-amber-800/60 text-slate-600 dark:text-slate-300',
        };
    }
    if (key === 'obsidian') {
        return {
            wrapper: 'bg-slate-950 text-slate-100 font-sans selection:bg-blue-500 selection:text-white',
            header: 'sticky top-0 z-50 backdrop-blur-xl bg-slate-950/90 border-b border-slate-800',
            navActive: 'bg-blue-500/20 text-blue-400 border-blue-500/40 shadow-sm shadow-blue-500/10',
            navInactive: 'text-slate-400 hover:text-white border-transparent',
            heroBg: 'bg-gradient-to-r from-slate-950 via-slate-900 to-slate-950 border border-slate-800/80 shadow-2xl',
            heroPill: 'bg-blue-500/10 border-blue-500/30 text-blue-400',
            heroCta: 'bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-blue-500/30',
            cardBg: 'bg-slate-900/90 border border-slate-800 text-slate-100 shadow-xl',
            accentText: 'text-blue-400',
            accentBg: 'bg-blue-500',
            footer: 'bg-slate-950 border-t border-slate-900 text-slate-500',
        };
    }
    if (key === 'masonic') {
        return {
            wrapper: 'bg-slate-950 text-slate-100 font-serif selection:bg-amber-500 selection:text-slate-950',
            header: 'sticky top-0 z-50 backdrop-blur-xl bg-blue-950/90 border-b border-amber-500/30 shadow-md',
            navActive: 'bg-amber-500/20 text-amber-300 border-amber-500/50 shadow-sm',
            navInactive: 'text-slate-300 hover:text-amber-300 border-transparent',
            heroBg: 'bg-gradient-to-br from-blue-950 via-slate-950 to-blue-950 border-2 border-amber-500/30 shadow-2xl',
            heroPill: 'bg-amber-500/10 border-amber-500/30 text-amber-400 font-sans tracking-widest',
            heroCta: 'bg-gradient-to-r from-amber-600 to-amber-500 text-slate-950 dark:text-white font-black shadow-amber-500/20',
            cardBg: 'bg-blue-950/60 border border-amber-500/25 text-slate-100 shadow-xl',
            accentText: 'text-amber-400',
            accentBg: 'bg-amber-500',
            footer: 'bg-blue-950 border-t border-amber-500/20 text-slate-400 font-sans',
        };
    }
    if (key === 'minimal') {
        return {
            wrapper: 'bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white font-sans selection:bg-emerald-500 selection:text-white',
            header: 'sticky top-0 z-50 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 shadow-sm',
            navActive: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700/60 font-bold',
            navInactive: 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border-transparent',
            heroBg: 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm text-slate-900 dark:text-white',
            heroPill: 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-300',
            heroCta: 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/20',
            cardBg: 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white shadow-sm',
            accentText: 'text-emerald-600 dark:text-emerald-400',
            accentBg: 'bg-emerald-600',
            footer: 'bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300',
        };
    }
    if (key === 'vibrant') {
        return {
            wrapper: 'bg-slate-900 dark:bg-slate-700 text-slate-100 font-sans selection:bg-rose-500 selection:text-white',
            header: 'sticky top-0 z-50 backdrop-blur-xl bg-slate-900/90 border-b border-rose-500/20',
            navActive: 'bg-rose-500/20 text-rose-300 border-rose-500/40 shadow-sm',
            navInactive: 'text-slate-300 hover:text-rose-300 border-transparent',
            heroBg: 'bg-gradient-to-r from-rose-900 via-amber-900 to-blue-950 border border-rose-500/30 shadow-2xl',
            heroPill: 'bg-rose-500/20 border-rose-500/40 text-rose-300',
            heroCta: 'bg-gradient-to-r from-rose-500 via-amber-500 to-rose-600 text-white shadow-rose-500/30',
            cardBg: 'bg-slate-950/80 border border-slate-800 text-slate-100 shadow-xl',
            accentText: 'text-rose-400',
            accentBg: 'bg-rose-500',
            footer: 'bg-slate-950 border-t border-slate-800 text-slate-400',
        };
    }
    // Default: 'classic'
    return {
        wrapper: 'bg-slate-950 text-slate-100 font-sans selection:bg-blue-500 selection:text-white',
        header: 'sticky top-0 z-50 backdrop-blur-xl bg-slate-950/85 border-b border-slate-800',
        navActive: 'bg-blue-500/10 text-blue-400 border-blue-500/30',
        navInactive: 'text-slate-400 hover:text-white border-transparent',
        heroBg: 'bg-gradient-to-r from-slate-900 via-blue-950/40 to-slate-900 border border-slate-800',
        heroPill: 'bg-blue-500/10 border-blue-500/20 text-blue-400',
        heroCta: 'bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-blue-500/20',
        cardBg: 'bg-slate-900 dark:bg-slate-700 border border-slate-800 text-slate-100 shadow-xl',
        accentText: 'text-blue-400',
        accentBg: 'bg-blue-500',
        footer: 'bg-slate-950 border-t border-slate-800 text-slate-400',
    };
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

const contactForm = useForm({
    name: '',
    email: '',
    phone: '',
    message: '',
    recipient_email: '',
    cc_emails: '',
    success_message: '',
});

const contactSuccessMap = ref({});

const submitPublicContactForm = (block) => {
    contactForm.recipient_email = block.recipient_email || props.club.contact_email || props.club.email || '';
    contactForm.cc_emails = block.cc_emails || '';
    contactForm.success_message = block.success_message || 'Thank you! Your message has been sent successfully.';

    contactForm.post(`/site/${props.club.slug}/contact-form`, {
        preserveScroll: true,
        onSuccess: () => {
            contactSuccessMap.value[block.id] = block.success_message || 'Thank you! Your message has been sent successfully.';
            contactForm.reset('name', 'email', 'phone', 'message');
        },
    });
};

const newsBlockPages = ref({});

const getNewsCurrentPage = (blockId) => {
    return newsBlockPages.value[blockId] || 1;
};

const setNewsPage = (blockId, pageNum) => {
    newsBlockPages.value[blockId] = pageNum;
};

const getNewsTotalPages = (block) => {
    if (!props.latestPosts || !props.latestPosts.length) return 1;
    const perPage = parseInt(block.limit) || 999;
    return Math.ceil(props.latestPosts.length / perPage);
};

const getNewsGridClass = (block) => {
    const cols = String(block.columns || '3');
    if (cols === '1') return 'grid grid-cols-1 gap-6';
    if (cols === '2') return 'grid grid-cols-1 md:grid-cols-2 gap-6';
    if (cols === '3') return 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6';
    if (cols === '4') return 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6';
    if (cols === 'masonry') return 'columns-1 md:columns-2 lg:columns-3 gap-6 space-y-6';
    return 'grid grid-cols-1 md:grid-cols-3 gap-6';
};

const getFilteredPosts = (block) => {
    if (!props.latestPosts) return [];
    const perPage = parseInt(block.limit) || 999;
    const page = getNewsCurrentPage(block.id);
    const start = (page - 1) * perPage;
    return props.latestPosts.slice(start, start + perPage);
};

const getPostImagePos = (block, idx) => {
    const pos = String(block.image_position || 'above');
    if (pos === 'alternate') {
        return idx % 2 === 0 ? 'left' : 'right';
    }
    return pos;
};

const getSiteUrl = (urlPath) => {
    if (!urlPath) return '';
    if (!props.previewTheme) return urlPath;
    if (urlPath.startsWith('/site/') || urlPath.includes('/site/')) {
        const separator = urlPath.includes('?') ? '&' : '?';
        return `${urlPath}${separator}preview_theme=${encodeURIComponent(props.previewTheme)}`;
    }
    return urlPath;
};
</script>

<template>
    <Head :title="`${page.title} - ${club.name}`" />

    <div :class="['min-h-screen transition-colors duration-300', themeClasses.wrapper]">
        <!-- Theme Preview Top Bar -->
        <div v-if="previewTheme" class="bg-amber-400 text-amber-950 px-4 py-2.5 text-center text-xs font-bold sticky top-0 z-[100] flex items-center justify-center gap-3 shadow-md border-b border-amber-500/30">
            <span>👁️ Theme Preview Mode: <strong>{{ currentThemeKey }}</strong></span>
            <span class="text-[11px] opacity-80">(This theme is currently being previewed and is not applied live to visitors.)</span>
            <Link :href="`/${club.slug}/admin/pages`" class="ml-2 px-2.5 py-1 rounded bg-amber-950 text-white font-bold hover:bg-black transition-colors text-[11px]">
                ⚙️ Back to Website Builder
            </Link>
        </div>

        <!-- Public Website Header / Nav -->
        <header :class="themeClasses.header">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <Link :href="getSiteUrl(`/site/${club.slug}`)" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-500 to-blue-600 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-500/20 group-hover:scale-105 transition-transform">
                        🏆
                    </div>
                    <div>
                        <div class="font-extrabold text-lg transition-colors">
                            {{ club.name }}
                        </div>
                        <div v-if="club.tagline" class="text-xs opacity-75">
                            {{ club.tagline }}
                        </div>
                    </div>
                </Link>

                <!-- Navigation Links -->
                <nav class="flex items-center gap-1 sm:gap-2">
                    <Link 
                        v-for="item in navigation" 
                        :key="item.id"
                        :href="getSiteUrl(item.is_homepage ? `/site/${club.slug}` : `/site/${club.slug}/${item.slug}`)"
                        :class="page.slug === item.slug || (page.is_homepage && item.is_homepage) ? themeClasses.navActive : themeClasses.navInactive"
                        class="px-3 py-1.5 rounded-xl text-sm font-semibold border transition-all"
                    >
                        {{ item.title }}
                    </Link>

                    <Link :href="`/${club.slug}/admin/subscriptions`" class="px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">
                        💳 Subscriptions
                    </Link>

                    <Link href="/admin/profile" class="px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">
                        ⚙️ Profile & 2FA
                    </Link>

                    <Link href="/login" class="px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">
                        Log In
                    </Link>

                    <Link 
                        :href="`/${club.slug}/overview`"
                        class="ml-2 px-3.5 py-1.5 rounded-xl bg-slate-900 dark:bg-slate-700 border border-slate-800 text-slate-300 hover:text-white text-xs font-semibold"
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
                <section v-if="block.type === 'hero'" class="relative p-10 sm:p-16 rounded-3xl bg-gradient-to-r from-slate-900 via-blue-950/40 to-slate-900 border border-slate-800 text-center overflow-hidden">
                    <div class="relative z-10 max-w-3xl mx-auto space-y-6">
                        <div class="inline-block px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-semibold uppercase tracking-wider">
                            Official Club Website
                        </div>
                        <h1 class="text-4xl sm:text-6xl font-black text-white leading-tight">
                            {{ block.title }}
                        </h1>
                        <p v-if="block.subtitle" class="text-slate-300 text-lg sm:text-xl">
                            {{ block.subtitle }}
                        </p>
                        <div v-if="block.cta_text && block.cta_link" class="pt-2">
                            <a :href="getSiteUrl(block.cta_link)" class="inline-block py-3.5 px-7 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 text-white font-bold text-sm shadow-xl shadow-blue-500/20 hover:scale-105 transition-transform">
                                {{ block.cta_text }}
                            </a>
                        </div>
                    </div>
                </section>

                <!-- 2. Text / Rich Text Block -->
                <section v-else-if="block.type === 'text' || block.type === 'rich_text'" class="prose prose-invert max-w-4xl mx-auto text-slate-200">
                    <h2 v-if="block.heading" class="text-2xl sm:text-3xl font-bold text-white mb-4">{{ block.heading }}</h2>
                    <div v-html="block.content"></div>
                </section>

                <!-- 3. Single Image Block -->
                <section v-else-if="block.type === 'image' && block.url" class="max-w-5xl mx-auto">
                    <div :class="['flex', block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : 'justify-center']">
                        <figure :class="[
                            'space-y-2',
                            block.size === 'small' ? 'w-full sm:w-1/3' : block.size === 'medium' ? 'w-full sm:w-1/2' : block.size === 'large' ? 'w-full sm:w-3/4' : 'w-full'
                        ]">
                            <img :src="block.url" :alt="block.caption || 'Image'" class="w-full h-auto rounded-2xl border border-slate-800 shadow-xl object-cover" />
                            <figcaption v-if="block.caption" class="text-xs text-center text-slate-400 italic">
                                {{ block.caption }}
                            </figcaption>
                        </figure>
                    </div>
                </section>

                <!-- 4. Image Gallery Block -->
                <section v-else-if="block.type === 'images' && block.items && block.items.length" class="max-w-6xl mx-auto space-y-4">
                    <div :class="[
                        'grid gap-4',
                        block.columns === 2 ? 'grid-cols-1 sm:grid-cols-2' :
                        block.columns === 4 ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4' :
                        'grid-cols-1 sm:grid-cols-3'
                    ]">
                        <div v-for="(item, iIdx) in block.items" :key="iIdx" class="space-y-2">
                            <img v-if="item.url" :src="item.url" :alt="item.caption || 'Gallery Image'" class="w-full h-48 sm:h-56 object-cover rounded-2xl border border-slate-800 shadow-md" />
                            <p v-if="item.caption" class="text-xs text-center text-slate-400 italic">{{ item.caption }}</p>
                        </div>
                    </div>
                </section>

                <!-- 5. Callout Box / Notice Block -->
                <section v-else-if="block.type === 'notice'" class="max-w-4xl mx-auto">
                    <div :class="[
                        'p-6 rounded-2xl border space-y-2',
                        block.style === 'warning' ? 'bg-amber-500/10 border-amber-500/30 text-amber-200' :
                        block.style === 'important' ? 'bg-rose-500/10 border-rose-500/30 text-rose-200' :
                        block.style === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-200' :
                        'bg-blue-500/10 border-blue-500/30 text-blue-200'
                    ]">
                        <h4 v-if="block.title" class="font-extrabold text-base flex items-center gap-2">
                            <span>📢</span> {{ block.title }}
                        </h4>
                        <p class="text-sm font-medium leading-relaxed">{{ block.text }}</p>
                    </div>
                </section>

                <!-- 6. Button Link Block -->
                <section v-else-if="block.type === 'button' && block.url" class="max-w-4xl mx-auto">
                    <div :class="['flex', block.align === 'left' ? 'justify-start' : block.align === 'right' ? 'justify-end' : 'justify-center']">
                        <a :href="getSiteUrl(block.url)" class="py-3 px-6 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-400 hover:to-blue-500 text-white font-bold text-sm shadow-lg shadow-blue-500/20 transition-all hover:scale-105">
                            {{ block.label || 'Learn More →' }}
                        </a>
                    </div>
                </section>

                <!-- 7. Dynamic Membership Pricing Cards Block -->
                <section v-else-if="block.type === 'pricing_cards'" class="max-w-6xl mx-auto space-y-6">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-white text-center">{{ block.heading || 'Membership Options & Dues' }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div v-for="plan in membershipPlans" :key="plan.id" class="p-6 rounded-3xl bg-slate-900 dark:bg-slate-700 border border-slate-800 space-y-4 flex flex-col justify-between">
                            <div>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-500/10 text-blue-400 border border-blue-500/20 uppercase tracking-wider">
                                    {{ plan.billing_period }}
                                </span>
                                <h3 class="text-xl font-bold text-white mt-2">{{ plan.name }}</h3>
                                <p class="text-xs text-slate-300 mt-1">{{ plan.description }}</p>
                            </div>
                            <div class="pt-4 border-t border-slate-800 flex items-baseline justify-between">
                                <div>
                                    <span class="text-2xl font-black text-white">{{ $cs }}{{ plan.price }}</span>
                                    <span class="text-xs text-slate-400"> / {{ plan.billing_period }}</span>
                                </div>
                                <Link :href="`/${club.slug}/admin/subscriptions`" class="py-2 px-4 rounded-xl bg-blue-600 text-white font-bold text-xs hover:bg-blue-500">
                                    Subscribe →
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 8. Dynamic Donation Campaign Block -->
                <section v-else-if="block.type === 'donation_campaign'" class="space-y-6">
                    <div v-for="d in donations" :key="d.id" class="p-8 sm:p-10 rounded-3xl bg-gradient-to-r from-slate-900 via-blue-950/40 to-slate-900 border border-slate-800 space-y-6">
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
                                <span class="text-slate-400 font-medium">Raised: <strong class="text-emerald-400 font-bold">{{ $cs }}{{ d.current_amount }}</strong></span>
                                <span class="text-slate-400 font-medium">Target: <strong class="text-white font-bold">{{ $cs }}{{ d.target_amount }}</strong></span>
                            </div>
                            <div class="w-full h-4 rounded-full bg-slate-950 border border-slate-800 overflow-hidden p-0.5">
                                <div class="h-full rounded-full bg-gradient-to-r from-amber-500 via-emerald-400 to-blue-400 transition-all duration-500" :style="{ width: `${d.percentage}%` }"></div>
                            </div>
                            <div class="text-right text-xs font-bold text-amber-400">
                                {{ d.percentage }}% Funded ({{ d.contributions_count }} Donors)
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 9. Dynamic News & Blog Feed / News List Block -->
                <section v-else-if="block.type === 'news_feed' || block.type === 'news_list'" class="space-y-6">
                    <div v-if="block.heading" class="flex items-center justify-between">
                        <h2 class="text-2xl sm:text-3xl font-bold text-white">{{ block.heading }}</h2>
                    </div>

                    <div :class="getNewsGridClass(block)">
                        <template v-for="(post, pIdx) in getFilteredPosts(block)" :key="post.id">
                            
                            <!-- No Image Layout (Clean text-only card, no placeholder) -->
                            <div v-if="!post.cover_image_url" :class="['rounded-2xl bg-slate-900/70 border border-slate-800 p-6 space-y-3 flex flex-col justify-between transition-all hover:border-slate-700', String(block.columns) === 'masonry' ? 'break-inside-avoid inline-block w-full mb-6' : '']">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-xs text-slate-400">
                                        <span v-if="post.author_name">Published by {{ post.author_name }}</span>
                                        <span>{{ post.published_at }}</span>
                                    </div>
                                    <h3 class="text-lg font-bold text-white leading-snug">{{ post.title }}</h3>
                                    <p class="text-sm text-slate-300 leading-relaxed line-clamp-3">{{ post.excerpt || post.content }}</p>
                                </div>
                            </div>

                            <!-- Image Left Layout -->
                            <div v-else-if="getPostImagePos(block, pIdx) === 'left'" :class="['rounded-2xl bg-slate-900/70 border border-slate-800 overflow-hidden grid grid-cols-1 sm:grid-cols-3 gap-0 transition-all hover:border-slate-700', String(block.columns) === 'masonry' ? 'break-inside-avoid inline-block w-full mb-6' : '']">
                                <div class="sm:col-span-1 min-h-[160px] bg-slate-800 overflow-hidden relative">
                                    <img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" />
                                </div>
                                <div class="sm:col-span-2 p-6 space-y-3 flex flex-col justify-between">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between text-xs text-slate-400">
                                            <span v-if="post.author_name">Published by {{ post.author_name }}</span>
                                            <span>{{ post.published_at }}</span>
                                        </div>
                                        <h3 class="text-lg font-bold text-white leading-snug">{{ post.title }}</h3>
                                        <p class="text-sm text-slate-300 leading-relaxed line-clamp-3">{{ post.excerpt || post.content }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Image Right Layout -->
                            <div v-else-if="getPostImagePos(block, pIdx) === 'right'" :class="['rounded-2xl bg-slate-900/70 border border-slate-800 overflow-hidden grid grid-cols-1 sm:grid-cols-3 gap-0 transition-all hover:border-slate-700', String(block.columns) === 'masonry' ? 'break-inside-avoid inline-block w-full mb-6' : '']">
                                <div class="sm:col-span-2 p-6 space-y-3 flex flex-col justify-between order-2 sm:order-1">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between text-xs text-slate-400">
                                            <span v-if="post.author_name">Published by {{ post.author_name }}</span>
                                            <span>{{ post.published_at }}</span>
                                        </div>
                                        <h3 class="text-lg font-bold text-white leading-snug">{{ post.title }}</h3>
                                        <p class="text-sm text-slate-300 leading-relaxed line-clamp-3">{{ post.excerpt || post.content }}</p>
                                    </div>
                                </div>
                                <div class="sm:col-span-1 min-h-[160px] bg-slate-800 overflow-hidden relative order-1 sm:order-2">
                                    <img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" />
                                </div>
                            </div>

                            <!-- Image Below Layout -->
                            <div v-else-if="getPostImagePos(block, pIdx) === 'below'" :class="['rounded-2xl bg-slate-900/70 border border-slate-800 overflow-hidden flex flex-col justify-between transition-all hover:border-slate-700', String(block.columns) === 'masonry' ? 'break-inside-avoid inline-block w-full mb-6' : '']">
                                <div class="p-6 space-y-3">
                                    <div class="flex items-center justify-between text-xs text-slate-400">
                                        <span v-if="post.author_name">Published by {{ post.author_name }}</span>
                                        <span>{{ post.published_at }}</span>
                                    </div>
                                    <h3 class="text-lg font-bold text-white leading-snug">{{ post.title }}</h3>
                                    <p class="text-sm text-slate-300 leading-relaxed line-clamp-3">{{ post.excerpt || post.content }}</p>
                                </div>
                                <div class="h-48 w-full bg-slate-800 overflow-hidden relative border-t border-slate-800">
                                    <img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" />
                                </div>
                            </div>

                            <!-- Image Above Layout (Default) -->
                            <div v-else :class="['rounded-2xl bg-slate-900/70 border border-slate-800 overflow-hidden flex flex-col justify-between transition-all hover:border-slate-700', String(block.columns) === 'masonry' ? 'break-inside-avoid inline-block w-full mb-6' : '']">
                                <div class="h-48 w-full bg-slate-800 overflow-hidden relative border-b border-slate-800">
                                    <img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" />
                                </div>
                                <div class="p-6 space-y-3">
                                    <div class="flex items-center justify-between text-xs text-slate-400">
                                        <span v-if="post.author_name">Published by {{ post.author_name }}</span>
                                        <span>{{ post.published_at }}</span>
                                    </div>
                                    <h3 class="text-lg font-bold text-white leading-snug">{{ post.title }}</h3>
                                    <p class="text-sm text-slate-300 leading-relaxed line-clamp-3">{{ post.excerpt || post.content }}</p>
                                </div>
                            </div>

                        </template>
                    </div>

                    <!-- Pagination Bar -->
                    <div v-if="getNewsTotalPages(block) > 1" class="flex items-center justify-between pt-6 border-t border-slate-800">
                        <button
                            type="button"
                            @click="setNewsPage(block.id, getNewsCurrentPage(block.id) - 1)"
                            :disabled="getNewsCurrentPage(block.id) <= 1"
                            class="py-2.5 px-4 rounded-xl bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 disabled:opacity-40 text-xs font-bold text-slate-200 border border-slate-800 flex items-center gap-1.5 cursor-pointer disabled:cursor-not-allowed transition-all"
                        >
                            ← Previous Page
                        </button>

                        <span class="text-xs font-bold text-slate-400">
                            Page <span class="text-white font-black">{{ getNewsCurrentPage(block.id) }}</span> of {{ getNewsTotalPages(block) }}
                        </span>

                        <button
                            type="button"
                            @click="setNewsPage(block.id, getNewsCurrentPage(block.id) + 1)"
                            :disabled="getNewsCurrentPage(block.id) >= getNewsTotalPages(block)"
                            class="py-2.5 px-4 rounded-xl bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 disabled:opacity-40 text-xs font-bold text-slate-200 border border-slate-800 flex items-center gap-1.5 cursor-pointer disabled:cursor-not-allowed transition-all"
                        >
                            Next Page →
                        </button>
                    </div>
                </section>

                <!-- 10. Dynamic Upcoming Events Block -->
                <section v-else-if="block.type === 'events_calendar'" class="space-y-6">
                    <h2 class="text-2xl font-bold text-white">{{ block.heading || 'Upcoming Events & Formal Dinners' }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div v-for="e in upcomingEvents" :key="e.id" class="p-6 rounded-2xl bg-slate-900/70 border border-slate-800 space-y-4">
                            <h3 class="text-lg font-bold text-white">{{ e.title }}</h3>
                            <p class="text-xs text-slate-400">📍 {{ e.location }} • 🕒 {{ e.starts_at }}</p>
                            <Link :href="`/${club.slug}/overview`" class="block w-full py-2.5 px-4 rounded-xl bg-slate-800 text-center font-bold text-xs text-slate-200">
                                RSVP & Book Ticket →
                            </Link>
                        </div>
                    </div>
                </section>

                <!-- 11. Contact Details & Cards Block -->
                <section v-else-if="block.type === 'contact_details'" class="py-8 space-y-10 text-center">
                    <div class="max-w-3xl mx-auto space-y-3">
                        <span v-if="block.eyebrow" class="text-xs font-bold text-amber-500 uppercase tracking-widest block">{{ block.eyebrow }}</span>
                        <h2 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight">{{ block.title || 'Get in Touch' }}</h2>
                        <div class="w-12 h-1 bg-amber-500/80 mx-auto my-4 rounded-full"></div>
                        <p v-if="block.description" class="text-sm sm:text-base text-slate-300 leading-relaxed max-w-2xl mx-auto">{{ block.description }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                        <!-- Email Card -->
                        <div class="p-8 rounded-2xl bg-amber-50/10 dark:bg-amber-950/10 border border-amber-500/20 text-center space-y-4 hover:border-amber-500/40 transition-all">
                            <div class="w-14 h-14 rounded-full bg-slate-900 dark:bg-slate-700 border border-amber-500/30 text-amber-400 flex items-center justify-center mx-auto text-xl shadow-lg">
                                ✉️
                            </div>
                            <h3 class="font-bold text-white text-lg">{{ block.email_heading || 'Email' }}</h3>
                            <ObfuscatedEmail
                                v-if="block.email || club.contact_email"
                                :email="block.email || club.contact_email"
                                custom-class="text-amber-400 hover:text-amber-300 font-semibold text-sm sm:text-base break-all cursor-pointer transition-colors"
                            />
                            <span v-else class="text-slate-500 dark:text-slate-400 text-xs italic">No email address configured</span>
                        </div>

                        <!-- Meeting Times Card -->
                        <div class="p-8 rounded-2xl bg-amber-50/10 dark:bg-amber-950/10 border border-amber-500/20 text-center space-y-4 hover:border-amber-500/40 transition-all">
                            <div class="w-14 h-14 rounded-full bg-slate-900 dark:bg-slate-700 border border-amber-500/30 text-amber-400 flex items-center justify-center mx-auto text-xl shadow-lg">
                                📅
                            </div>
                            <h3 class="font-bold text-white text-lg">{{ block.times_heading || 'Meeting Times' }}</h3>
                            <p v-if="block.times || club.meeting_formula" class="text-slate-300 text-sm whitespace-pre-line leading-relaxed">
                                {{ block.times || club.meeting_formula }}
                            </p>
                            <span v-else class="text-slate-500 dark:text-slate-400 text-xs italic">No meeting schedule configured</span>
                        </div>

                        <!-- Location Card -->
                        <div class="p-8 rounded-2xl bg-amber-50/10 dark:bg-amber-950/10 border border-amber-500/20 text-center space-y-4 hover:border-amber-500/40 transition-all">
                            <div class="w-14 h-14 rounded-full bg-slate-900 dark:bg-slate-700 border border-amber-500/30 text-amber-400 flex items-center justify-center mx-auto text-xl shadow-lg">
                                📍
                            </div>
                            <h3 class="font-bold text-white text-lg">{{ block.location_heading || 'Location' }}</h3>
                            <p v-if="block.location || club.address" class="text-slate-300 text-sm whitespace-pre-line leading-relaxed">
                                {{ block.location || club.address }}
                            </p>
                            <span v-else class="text-slate-500 dark:text-slate-400 text-xs italic">No location address configured</span>
                        </div>
                    </div>
                </section>

                <!-- 12. Interactive Contact Form Block -->
                <section v-else-if="block.type === 'contact_form'" class="py-8 space-y-8 max-w-3xl mx-auto">
                    <div class="text-center space-y-3">
                        <h2 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight">{{ block.heading || 'Send Us a Message' }}</h2>
                        <div class="w-12 h-1 bg-amber-500/80 mx-auto my-2 rounded-full"></div>
                        <p v-if="block.subtitle" class="text-sm sm:text-base text-slate-300 leading-relaxed max-w-xl mx-auto">{{ block.subtitle }}</p>
                    </div>

                    <!-- Success Alert -->
                    <div v-if="contactSuccessMap[block.id]" class="p-4 sm:p-5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm font-semibold flex items-center gap-3">
                        <span class="text-xl">✅</span>
                        <span>{{ contactSuccessMap[block.id] }}</span>
                    </div>

                    <form @submit.prevent="submitPublicContactForm(block)" class="p-6 sm:p-10 rounded-3xl bg-slate-900/80 border border-slate-800 shadow-2xl space-y-6 text-sm">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                                    Your Name <span v-if="block.name_required" class="text-rose-400">*</span>
                                </label>
                                <input 
                                    v-model="contactForm.name" 
                                    type="text" 
                                    :required="block.name_required !== false"
                                    placeholder="e.g. John Doe" 
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3.5 text-white focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors" 
                                />
                                <span v-if="contactForm.errors.name" class="text-xs text-rose-400 mt-1 block">{{ contactForm.errors.name }}</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                                    Email Address <span v-if="block.email_required !== false" class="text-rose-400">*</span>
                                </label>
                                <input 
                                    v-model="contactForm.email" 
                                    type="email" 
                                    :required="block.email_required !== false"
                                    placeholder="e.g. john@example.org" 
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3.5 text-white focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors" 
                                />
                                <span v-if="contactForm.errors.email" class="text-xs text-rose-400 mt-1 block">{{ contactForm.errors.email }}</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                                Phone Number <span v-if="block.phone_required" class="text-rose-400">*</span>
                            </label>
                            <input 
                                v-model="contactForm.phone" 
                                type="tel" 
                                :required="!!block.phone_required"
                                placeholder="e.g. +44 7123 456789" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3.5 text-white focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors" 
                            />
                            <span v-if="contactForm.errors.phone" class="text-xs text-rose-400 mt-1 block">{{ contactForm.errors.phone }}</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                                Message <span v-if="block.message_required !== false" class="text-rose-400">*</span>
                            </label>
                            <textarea 
                                v-model="contactForm.message" 
                                rows="5" 
                                :required="block.message_required !== false"
                                placeholder="Write your message or inquiry here..." 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3.5 text-white focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors"
                            ></textarea>
                            <span v-if="contactForm.errors.message" class="text-xs text-rose-400 mt-1 block">{{ contactForm.errors.message }}</span>
                        </div>

                        <button 
                            type="submit" 
                            :disabled="contactForm.processing"
                            class="w-full py-4 rounded-xl bg-gradient-to-r from-amber-500 via-amber-600 to-amber-700 hover:from-amber-600 hover:to-amber-800 text-white font-black text-sm uppercase tracking-wider shadow-lg shadow-amber-500/20 transition-all cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50"
                        >
                            <span>{{ contactForm.processing ? 'Sending Message...' : (block.button_text || 'Send Message') }}</span>
                            <span>→</span>
                        </button>
                    </form>
                </section>
            </div>
        </main>

        <!-- Donation Modal -->
        <div v-if="showDonationModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-slate-900 dark:bg-slate-700 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-6 shadow-2xl">
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
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Donation Amount ({{ $cs }})</label>
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
