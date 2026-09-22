<script setup>
import { ref, onMounted, computed } from 'vue';

const props = defineProps({
    email: {
        type: String,
        default: '',
    },
    asLink: {
        type: Boolean,
        default: true,
    },
    customClass: {
        type: String,
        default: 'text-amber-400 hover:text-amber-300 font-semibold text-sm sm:text-base break-all transition-colors cursor-pointer',
    },
});

const isMounted = ref(false);

// Base64 encoded email string to hide from initial static HTML scrapers
const b64Email = computed(() => {
    if (!props.email) return '';
    try {
        return btoa(props.email);
    } catch (e) {
        return props.email;
    }
});

// Display text: shows real email once JS mounts; fallback is 'user [at] domain.com' for non-JS scrapers
const displayEmail = computed(() => {
    if (!props.email) return '';
    if (isMounted.value) {
        return props.email;
    }
    return props.email.replace('@', ' [at] ').replace(/\./g, ' [dot] ');
});

// A real mailto: href, but only once mounted — so a static HTML scrape still only ever sees the
// base64 attribute, while a real browser (and anything reading the link's actual href — a screen
// reader's link list, "copy link address", opening in a new tab) gets a working destination
// instead of a bare "#".
const href = computed(() => (isMounted.value && props.email ? `mailto:${props.email}` : '#'));

const handleClick = (e) => {
    if (!props.email || !isMounted.value) return;
    if (props.asLink) {
        // The href is already a real mailto: link now, so the browser's default action is
        // correct — this just stops it also being treated as an in-app navigation.
        e.preventDefault();
        window.location.href = href.value;
    }
};

onMounted(() => {
    isMounted.value = true;
});
</script>

<template>
    <a
        v-if="asLink && email"
        :href="href"
        @click="handleClick"
        :class="customClass"
        :data-email-b64="b64Email"
        title="Click to send email (Protected from spam bots)"
    >
        {{ displayEmail }}
    </a>
    <span v-else-if="email" :class="customClass" :data-email-b64="b64Email">
        {{ displayEmail }}
    </span>
</template>
