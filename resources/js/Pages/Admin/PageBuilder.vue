<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    club: Object,
    page: Object,
});

const form = useForm({
    id: props.page.id || null,
    title: props.page.title || 'New Page',
    slug: props.page.slug || 'new-page',
    is_published: props.page.is_published ?? true,
    is_homepage: props.page.is_homepage ?? false,
    show_in_navigation: props.page.show_in_navigation ?? true,
    blocks: props.page.blocks ? JSON.parse(JSON.stringify(props.page.blocks)) : [],
});

const addBlock = (type) => {
    if (type === 'hero') {
        form.blocks.push({
            type: 'hero',
            title: 'Welcome to Our Club',
            subtitle: 'Join us for training and events.',
            cta_text: 'Explore Membership',
            cta_link: '/site/' + props.club.slug,
        });
    } else if (type === 'rich_text') {
        form.blocks.push({
            type: 'rich_text',
            heading: 'About Our Facilities',
            content: '<p>Edit your rich content text here.</p>',
        });
    } else if (type === 'news_feed') {
        form.blocks.push({
            type: 'news_feed',
            heading: 'Latest Club News',
            limit: 3,
        });
    } else if (type === 'donation_campaign') {
        form.blocks.push({
            type: 'donation_campaign',
            heading: 'Fundraising Campaign',
        });
    } else if (type === 'events_calendar') {
        form.blocks.push({
            type: 'events_calendar',
            heading: 'Upcoming Regattas & Dinners',
            limit: 3,
        });
    } else if (type === 'pricing_cards') {
        form.blocks.push({
            type: 'pricing_cards',
            heading: 'Membership Dues',
        });
    }
};

const removeBlock = (index) => {
    form.blocks.splice(index, 1);
};

const moveBlock = (index, direction) => {
    const target = index + direction;
    if (target >= 0 && target < form.blocks.length) {
        const temp = form.blocks[index];
        form.blocks[index] = form.blocks[target];
        form.blocks[target] = temp;
    }
};

const submit = () => {
    form.post(`/clubs/${props.club.slug}/admin/pages`);
};
</script>

