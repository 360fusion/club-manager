<script setup>
import AnnouncementBar from '@/Components/Site/AnnouncementBar.vue';
import LinkField from '@/Components/Blocks/Editors/LinkField.vue';
import PublicFooter from '@/Components/Site/PublicFooter.vue';
import PublicHeader from '@/Components/Site/PublicHeader.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';

// A section of the website builder page, in its own file so the page stays readable.
defineEmits(['choose-nav']);

defineProps({
    club: { type: [Object, Array], default: undefined },
    pages: { type: [Object, Array], default: undefined },
    announcementStyles: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    footerLayouts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    headerLayouts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    maxFooterColumns: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    maxFooterColumnLinks: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    addFooterColumn: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    addFooterLink: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    announcementPreview: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    copyrightPreview: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    currentYear: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    footerDetailsSideBySide: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    footerNavPages: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    footerPinnedPages: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    form: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    headerFooterForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isSavedSuccess: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    liveThemeClasses: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    publishedNavPages: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    removeFooterColumn: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    removeFooterLink: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitHeaderFooter: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 flex-wrap gap-3">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">🧭 Header & Footer</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Choose a layout and toggle what appears in your public site's header and footer. Colours follow whichever theme is active.</p>
            </div>
            <button
                type="button"
                @click="submitHeaderFooter"
                :disabled="headerFooterForm.processing"
                class="py-2.5 px-5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md transition-all flex items-center gap-2 disabled:opacity-50 cursor-pointer"
            >
                <span>💾 Save header & footer</span>
            </button>
        </div>

        <!-- Saved Success Alert -->
        <div v-if="isSavedSuccess" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-2xl text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center gap-2">
            <span>✅ Header & footer settings saved successfully!</span>
        </div>

        <form @submit.prevent="submitHeaderFooter" class="space-y-12 text-xs">

            <!-- Announcement bar -->
            <div class="space-y-5 -mx-6 sm:-mx-8 px-3 sm:px-4 pb-2">
                <h3 class="-mx-3 sm:-mx-4 px-3 sm:px-4 py-3 border-b-2 text-sm font-extrabold bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-200">Announcement Bar</h3>
                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                    <input type="checkbox" v-model="headerFooterForm.announcement_enabled" class="w-4 h-4 mt-0.5 rounded accent-blue-600 cursor-pointer" />
                    <span>
                        <span class="font-bold text-slate-700 dark:text-slate-200">Show a bar across the top of every page</span>
                        <span class="block text-[10px] text-slate-400 mt-0.5">For a date or notice everyone should see, such as "Installation on 12 April". You can give it a first and last day and it comes down by itself.</span>
                    </span>
                </label>

                <div v-if="headerFooterForm.announcement_enabled" class="space-y-4 p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <div :class="['rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700', !announcementPreview ? 'p-3 text-center text-slate-400 italic bg-white dark:bg-slate-900' : '']">
                        <AnnouncementBar v-if="announcementPreview" :announcement="announcementPreview" club-slug="preview" :interactive="false" />
                        <span v-else>Type your message to see the bar</span>
                    </div>

                    <div>
                        <label for="announcement-text" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Message</label>
                        <input id="announcement-text" v-model="headerFooterForm.announcement_text" type="text" maxlength="200" placeholder="Installation on 12 April at 6pm" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        <p class="text-[10px] text-slate-400 mt-1">{{ headerFooterForm.announcement_text.length }}/200 characters.</p>
                        <p v-if="headerFooterForm.errors.announcement_text" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.announcement_text }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="announcement-link-label" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Link text <span class="font-normal text-slate-400">(optional)</span></label>
                            <input id="announcement-link-label" v-model="headerFooterForm.announcement_link_label" type="text" maxlength="60" placeholder="Book your place" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <span class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Link address <span class="font-normal text-slate-400">(optional)</span></span>
                            <LinkField v-model="headerFooterForm.announcement_link_url" :pages="pages" :club="club" label="Announcement link" />
                            <p v-if="headerFooterForm.errors.announcement_link_url" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.announcement_link_url }}</p>
                        </div>
                        <div>
                            <label for="announcement-starts" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Show from <span class="font-normal text-slate-400">(optional)</span></label>
                            <input id="announcement-starts" v-model="headerFooterForm.announcement_starts_on" type="date" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label for="announcement-ends" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Show until, including this day <span class="font-normal text-slate-400">(optional)</span></label>
                            <input id="announcement-ends" v-model="headerFooterForm.announcement_ends_on" type="date" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                            <p v-if="headerFooterForm.errors.announcement_ends_on" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.announcement_ends_on }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                        <div class="flex items-center gap-2" role="group" aria-label="Bar colour">
                            <span class="font-bold text-slate-700 dark:text-slate-200">Colour</span>
                            <button v-for="opt in announcementStyles" :key="opt.id" type="button" @click="headerFooterForm.announcement_style = opt.id" :aria-pressed="headerFooterForm.announcement_style === opt.id" :class="['px-3 py-1.5 rounded-xl border font-bold cursor-pointer', headerFooterForm.announcement_style === opt.id ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-300' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300']">{{ opt.name }}</button>
                        </div>
                        <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                            <input type="checkbox" v-model="headerFooterForm.announcement_dismissible" class="w-4 h-4 rounded accent-blue-600 cursor-pointer" />
                            <span>Visitors can close it</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Header Section -->
            <div class="space-y-5 -mx-6 sm:-mx-8 px-3 sm:px-4 pb-2">
                <h3 class="-mx-3 sm:-mx-4 px-3 sm:px-4 py-3 border-b-2 text-sm font-extrabold bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-800/60 text-blue-900 dark:text-blue-200">Header</h3>

                <!-- Live preview -->
                <div :class="['rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden [&>header]:!static [&>header]:!z-auto', liveThemeClasses.wrapper]" :style="liveThemeClasses.cssVars">
                    <PublicHeader
                        :club="club"
                        :theme="liveThemeClasses"
                        :navigation="publishedNavPages"
                        :current-page="{ is_homepage: true }"
                        :settings="headerFooterForm.data()"
                        :interactive="false"
                    />
                </div>

                <!-- Layout picker -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button
                        v-for="layout in headerLayouts"
                        :key="layout.id"
                        type="button"
                        @click="headerFooterForm.header_layout = layout.id"
                        :class="[
                            'text-left p-4 rounded-2xl border transition-all cursor-pointer',
                            headerFooterForm.header_layout === layout.id ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50/20 dark:bg-blue-950/20' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'
                        ]"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-slate-900 dark:text-white">{{ layout.name }}</span>
                            <span v-if="headerFooterForm.header_layout === layout.id" class="text-[10px] font-black text-blue-600 dark:text-blue-400">✓ SELECTED</span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 mt-1">{{ layout.description }}</p>
                    </button>
                </div>

                <!-- Visibility toggles -->
                <div class="flex flex-wrap items-center gap-6 pt-1">
                    <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                        <input type="checkbox" v-model="headerFooterForm.header_show_logo" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                        <span>Show Logo</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                        <input type="checkbox" v-model="headerFooterForm.header_show_tagline" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                        <span>Show Tagline</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                        <input type="checkbox" v-model="headerFooterForm.header_show_account_links" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                        <span>Show Login / Member Area button</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                        <input type="checkbox" v-model="headerFooterForm.header_cta_enabled" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                        <span>Show Call-to-Action Button</span>
                    </label>
                </div>

                <div v-if="headerFooterForm.header_cta_enabled" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <div>
                        <label for="header-cta-text" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Button Text</label>
                        <input id="header-cta-text" v-model="headerFooterForm.header_cta_text" type="text" placeholder="e.g. Join Our Club" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <span class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Button Link</span>
                        <LinkField v-model="headerFooterForm.header_cta_link" :pages="pages" :club="club" label="Header button" />
                        <p v-if="headerFooterForm.errors.header_cta_link" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.header_cta_link }}</p>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="space-y-5 -mx-6 sm:-mx-8 px-3 sm:px-4 pb-2">
                <h3 class="-mx-3 sm:-mx-4 px-3 sm:px-4 py-3 border-b-2 text-sm font-extrabold bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800/60 text-emerald-900 dark:text-emerald-200">Footer</h3>

                <!-- Live preview -->
                <div :class="['rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden', liveThemeClasses.wrapper]" :style="liveThemeClasses.cssVars">
                    <PublicFooter
                        :club="club"
                        :theme="liveThemeClasses"
                        :navigation="publishedNavPages"
                        :footer-links="footerPinnedPages"
                        :settings="headerFooterForm.data()"
                        :interactive="false"
                    />
                </div>

                <!-- Layout picker -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button
                        v-for="layout in footerLayouts"
                        :key="layout.id"
                        type="button"
                        @click="headerFooterForm.footer_layout = layout.id"
                        :class="[
                            'text-left p-4 rounded-2xl border transition-all cursor-pointer',
                            headerFooterForm.footer_layout === layout.id ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50/20 dark:bg-blue-950/20' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'
                        ]"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-slate-900 dark:text-white">{{ layout.name }}</span>
                            <span v-if="headerFooterForm.footer_layout === layout.id" class="text-[10px] font-black text-blue-600 dark:text-blue-400">✓ SELECTED</span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 mt-1">{{ layout.description }}</p>
                    </button>
                </div>

                <!-- Text under the club name, on the left of the footer -->
                <div class="space-y-2 min-w-0">
                    <label for="footer-about-text" class="block font-bold text-slate-700 dark:text-slate-200">Text under the club name</label>
                    <textarea id="footer-about-text" v-model="headerFooterForm.footer_about_text" rows="3" maxlength="300" placeholder="e.g. Meeting at Stockton Masonic Hall, 4th Thursday, September to May." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    <p class="text-[10px] text-slate-400">{{ headerFooterForm.footer_about_text.length }}/300. Shown under your club's name at the left of the footer. Leave blank to show your club's tagline instead.</p>
                </div>

                <!-- Navigation links (Columns layout only): a column of the site's menu pages -->
                <div v-if="headerFooterForm.footer_layout === 'columns'" class="space-y-3 min-w-0">
                    <div class="flex items-center justify-between gap-2 h-8">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Navigation Links</label>
                        <div class="flex items-center gap-3">
                            <button v-if="headerFooterForm.footer_show_nav" type="button" @click="$emit('choose-nav')" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-[11px] transition-colors cursor-pointer">Choose pages</button>
                            <ToggleSwitch v-model="headerFooterForm.footer_show_nav" label="Show navigation links in the footer" />
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-400">A "Navigate" column listing pages from your top menu. Use Choose Pages to pick which ones. To add a page that isn't in the top menu, tick "Show in Footer Menu" on that page instead.</p>
                    <div v-if="headerFooterForm.footer_show_nav" class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ headerFooterForm.footer_nav_page_ids === null ? `All ${publishedNavPages.length} menu pages` : `${footerNavPages.length} of ${publishedNavPages.length} menu pages` }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="navPage in footerNavPages" :key="navPage.id" class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-[11px] font-semibold text-slate-600 dark:text-slate-300">{{ navPage.title }}</span>
                            <span v-if="!footerNavPages.length" class="text-slate-400 italic">{{ publishedNavPages.length ? 'No pages chosen, so the column will be empty.' : 'No pages are in the top menu yet.' }}</span>
                        </div>
                    </div>
                    <p v-else class="p-3 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl text-slate-400 text-center">Hidden. The footer won't list your pages.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                <div :class="footerDetailsSideBySide ? '' : 'sm:col-span-2'" class="space-y-3 min-w-0">
                    <div class="flex items-center justify-between gap-2 h-8">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Social Links</label>
                        <ToggleSwitch v-model="headerFooterForm.footer_show_social" label="Show social links in the footer" />
                    </div>
                    <p class="text-[10px] text-slate-400 sm:min-h-[3.25rem]">Where visitors can follow you, such as your Facebook page or YouTube channel. They show in the footer under "Follow". Leave a box empty to hide that link.</p>

                    <p v-if="!headerFooterForm.footer_show_social" class="p-3 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl text-slate-400 text-center">Hidden. The footer won't show social links. Your addresses are kept.</p>
                    <div v-else :class="footerDetailsSideBySide ? '' : 'sm:grid-cols-3'" class="grid grid-cols-1 gap-3 p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <div>
                        <label for="social-facebook" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Facebook</label>
                        <input id="social-facebook" v-model="headerFooterForm.social_facebook" type="text" placeholder="https://facebook.com/yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label for="social-instagram" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Instagram</label>
                        <input id="social-instagram" v-model="headerFooterForm.social_instagram" type="text" placeholder="https://instagram.com/yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label for="social-twitter" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">X (Twitter)</label>
                        <input id="social-twitter" v-model="headerFooterForm.social_twitter" type="text" placeholder="https://x.com/yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        <p v-if="headerFooterForm.errors.social_twitter" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.social_twitter }}</p>
                    </div>
                    <div>
                        <label for="social-youtube" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">YouTube</label>
                        <input id="social-youtube" v-model="headerFooterForm.social_youtube" type="text" placeholder="https://youtube.com/@yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        <p v-if="headerFooterForm.errors.social_youtube" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.social_youtube }}</p>
                    </div>
                    <div>
                        <label for="social-linkedin" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">LinkedIn</label>
                        <input id="social-linkedin" v-model="headerFooterForm.social_linkedin" type="text" placeholder="https://linkedin.com/company/yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        <p v-if="headerFooterForm.errors.social_linkedin" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.social_linkedin }}</p>
                    </div>
                    <div>
                        <label for="social-tiktok" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">TikTok</label>
                        <input id="social-tiktok" v-model="headerFooterForm.social_tiktok" type="text" placeholder="https://tiktok.com/@yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        <p v-if="headerFooterForm.errors.social_tiktok" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.social_tiktok }}</p>
                    </div>
                    <div>
                        <label for="social-whatsapp" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">WhatsApp</label>
                        <input id="social-whatsapp" v-model="headerFooterForm.social_whatsapp" type="text" placeholder="https://chat.whatsapp.com/…" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        <p v-if="headerFooterForm.errors.social_whatsapp" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.social_whatsapp }}</p>
                    </div>
                </div>
                </div>

                <!-- Custom footer link columns (Columns layout only) -->
                <div v-if="headerFooterForm.footer_layout === 'columns'" class="space-y-3 min-w-0">
                    <div class="flex items-center justify-between gap-2 h-8">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Custom Link Columns</label>
                        <div class="flex items-center gap-3">
                        <!-- One column is all a lodge normally needs, so the button only shows when there is none (after deleting it) -->
                        <button
                            v-if="headerFooterForm.footer_show_custom_columns && !headerFooterForm.footer_link_columns.length"
                            type="button"
                            @click="addFooterColumn"
                            :disabled="headerFooterForm.footer_link_columns.length >= maxFooterColumns"
                            class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-[11px] transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            ➕ Add column
                        </button>
                        <ToggleSwitch v-model="headerFooterForm.footer_show_custom_columns" label="Show custom link columns in the footer" />
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-400 sm:min-h-[3.25rem]">Add extra footer columns for things like "Useful Links" — e.g. your Grand Lodge, Province, or a Data Protection Notice.</p>

                    <p v-if="!headerFooterForm.footer_show_custom_columns" class="p-3 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl text-slate-400 text-center">Hidden. The footer won't show these links. Your columns are kept.</p>
                    <div v-else-if="!headerFooterForm.footer_link_columns.length" class="p-4 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 text-slate-400 text-center">
                        No custom columns yet.
                    </div>

                    <!-- Side by side, as they sit in the site's footer -->
                    <div v-if="headerFooterForm.footer_show_custom_columns" :class="footerDetailsSideBySide ? '' : 'sm:grid-cols-3'" class="grid grid-cols-1 gap-4 items-start">
                    <div
                        v-for="(column, colIndex) in headerFooterForm.footer_link_columns"
                        :key="column.id"
                        class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-3 min-w-0"
                    >
                        <div class="flex items-center gap-2">
                            <input v-model="column.title" type="text" :aria-label="`Column ${colIndex + 1} heading`" placeholder="Heading, e.g. Useful Links" class="flex-1 min-w-0 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                            <button type="button" @click="removeFooterColumn(colIndex)" class="px-3 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-600 dark:text-rose-400 font-bold text-[11px] transition-colors cursor-pointer" title="Remove column">
                                🗑️
                            </button>
                        </div>

                        <div v-for="(link, linkIndex) in column.links" :key="link.id" class="flex items-start gap-1.5">
                          <div class="flex-1 min-w-0 space-y-1.5">
                            <input v-model="link.label" type="text" :aria-label="`Column ${colIndex + 1}, link ${linkIndex + 1} text`" placeholder="Link text" class="w-full min-w-0 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                            <input v-model="link.url" type="text" :aria-label="`Column ${colIndex + 1}, link ${linkIndex + 1} URL`" placeholder="https://... or /site/your-club/page" class="w-full min-w-0 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                          </div>
                            <button type="button" @click="removeFooterLink(colIndex, linkIndex)" class="px-2.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 font-bold text-[11px] transition-colors cursor-pointer" title="Remove link">
                                ✕
                            </button>
                        </div>

                        <button
                            type="button"
                            @click="addFooterLink(colIndex)"
                            :disabled="column.links.length >= maxFooterColumnLinks"
                            class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-600 dark:text-slate-300 font-bold text-[11px] transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            ➕ Add link
                        </button>
                    </div>
                    </div>
                </div>

                </div>

                <!-- Copyright line: its own panel, with the year kept out of the text -->
                <div class="rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/40 p-4 sm:p-5 space-y-3">
                    <div>
                        <h4 class="text-sm font-extrabold text-slate-900 dark:text-white">Copyright Line</h4>
                        <p class="text-[10px] text-slate-400 mt-0.5">The © symbol and the year are added for you and change on their own every January, so you never need to edit them.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
                        <div>
                            <span class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Symbol and year</span>
                            <div class="px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-bold text-slate-500 dark:text-slate-400 select-none" aria-label="Symbol and year, added automatically">© {{ currentYear }} <span class="font-normal text-[10px]">(automatic)</span></div>
                        </div>
                        <div>
                            <label for="footer-copyright-holder" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Copyright holder</label>
                            <input id="footer-copyright-holder" v-model="headerFooterForm.footer_copyright_holder" type="text" maxlength="150" :placeholder="club.name" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                            <p class="text-[10px] text-slate-400 mt-1">Leave blank to use {{ club.name }}.</p>
                        </div>
                        <div>
                            <label for="footer-copyright-text" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Wording after it</label>
                            <input id="footer-copyright-text" v-model="headerFooterForm.footer_copyright_text" type="text" maxlength="200" placeholder="All rights reserved." class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                            <p class="text-[10px] text-slate-400 mt-1">Optional.</p>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Shown as: <strong class="text-slate-800 dark:text-slate-100">{{ copyrightPreview }}</strong></p>
                </div>
            </div>

            <div class="pt-4 flex justify-end">
                <button
                    type="submit"
                    :disabled="headerFooterForm.processing"
                    class="py-3 px-6 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-lg shadow-blue-600/20 transition-all flex items-center gap-2 disabled:opacity-50 cursor-pointer"
                >
                    <span>💾 Save header & footer</span>
                </button>
            </div>
        </form>
    </div>
</template>
