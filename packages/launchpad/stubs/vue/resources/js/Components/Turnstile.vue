<script setup>
import { onMounted, ref } from 'vue';

// Cloudflare's "are you a person" check. It draws only when the site has a key (turnstileKey is null otherwise) and hands the token to the form.
const props = defineProps({ siteKey: { type: String, default: null }, error: { type: String, default: null } });
const emit = defineEmits(['verified']);
const box = ref(null);

onMounted(() => {
    if (!props.siteKey) {
        return;
    }

    const draw = () => window.turnstile.render(box.value, { sitekey: props.siteKey, callback: (token) => emit('verified', token), 'expired-callback': () => emit('verified', '') });

    if (window.turnstile) {
        draw();

        return;
    }

    const script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    script.async = true;
    script.onload = draw;
    document.head.appendChild(script);
});
</script>

<template>
    <div v-if="siteKey" class="lp-field">
        <div ref="box" />
        <p v-if="error" class="lp-error">{{ error }}</p>
    </div>
</template>
