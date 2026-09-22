<script setup>
// Renders a page's blocks. Used by both the website builder's live preview and the real public page
// (Public/Site.vue), so a block only has to be written once: what an admin previews is what a visitor sees.
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import ObfuscatedEmail from '@/Components/ObfuscatedEmail.vue';

const props = defineProps({
    blocks: { type: Array, default: () => [] },
    theme: { type: Object, required: true },
    club: { type: Object, required: true },
    latestPosts: { type: Array, default: () => [] },
    upcomingEvents: { type: Array, default: () => [] },
    membershipPlans: { type: Array, default: () => [] },
    donations: { type: Array, default: () => [] },
    // false in the website builder's own preview: forms are shown but do nothing, so editing a page can
    // never accidentally email someone or take a donation.
    interactive: { type: Boolean, default: true },
    // Wraps an internal link so the "preview this theme" banner can carry the theme along as you click around.
    resolveUrl: { type: Function, default: (url) => url },
});

// ---- news feed: pagination and layout, one block can hold several of these -------------------------------------
const newsBlockPages = ref({});
const getNewsCurrentPage = (blockId) => newsBlockPages.value[blockId] || 1;
const setNewsPage = (blockId, pageNum) => { newsBlockPages.value[blockId] = pageNum; };

const getNewsTotalPages = (block) => {
    if (!props.latestPosts.length) return 1;
    const perPage = parseInt(block.limit) || 999;
    return Math.ceil(props.latestPosts.length / perPage);
};

const getNewsGridClass = (block) => {
    const cols = String(block.columns || '3');
    if (cols === '1') return 'grid grid-cols-1 gap-6';
    if (cols === '2') return 'grid grid-cols-1 md:grid-cols-2 gap-6';
    if (cols === '4') return 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6';
    if (cols === 'masonry') return 'columns-1 md:columns-2 lg:columns-3 gap-6 space-y-6';
    return 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6';
};

const getFilteredPosts = (block) => {
    const perPage = parseInt(block.limit) || 999;
    const page = getNewsCurrentPage(block.id);
    const start = (page - 1) * perPage;
    return props.latestPosts.slice(start, start + perPage);
};

const getPostImagePos = (block, idx) => {
    const pos = String(block.image_position || 'above');
    return pos === 'alternate' ? (idx % 2 === 0 ? 'left' : 'right') : pos;
};

// ---- donations: the "make a contribution" modal, live pages only -------------------------------------------------
const showDonationModal = ref(false);
const activeDonation = ref(null);
const donationForm = useForm({ donor_name: '', donor_email: '', amount: 50 });

const openDonation = (campaign) => {
    if (!props.interactive) return;
    activeDonation.value = campaign;
    showDonationModal.value = true;
};

const submitDonation = () => {
    if (!activeDonation.value) return;
    donationForm.post(`/site/${props.club.slug}/donations/${activeDonation.value.id}`, {
        onSuccess: () => { showDonationModal.value = false; },
    });
};

// ---- contact form: one shared form, keyed success message per block (a page can have more than one) --------------
const contactForm = useForm({ name: '', email: '', phone: '', message: '', recipient_email: '', cc_emails: '', success_message: '' });
const contactSuccessMap = ref({});

const submitContactForm = (block) => {
    if (!props.interactive) return;

    contactForm.recipient_email = block.recipient_email || props.club.contact_email || props.club.email || '';
    contactForm.cc_emails = block.cc_emails || '';
    contactForm.success_message = block.success_message || 'Thank you! Your message has been sent successfully.';

    contactForm.post(`/site/${props.club.slug}/contact-form`, {
        preserveScroll: true,
        onSuccess: () => {
            contactSuccessMap.value[block.id] = contactForm.success_message;
            contactForm.reset('name', 'email', 'phone', 'message');
        },
    });
};
</script>

