<script setup>
// Loads the club's visitor statistics and, when needed, asks first. Plausible sets no cookies so it loads at
// once; Google Analytics loads only after the visitor accepts. The choice is remembered in this browser.
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    tracking: { type: Object, required: true },
    clubSlug: { type: String, required: true },
});

const storageKey = computed(() => `cm_cookie_choice_${props.clubSlug}`);
const choice = ref(null); // 'accepted', 'declined' or null while undecided
const ready = ref(false);

const needsConsent = computed(() => props.tracking.provider === 'google');
const showNotice = computed(() => ready.value && props.tracking.banner && choice.value === null);

const addScript = (src, attributes = {}) => {
    const script = document.createElement('script');
    script.async = true;
    script.src = src;
    Object.entries(attributes).forEach(([name, value]) => script.setAttribute(name, value));
    document.head.appendChild(script);
};

let loaded = false;

const loadTracking = () => {
    const { provider, id } = props.tracking;

    if (loaded) return;

    if (provider === 'plausible') {
        loaded = true;
        addScript('https://plausible.io/js/script.js', { defer: '', 'data-domain': id });
    } else if (provider === 'google' && choice.value === 'accepted') {
        loaded = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', id, { anonymize_ip: true });
        addScript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`);
    }
};

const decide = (value) => {
    choice.value = value;
    try {
        localStorage.setItem(storageKey.value, value);
    } catch { /* asked again next visit */ }
    loadTracking();
};

onMounted(() => {
    try {
        choice.value = localStorage.getItem(storageKey.value);
    } catch { /* treated as undecided */ }
    ready.value = true;

    // Plausible needs no consent, and a returning visitor who already accepted Google needs no second question.
    if (!needsConsent.value || choice.value === 'accepted') loadTracking();
});

const noticeText = computed(() => props.tracking.banner_text
    || (needsConsent.value ? 'We use cookies to count visits so we can improve this website.' : 'This website counts visits without using cookies.'));
</script>

<template>
    <div
        v-if="showNotice"
        role="dialog"
        aria-label="Cookies"
        class="fixed bottom-0 inset-x-0 z-[90] p-3 sm:p-4"
    >
        <div class="max-w-3xl mx-auto bg-[var(--cm-night)] text-white rounded-2xl shadow-2xl border border-white/10 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-5 text-xs sm:text-sm">
            <p class="flex-1 leading-relaxed">
                {{ noticeText }}
                <a v-if="tracking.banner_link_url && tracking.banner_link_label" :href="tracking.banner_link_url" class="underline font-bold whitespace-nowrap">{{ tracking.banner_link_label }}</a>
            </p>
            <div class="flex gap-2 shrink-0">
                <button v-if="needsConsent" type="button" class="px-4 py-2 rounded-xl border border-white/30 font-bold hover:bg-white/10 cursor-pointer" @click="decide('declined')">Decline</button>
                <button type="button" class="px-4 py-2 rounded-xl bg-white text-[var(--cm-night)] font-bold hover:bg-[var(--cm-tint)] cursor-pointer" @click="decide(needsConsent ? 'accepted' : 'dismissed')">{{ needsConsent ? 'Accept' : 'OK' }}</button>
            </div>
        </div>
    </div>
</template>
