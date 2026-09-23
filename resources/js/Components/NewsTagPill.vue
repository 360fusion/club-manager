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
        :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition-shadow hover:shadow-sm', orderColour(tag.color).soft, active ? 'ring-2 ring-blue-500' : '']"
    >
        {{ tag.name }}<span v-if="count !== null" class="opacity-70">{{ count }}</span>
    </Link>
</template>