<template>
    <div class="space-y-16">
        <div v-for="(block, index) in blocks" :key="block.id || index" class="w-full">

            <!-- 1. Hero banner -->
            <section v-if="block.type === 'hero'" :class="['relative p-10 sm:p-16 rounded-3xl text-center overflow-hidden border transition-colors', theme.heroBg]">
                <div class="relative z-10 max-w-3xl mx-auto space-y-6">
                    <div :class="['inline-block px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border', theme.heroPill]">Official Club Website</div>
                    <h1 class="text-4xl sm:text-6xl font-black leading-tight">{{ block.title || 'Welcome' }}</h1>
                    <p v-if="block.subtitle" class="text-lg sm:text-xl opacity-90">{{ block.subtitle }}</p>
                    <div v-if="block.cta_text" class="pt-2">
                        <component :is="interactive && block.cta_link ? 'a' : 'span'" :href="interactive ? resolveUrl(block.cta_link) : undefined" :class="['inline-block py-3.5 px-7 rounded-2xl font-bold text-sm shadow-xl hover:scale-105 transition-transform', theme.heroCta]">
                            {{ block.cta_text }}
                        </component>
                    </div>
                </div>
            </section>

            <!-- 2. Text -->
            <section v-else-if="block.type === 'text' || block.type === 'rich_text'" class="prose dark:prose-invert max-w-4xl mx-auto">
                <h2 v-if="block.heading" :class="['text-2xl sm:text-3xl font-bold mb-4', theme.headingText]">{{ block.heading }}</h2>
                <div :class="theme.bodyText" v-html="block.content"></div>
            </section>

            <!-- 3. Single image -->
            <section v-else-if="block.type === 'image' && block.url" class="max-w-5xl mx-auto">
                <div :class="['flex', block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : 'justify-center']">
                    <figure :class="['space-y-2', block.size === 'small' ? 'w-full sm:w-1/3' : block.size === 'medium' ? 'w-full sm:w-1/2' : block.size === 'large' ? 'w-full sm:w-3/4' : 'w-full']">
                        <img :src="block.url" :alt="block.caption || 'Image'" class="w-full h-auto rounded-2xl border border-slate-800 shadow-xl object-cover" />
                        <figcaption v-if="block.caption" class="text-xs text-center text-slate-400 italic">{{ block.caption }}</figcaption>
                    </figure>
                </div>
            </section>

            <!-- 4. Image gallery -->
            <section v-else-if="block.type === 'images' && block.items?.length" class="max-w-6xl mx-auto space-y-4">
                <div :class="['grid gap-4', block.columns === 2 ? 'grid-cols-1 sm:grid-cols-2' : block.columns === 4 ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4' : 'grid-cols-1 sm:grid-cols-3']">
                    <div v-for="(item, iIdx) in block.items" :key="item.id || iIdx" class="space-y-2">
                        <img v-if="item.url" :src="item.url" :alt="item.caption || 'Gallery image'" class="w-full h-48 sm:h-56 object-cover rounded-2xl border border-slate-800 shadow-md" />
                        <p v-if="item.caption" class="text-xs text-center text-slate-400 italic">{{ item.caption }}</p>
                    </div>
                </div>
            </section>

            <!-- 5. Callout box -->
            <section v-else-if="block.type === 'notice'" class="max-w-4xl mx-auto">
                <div :class="['p-6 rounded-2xl border space-y-2',
                    block.style === 'warning' ? 'bg-amber-500/10 border-amber-500/30 text-amber-800 dark:text-amber-200' :
                    block.style === 'important' ? 'bg-rose-500/10 border-rose-500/30 text-rose-800 dark:text-rose-200' :
                    block.style === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-800 dark:text-emerald-200' :
                    'bg-blue-500/10 border-blue-500/30 text-blue-800 dark:text-blue-200']">
                    <h4 v-if="block.title" class="font-extrabold text-base flex items-center gap-2"><span>📢</span> {{ block.title }}</h4>
                    <p class="text-sm font-medium leading-relaxed">{{ block.text }}</p>
                </div>
            </section>

            <!-- 6. Button link -->
            <section v-else-if="block.type === 'button' && (block.url || !interactive)" class="max-w-4xl mx-auto">
                <div :class="['flex', block.align === 'left' ? 'justify-start' : block.align === 'right' ? 'justify-end' : 'justify-center']">
                    <component :is="interactive ? 'a' : 'span'" :href="interactive ? resolveUrl(block.url) : undefined" :class="['py-3 px-6 rounded-xl font-bold text-sm shadow-lg hover:scale-105 transition-all', theme.heroCta]">
                        {{ block.label || 'Learn More →' }}
                    </component>
                </div>
            </section>

            <!-- 7. Membership pricing -->
            <section v-else-if="block.type === 'pricing_cards'" class="max-w-6xl mx-auto space-y-6">
                <h2 :class="['text-2xl sm:text-3xl font-extrabold text-center', theme.headingText]">{{ block.heading || 'Membership Options & Dues' }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div v-for="plan in membershipPlans" :key="plan.id" :class="['p-6 rounded-3xl space-y-4 flex flex-col justify-between', theme.cardBg]">
                        <div>
                            <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider border', theme.heroPill]">{{ plan.billing_period }}</span>
                            <h3 :class="['text-xl font-bold mt-2', theme.headingText]">{{ plan.name }}</h3>
                            <p :class="['text-xs mt-1', theme.bodyText]">{{ plan.description }}</p>
                        </div>
                        <div class="pt-4 border-t border-slate-800/20 flex items-baseline justify-between">
                            <div>
                                <span :class="['text-2xl font-black', theme.headingText]">{{ $cs }}{{ plan.price }}</span>
                                <span :class="['text-xs', theme.bodyText]"> / {{ plan.billing_period }}</span>
                            </div>
                            <Link v-if="interactive" :href="`/${club.slug}/admin/subscriptions`" class="py-2 px-4 rounded-xl bg-blue-600 text-white font-bold text-xs hover:bg-blue-500">Subscribe →</Link>
                            <span v-else :class="['text-xs font-bold', theme.accentText]">Subscribe →</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 8. Donation campaign -->
            <section v-else-if="block.type === 'donation_campaign'" class="space-y-6">
                <div v-for="d in donations" :key="d.id" :class="['p-8 sm:p-10 rounded-3xl space-y-6', theme.heroBg]">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 uppercase tracking-wider">🎁 Active Fundraising Campaign</span>
                            <h2 class="text-2xl sm:text-3xl font-black mt-2">{{ d.campaign_name }}</h2>
                            <p class="text-sm mt-1 max-w-2xl opacity-90">{{ d.description }}</p>
                        </div>
                        <button type="button" :disabled="!interactive" @click="openDonation(d)" class="py-3.5 px-6 rounded-2xl bg-gradient-to-r from-amber-500 to-rose-500 hover:from-amber-400 hover:to-rose-400 text-white font-bold text-sm shadow-xl shadow-amber-500/20 hover:scale-105 transition-transform disabled:opacity-70 disabled:hover:scale-100 disabled:cursor-not-allowed">
                            💖 Make a Contribution
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm opacity-90">
                            <span>Raised: <strong class="text-emerald-500 dark:text-emerald-400 font-bold">{{ $cs }}{{ d.current_amount }}</strong></span>
                            <span>Target: <strong>{{ $cs }}{{ d.target_amount }}</strong></span>
                        </div>
                        <div class="w-full h-4 rounded-full bg-black/20 border border-black/10 overflow-hidden p-0.5">
                            <div class="h-full rounded-full bg-gradient-to-r from-amber-500 via-emerald-400 to-blue-400 transition-all duration-500" :style="{ width: `${d.percentage}%` }"></div>
                        </div>
                        <div class="text-right text-xs font-bold text-amber-600 dark:text-amber-400">{{ d.percentage }}% Funded ({{ d.contributions_count }} Donors)</div>
                    </div>
                </div>
            </section>

            <!-- 9. News feed -->
            <section v-else-if="block.type === 'news_feed' || block.type === 'news_list'" class="space-y-6">
                <h2 v-if="block.heading" :class="['text-2xl sm:text-3xl font-bold', theme.headingText]">{{ block.heading }}</h2>

                <div :class="getNewsGridClass(block)">
                    <template v-for="(post, pIdx) in getFilteredPosts(block)" :key="post.id">
                        <div :class="[theme.cardBg, 'rounded-2xl overflow-hidden transition-all', String(block.columns) === 'masonry' ? 'break-inside-avoid inline-block w-full mb-6' : '',
                            post.cover_image_url && ['left', 'right'].includes(getPostImagePos(block, pIdx)) ? 'grid grid-cols-1 sm:grid-cols-3 gap-0' : 'flex flex-col justify-between']">
                            <template v-if="post.cover_image_url && getPostImagePos(block, pIdx) === 'left'">
                                <div class="sm:col-span-1 min-h-[160px] bg-slate-800 overflow-hidden"><img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" /></div>
                                <div class="sm:col-span-2 p-6 space-y-2">
                                    <div class="flex items-center justify-between text-xs opacity-70"><span v-if="post.author_name">Published by {{ post.author_name }}</span><span>{{ post.published_at }}</span></div>
                                    <h3 :class="['text-lg font-bold leading-snug', theme.headingText]">{{ post.title }}</h3>
                                    <p :class="['text-sm leading-relaxed line-clamp-3', theme.bodyText]">{{ post.excerpt || post.content }}</p>
                                </div>
                            </template>
                            <template v-else-if="post.cover_image_url && getPostImagePos(block, pIdx) === 'right'">
                                <div class="sm:col-span-2 p-6 space-y-2 order-2 sm:order-1">
                                    <div class="flex items-center justify-between text-xs opacity-70"><span v-if="post.author_name">Published by {{ post.author_name }}</span><span>{{ post.published_at }}</span></div>
                                    <h3 :class="['text-lg font-bold leading-snug', theme.headingText]">{{ post.title }}</h3>
                                    <p :class="['text-sm leading-relaxed line-clamp-3', theme.bodyText]">{{ post.excerpt || post.content }}</p>
                                </div>
                                <div class="sm:col-span-1 min-h-[160px] bg-slate-800 overflow-hidden order-1 sm:order-2"><img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" /></div>
                            </template>
                            <template v-else-if="post.cover_image_url && getPostImagePos(block, pIdx) === 'below'">
                                <div class="p-6 space-y-2">
                                    <div class="flex items-center justify-between text-xs opacity-70"><span v-if="post.author_name">Published by {{ post.author_name }}</span><span>{{ post.published_at }}</span></div>
                                    <h3 :class="['text-lg font-bold leading-snug', theme.headingText]">{{ post.title }}</h3>
                                    <p :class="['text-sm leading-relaxed line-clamp-3', theme.bodyText]">{{ post.excerpt || post.content }}</p>
                                </div>
                                <div class="h-48 w-full bg-slate-800 overflow-hidden border-t border-black/10"><img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" /></div>
                            </template>
                            <template v-else-if="post.cover_image_url">
                                <div class="h-48 w-full bg-slate-800 overflow-hidden border-b border-black/10"><img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" /></div>
                                <div class="p-6 space-y-2">
                                    <div class="flex items-center justify-between text-xs opacity-70"><span v-if="post.author_name">Published by {{ post.author_name }}</span><span>{{ post.published_at }}</span></div>
                                    <h3 :class="['text-lg font-bold leading-snug', theme.headingText]">{{ post.title }}</h3>
                                    <p :class="['text-sm leading-relaxed line-clamp-3', theme.bodyText]">{{ post.excerpt || post.content }}</p>
                                </div>
                            </template>
                            <div v-else class="p-6 space-y-2">
                                <div class="flex items-center justify-between text-xs opacity-70"><span v-if="post.author_name">Published by {{ post.author_name }}</span><span>{{ post.published_at }}</span></div>
                                <h3 :class="['text-lg font-bold leading-snug', theme.headingText]">{{ post.title }}</h3>
                                <p :class="['text-sm leading-relaxed line-clamp-3', theme.bodyText]">{{ post.excerpt || post.content }}</p>
                            </div>
                        </div>
                    </template>
                </div>

                <div v-if="getNewsTotalPages(block) > 1" class="flex items-center justify-between pt-6 border-t border-slate-800/20">
                    <button type="button" @click="setNewsPage(block.id, getNewsCurrentPage(block.id) - 1)" :disabled="getNewsCurrentPage(block.id) <= 1" :class="[theme.cardBg, 'py-2.5 px-4 rounded-xl text-xs font-bold flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer']">← Previous</button>
                    <span :class="['text-xs font-bold', theme.bodyText]">Page <span :class="theme.headingText">{{ getNewsCurrentPage(block.id) }}</span> of {{ getNewsTotalPages(block) }}</span>
                    <button type="button" @click="setNewsPage(block.id, getNewsCurrentPage(block.id) + 1)" :disabled="getNewsCurrentPage(block.id) >= getNewsTotalPages(block)" :class="[theme.cardBg, 'py-2.5 px-4 rounded-xl text-xs font-bold flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer']">Next →</button>
                </div>
            </section>

            <!-- 10. Upcoming events -->
            <section v-else-if="block.type === 'events_calendar'" class="space-y-6">
                <h2 :class="['text-2xl font-bold', theme.headingText]">{{ block.heading || 'Upcoming Events & Formal Dinners' }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="e in upcomingEvents" :key="e.id" :class="['p-6 rounded-2xl space-y-4', theme.cardBg]">
                        <h3 :class="['text-lg font-bold', theme.headingText]">{{ e.title }}</h3>
                        <p :class="['text-xs', theme.bodyText]">📍 {{ e.location }} • 🕒 {{ e.starts_at }}</p>
                        <Link v-if="interactive" :href="`/site/${club.slug}/events/${e.slug}`" class="block w-full py-2.5 px-4 rounded-xl bg-black/10 dark:bg-white/10 text-center font-bold text-xs">Event details & booking →</Link>
                    </div>
                </div>
            </section>

            <!-- 11. Contact details -->
            <section v-else-if="block.type === 'contact_details'" class="py-8 space-y-10 text-center">
                <div class="max-w-3xl mx-auto space-y-3">
                    <span v-if="block.eyebrow" class="text-xs font-bold text-amber-600 dark:text-amber-500 uppercase tracking-widest block">{{ block.eyebrow }}</span>
                    <h2 :class="['text-3xl sm:text-5xl font-extrabold tracking-tight', theme.headingText]">{{ block.title || 'Get in Touch' }}</h2>
                    <div class="w-12 h-1 bg-amber-500/80 mx-auto my-4 rounded-full"></div>
                    <p v-if="block.description" :class="['text-sm sm:text-base leading-relaxed max-w-2xl mx-auto', theme.bodyText]">{{ block.description }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                    <div v-for="card in [
                        { icon: '✉️', heading: block.email_heading || 'Email', value: block.email || club.contact_email, kind: 'email' },
                        { icon: '📅', heading: block.times_heading || 'Meeting Times', value: block.times || club.meeting_formula, kind: 'text' },
                        { icon: '📍', heading: block.location_heading || 'Location', value: block.location || club.address, kind: 'text' },
                    ]" :key="card.heading" :class="['p-8 rounded-2xl text-center space-y-4 transition-all', theme.cardBg]">
                        <div class="w-14 h-14 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto text-xl">{{ card.icon }}</div>
                        <h3 :class="['font-bold text-lg', theme.headingText]">{{ card.heading }}</h3>
                        <ObfuscatedEmail v-if="card.kind === 'email' && card.value" :email="card.value" custom-class="text-amber-600 dark:text-amber-400 hover:underline font-semibold text-sm sm:text-base break-all cursor-pointer transition-colors" />
                        <p v-else-if="card.value" :class="['text-sm whitespace-pre-line leading-relaxed', theme.bodyText]">{{ card.value }}</p>
                        <span v-else class="text-slate-500 dark:text-slate-400 text-xs italic">Not configured</span>
                    </div>
                </div>
            </section>

            <!-- 12. Contact form -->
            <section v-else-if="block.type === 'contact_form'" class="py-8 space-y-8 max-w-3xl mx-auto">
                <div class="text-center space-y-3">
                    <h2 :class="['text-3xl sm:text-5xl font-extrabold tracking-tight', theme.headingText]">{{ block.heading || 'Send Us a Message' }}</h2>
                    <div class="w-12 h-1 bg-amber-500/80 mx-auto my-2 rounded-full"></div>
                    <p v-if="block.subtitle" :class="['text-sm sm:text-base leading-relaxed max-w-xl mx-auto', theme.bodyText]">{{ block.subtitle }}</p>
                </div>

                <div v-if="interactive && contactSuccessMap[block.id]" class="p-4 sm:p-5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm font-semibold flex items-center gap-3">
                    <span class="text-xl">✅</span><span>{{ contactSuccessMap[block.id] }}</span>
                </div>

                <form @submit.prevent="submitContactForm(block)" :class="['p-6 sm:p-10 rounded-3xl shadow-2xl space-y-6 text-sm', theme.cardBg]">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider mb-2 opacity-80">Your Name <span v-if="block.name_required" class="text-rose-500">*</span></label>
                            <input v-model="contactForm.name" type="text" :disabled="!interactive" :required="interactive && block.name_required !== false" placeholder="e.g. John Doe" class="w-full bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 rounded-xl p-3.5 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors disabled:opacity-70 disabled:cursor-not-allowed" />
                            <span v-if="contactForm.errors.name" class="text-xs text-rose-500 mt-1 block">{{ contactForm.errors.name }}</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider mb-2 opacity-80">Email Address <span v-if="block.email_required !== false" class="text-rose-500">*</span></label>
                            <input v-model="contactForm.email" type="email" :disabled="!interactive" :required="interactive && block.email_required !== false" placeholder="e.g. john@example.org" class="w-full bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 rounded-xl p-3.5 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors disabled:opacity-70 disabled:cursor-not-allowed" />
                            <span v-if="contactForm.errors.email" class="text-xs text-rose-500 mt-1 block">{{ contactForm.errors.email }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-2 opacity-80">Phone Number <span v-if="block.phone_required" class="text-rose-500">*</span></label>
                        <input v-model="contactForm.phone" type="tel" :disabled="!interactive" :required="interactive && !!block.phone_required" placeholder="e.g. +44 7123 456789" class="w-full bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 rounded-xl p-3.5 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors disabled:opacity-70 disabled:cursor-not-allowed" />
                        <span v-if="contactForm.errors.phone" class="text-xs text-rose-500 mt-1 block">{{ contactForm.errors.phone }}</span>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-2 opacity-80">Message <span v-if="block.message_required !== false" class="text-rose-500">*</span></label>
                        <textarea v-model="contactForm.message" rows="5" :disabled="!interactive" :required="interactive && block.message_required !== false" placeholder="Write your message or inquiry here..." class="w-full bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 rounded-xl p-3.5 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors disabled:opacity-70 disabled:cursor-not-allowed"></textarea>
                        <span v-if="contactForm.errors.message" class="text-xs text-rose-500 mt-1 block">{{ contactForm.errors.message }}</span>
                    </div>
                    <button type="submit" :disabled="!interactive || contactForm.processing" :class="['w-full py-4 rounded-xl font-black text-sm uppercase tracking-wider shadow-lg transition-all flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed', theme.heroCta]">
                        <span>{{ contactForm.processing ? 'Sending Message...' : (block.button_text || 'Send Message') }}</span><span>→</span>
                    </button>
                    <p v-if="!interactive" class="text-[11px] text-center opacity-70">🔒 Submissions will route to: <strong>{{ block.recipient_email || club.contact_email || club.email || 'not set yet' }}</strong><span v-if="block.cc_emails"> (CC: {{ block.cc_emails }})</span></p>
                </form>
            </section>
        </div>

        <!-- Donation modal (live pages only) -->
        <div v-if="interactive && showDonationModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-6 shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white">Make a Donation</h3>
                        <p class="text-xs text-amber-600 dark:text-amber-400">{{ activeDonation?.campaign_name }}</p>
                    </div>
                    <button type="button" @click="showDonationModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-white font-bold" aria-label="Close">✕</button>
                </div>
                <form @submit.prevent="submitDonation" class="space-y-4 text-sm">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Your Full Name</label>
                        <input v-model="donationForm.donor_name" type="text" required placeholder="e.g. Jane Smith" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-slate-900 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Email Address</label>
                        <input v-model="donationForm.donor_email" type="email" required placeholder="e.g. jane@example.com" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-slate-900 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Donation Amount ({{ $cs }})</label>
                        <input v-model="donationForm.amount" type="number" min="1" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-slate-900 dark:text-white font-bold text-lg" />
                    </div>
                    <button type="submit" :disabled="donationForm.processing" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-rose-500 text-white font-bold text-sm shadow-lg shadow-amber-500/20 disabled:opacity-60">Confirm & Process Donation</button>
                </form>
            </div>
        </div>
    </div>
</template>
