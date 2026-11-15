<script setup>
// The website builder's left navigation (a dropdown on small screens, a side card on large ones), in its own file so the page stays readable.
import { getPageIcon } from '@/Utils/pageBuilderUrl';
import Card from '@/Components/Ui/Card.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
    pages: { type: Array, default: () => [] },
    activeNavSelection: { type: String, default: '' },
    pageViewMode: { type: String, default: 'edit' },
    selectionUrl: { type: Function, required: true },
    navClick: { type: Function, required: true },
    requestNavigation: { type: Function, required: true },
});

// getPageIcon (Utils/pageBuilderUrl) answers with an emoji; the builder's own lists draw the matching interface icon.
const PAGE_ICON_NAMES = { '🏠': 'home', 'ℹ️': 'info', '🤝': 'users', '📰': 'document', '✉️': 'mail' };
const pageIconName = (page) => PAGE_ICON_NAMES[getPageIcon(page)] || 'document';

// A link in the side navigation, highlighted while its screen is showing.
const navLinkClass = (isActive) => [
    'w-full px-3 py-2 rounded-xl transition-colors flex items-center gap-2.5 cursor-pointer text-left',
    isActive ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white',
];

const MANAGEMENT_LINKS = [
    { selection: 'settings', icon: 'settings', label: 'Website & SEO settings' },
    { selection: 'header_footer', icon: 'resize', label: 'Header & footer' },
    { selection: 'themes', icon: 'palette', label: 'Website themes' },
    { selection: 'redirects', icon: 'link', label: 'Redirects' },
    { selection: 'publish', icon: 'upload', label: 'Publish as files' },
    { selection: 'overview', icon: 'clipboard', label: 'Manage pages' },
];
</script>

<template>
    <!-- Left Sidebar Navigation Panel -->
    <div class="lg:col-span-1 space-y-4">

        <!-- Mobile Page Selector Dropdown -->
        <Card padding="sm" class="lg:hidden space-y-1.5">
            <label for="mobile-page-selector" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Website page</label>
            <!-- A raw select: the kit's Select has no option groups. -->
            <select id="mobile-page-selector" :value="activeNavSelection" @change="e => requestNavigation(e.target.value)" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white cursor-pointer">
                <optgroup label="Public website pages">
                    <option v-for="p in pages" :key="p.id" :value="String(p.id)">
                        {{ p.title }} {{ p.is_homepage ? '(homepage)' : '' }}
                    </option>
                </optgroup>
                <optgroup label="Management">
                    <option value="settings">Website & SEO settings</option>
                    <option value="header_footer">Header & footer</option>
                    <option value="themes">Website themes</option>
                    <option value="redirects">Redirects</option>
                    <option value="publish">Publish as files</option>
                    <option value="new">Add a new custom page</option>
                    <option value="overview">Manage pages</option>
                </optgroup>
            </select>
        </Card>

        <!-- Desktop Vertical Sidebar Navigation Card -->
        <Card padding="none" class="hidden lg:block p-3 space-y-5 sticky top-6">

            <!-- Navigation Pages Group -->
            <div>
                <div class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5 flex items-center justify-between">
                    <span>Navigation pages</span>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium normal-case tracking-normal text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ pages.length }} pages</span>
                </div>

                <div class="space-y-1 text-sm font-medium">
                    <a
                        v-for="p in pages"
                        :key="p.id"
                        :href="selectionUrl(p.id, pageViewMode)"
                        :aria-current="activeNavSelection === String(p.id) ? 'page' : undefined"
                        @click="navClick($event, p.id, pageViewMode || 'edit')"
                        :class="navLinkClass(activeNavSelection === String(p.id))"
                    >
                        <UiIcon :name="pageIconName(p)" />
                        <span class="truncate">{{ p.title }}</span>
                    </a>
                </div>

                <!-- Add New Page Quick Action -->
                <a
                    :href="selectionUrl('new', 'edit')"
                    @click="navClick($event, 'new', 'edit')"
                    :class="[
                        'w-full mt-3 px-3 py-2 rounded-xl border transition-colors text-sm font-medium flex items-center justify-center gap-2 cursor-pointer',
                        activeNavSelection === 'new'
                            ? 'bg-blue-600 border-transparent text-white'
                            : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50 hover:border-slate-400 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800'
                    ]"
                >
                    <UiIcon name="plus" />
                    <span>Add new custom page</span>
                </a>
            </div>

            <!-- Management & Tools Group -->
            <div class="border-t border-slate-200 dark:border-slate-800 pt-4 space-y-1">
                <div class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Management</div>
                <div class="space-y-1 text-sm font-medium">
                    <a
                        v-for="link in MANAGEMENT_LINKS"
                        :key="link.selection"
                        :href="selectionUrl(link.selection)"
                        :aria-current="activeNavSelection === link.selection ? 'page' : undefined"
                        @click="navClick($event, link.selection)"
                        :class="navLinkClass(activeNavSelection === link.selection)"
                    >
                        <UiIcon :name="link.icon" /> {{ link.label }}
                    </a>
                </div>
            </div>

        </Card>
    </div>
</template>