<template>
    <Head :title="`Page Builder - ${form.title}`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans p-6 sm:p-10">
        <div class="max-w-5xl mx-auto space-y-8">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-800 pb-6">
                <div>
                    <Link :href="`/clubs/${club.slug}/admin/pages`" class="text-xs text-slate-400 hover:text-white">← Back to Page List</Link>
                    <h1 class="text-3xl font-black text-white mt-1">Visual Block Page Builder</h1>
                </div>

                <div class="flex items-center gap-3">
                    <button @click="submit" :disabled="form.processing" class="py-3 px-6 rounded-xl bg-gradient-to-r from-emerald-600 to-sky-600 text-white font-bold text-sm shadow-xl shadow-emerald-600/20">
                        💾 Save & Publish Page
                    </button>
                </div>
            </div>

            <!-- Page Settings Form -->
            <div class="p-6 rounded-2xl bg-slate-900/70 border border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Page Title</label>
                    <input v-model="form.title" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white" />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">URL Slug</label>
                    <input v-model="form.slug" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white" />
                </div>

                <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-300">
                        <input type="checkbox" v-model="form.is_published" class="w-4 h-4 accent-emerald-500" />
                        Is Published
                    </label>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-300">
                        <input type="checkbox" v-model="form.is_homepage" class="w-4 h-4 accent-amber-500" />
                        Set as Homepage
                    </label>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-300">
                        <input type="checkbox" v-model="form.show_in_navigation" class="w-4 h-4 accent-sky-500" />
                        Show in Header Nav
                    </label>
                </div>
            </div>

            <!-- Block Selector Toolbar -->
            <div class="p-6 rounded-2xl bg-slate-900/70 border border-slate-800 space-y-3">
                <h2 class="text-sm font-bold text-slate-300 uppercase tracking-wider">Add Content Section Block:</h2>
                <div class="flex flex-wrap gap-2">
                    <button @click="addBlock('hero')" class="py-2 px-3 rounded-lg bg-sky-500/10 text-sky-400 border border-sky-500/20 text-xs font-bold">
                        ➕ Hero Banner Block
                    </button>
                    <button @click="addBlock('rich_text')" class="py-2 px-3 rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-xs font-bold">
                        ➕ Rich Text Block
                    </button>
                    <button @click="addBlock('news_feed')" class="py-2 px-3 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20 text-xs font-bold">
                        ➕ Dynamic News Feed Block
                    </button>
                    <button @click="addBlock('donation_campaign')" class="py-2 px-3 rounded-lg bg-rose-500/10 text-rose-400 border border-rose-500/20 text-xs font-bold">
                        ➕ Dynamic Donation Campaign Block
                    </button>
                    <button @click="addBlock('events_calendar')" class="py-2 px-3 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-bold">
                        ➕ Dynamic Events Block
                    </button>
                    <button @click="addBlock('pricing_cards')" class="py-2 px-3 rounded-lg bg-purple-500/10 text-purple-400 border border-purple-500/20 text-xs font-bold">
                        ➕ Membership Pricing Cards Block
                    </button>
                </div>
            </div>

            <!-- Block List Visual Editor -->
            <div class="space-y-4">
                <div 
                    v-for="(block, index) in form.blocks" 
                    :key="index" 
                    class="p-6 rounded-2xl bg-slate-900 border border-slate-800 space-y-4 relative"
                >
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-sky-400">
                            Block #{{ index + 1 }}: {{ block.type.replace('_', ' ') }}
                        </span>

                        <div class="flex items-center gap-2">
                            <button @click="moveBlock(index, -1)" class="p-1 rounded bg-slate-800 text-xs">▲</button>
                            <button @click="moveBlock(index, 1)" class="p-1 rounded bg-slate-800 text-xs">▼</button>
                            <button @click="removeBlock(index)" class="p-1 px-2 rounded bg-rose-600/20 text-rose-400 text-xs font-bold">Delete</button>
                        </div>
                    </div>

                    <!-- Block Editor Inputs -->
                    <div v-if="block.type === 'hero'" class="space-y-3 text-sm">
                        <input v-model="block.title" placeholder="Hero Title" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white" />
                        <input v-model="block.subtitle" placeholder="Hero Subtitle" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white" />
                        <div class="grid grid-cols-2 gap-2">
                            <input v-model="block.cta_text" placeholder="CTA Button Text" class="bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white" />
                            <input v-model="block.cta_link" placeholder="CTA Button Link" class="bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white" />
                        </div>
                    </div>

                    <div v-else-if="block.type === 'rich_text'" class="space-y-3 text-sm">
                        <input v-model="block.heading" placeholder="Section Heading" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white" />
                        <textarea v-model="block.content" placeholder="Content HTML" rows="3" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white font-mono text-xs"></textarea>
                    </div>

                    <div v-else-if="block.type === 'news_feed'" class="space-y-3 text-sm">
                        <input v-model="block.heading" placeholder="Section Heading" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white" />
                        <p class="text-xs text-amber-400">⚡ Automatically pulls published News & Articles from your club's database.</p>
                    </div>

                    <div v-else-if="block.type === 'events_calendar'" class="space-y-3 text-sm">
                        <input v-model="block.heading" placeholder="Section Heading" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white" />
                        <p class="text-xs text-emerald-400">⚡ Automatically pulls upcoming Events, Dinners & Summons from your club's database.</p>
                    </div>

                    <div v-else-if="block.type === 'pricing_cards'" class="space-y-3 text-sm">
                        <input v-model="block.heading" placeholder="Section Heading" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white" />
                        <p class="text-xs text-purple-400">⚡ Automatically pulls active Membership Plans & Pricing from your club's database.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
