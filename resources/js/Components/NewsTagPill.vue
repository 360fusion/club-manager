<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { orderColour } from '@/Utils/orderColour';

const props = defineProps({
    // { name, slug, color }
    tag: { type: Object, required: true },
    // With a club slug the pill filters that club's news; without one, all of the member's clubs.
    clubSlug: { type: String, default: null },
    count: { type: Number, default: null },
    active: { type: Boolean, default: false },
});

const href = computed(() => (props.clubSlug ? route('member.news', { slug: props.clubSlug }) : route('members.news')));
</script>

<template>
    <Link
        :href="href"
        :data="{ tags: [tag.slug] }"
        :aria-current="active ? 'true' : undefined"
        :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition-shadow hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950', orderColour(tag.color).soft, active ? 'ring-2 ring-blue-500' : '']"
    >
        {{ tag.name }}<span v-if="count !== null" class="opacity-70"><span class="sr-only">, </span>{{ count }}</span>
    </Link>
</template>
