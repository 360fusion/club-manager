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

const handleClick = (e) => {
    if (!props.email) return;
    if (props.asLink) {
        e.preventDefault();
        window.location.href = `mailto:${props.email}`;
    }
};

onMounted(() => {
    isMounted.value = true;
});
</script>

<template>
    <a
        v-if="asLink && email"
        href="#"
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
