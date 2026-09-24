<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const flash = computed(() => page.props.flash ?? {});
const followError = computed(() => page.props.errors?.follow ?? null);
</script>

<template>
    <Head :title="`${title} - ClubManager`">
        <meta v-if="description" name="description" :content="description" />
    </Head>

    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-4 px-4">
                <div class="flex items-center gap-6">
                    <Link :href="route('home')" class="text-base font-bold tracking-tight">ClubManager</Link>
                    <nav class="flex items-center gap-4 text-sm font-medium text-slate-600 dark:text-slate-300">
                        <Link :href="route('lodges.index')" class="hover:text-slate-900 dark:hover:text-white">Find a lodge</Link>
                    </nav>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <ThemeToggle />
                    <Link v-if="user" :href="route('members.dashboard')" class="rounded-lg bg-blue-600 px-3 py-1.5 font-medium text-white hover:bg-blue-500">My area</Link>
                    <Link v-else :href="route('login')" class="rounded-lg bg-blue-600 px-3 py-1.5 font-medium text-white hover:bg-blue-500">Sign in</Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <p v-if="flash.success" role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/50 dark:text-emerald-300">{{ flash.success }}</p>
            <p v-if="flash.error || followError" role="alert" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800 dark:border-rose-800/50 dark:bg-rose-950/50 dark:text-rose-300">{{ flash.error || followError }}</p>
            <slot />
        </main>

        <footer class="mx-auto max-w-6xl px-4 pb-10 text-xs text-slate-500 dark:text-slate-400">
            Meeting times shown as "expected" are worked out from the lodge's published pattern and may change. Always confirm with the lodge before you travel.
        </footer>
    </div>
</template>
