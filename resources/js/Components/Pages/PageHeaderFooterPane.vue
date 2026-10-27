<script setup>
import AnnouncementBar from '@/Components/Site/AnnouncementBar.vue';
import LinkField from '@/Components/Blocks/Editors/LinkField.vue';
import PublicFooter from '@/Components/Site/PublicFooter.vue';
import PublicHeader from '@/Components/Site/PublicHeader.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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

const LABEL = 'block text-xs font-medium text-slate-700 dark:text-slate-300';
const HINT = 'text-xs text-slate-500 dark:text-slate-400';
const ERROR = 'text-xs text-rose-600 dark:text-rose-400';
const BAND = '-mx-3 sm:-mx-4 px-3 sm:px-4 py-3 border-y border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-base font-semibold text-slate-900 dark:text-white';
const CHECKBOX = 'w-4 h-4 rounded border-slate-300 accent-blue-600 cursor-pointer dark:border-slate-700';
const CHECK_LABEL = 'flex items-center gap-2.5 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-200 select-none';
const EMPTY = 'p-3 border border-dashed border-slate-300 dark:border-slate-700 rounded-2xl text-slate-500 dark:text-slate-400 text-center';
</script>

<template>
    <Card padding="lg" class="space-y-6">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4 flex-wrap gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Header and footer</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Choose a layout and toggle what appears in your public site's header and footer. Colours follow whichever theme is active.</p>
            </div>
            <Button :loading="headerFooterForm.processing" @click="submitHeaderFooter">
                <template #icon><UiIcon name="save" /></template>
                Save header and footer
            </Button>
        </div>

        <Alert v-if="isSavedSuccess" variant="success">Header and footer settings saved.</Alert>

        <form @submit.prevent="submitHeaderFooter" class="space-y-12 text-xs">

            <!-- Announcement bar -->
            <div class="space-y-5 -mx-6 sm:-mx-8 px-3 sm:px-4 pb-2">
                <h3 :class="BAND">Announcement bar</h3>
                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                    <input type="checkbox" v-model="headerFooterForm.announcement_enabled" :class="[CHECKBOX, 'mt-0.5']" />
                    <span>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Show a bar across the top of every page</span>
                        <span :class="[HINT, 'block mt-0.5']">For a date or notice everyone should see, such as "Installation on 12 April". You can give it a first and last day and it comes down by itself.</span>
                    </span>
                </label>

                <div v-if="headerFooterForm.announcement_enabled" class="space-y-4 p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <div :class="['rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700', !announcementPreview ? 'p-3 text-center text-slate-400 italic bg-white dark:bg-slate-900' : '']">
                        <AnnouncementBar v-if="announcementPreview" :announcement="announcementPreview" club-slug="preview" :interactive="false" />
                        <span v-else>Type your message to see the bar</span>
                    </div>

                    <Input
                        v-model="headerFooterForm.announcement_text"
                        label="Message"
                        maxlength="200"
                        placeholder="Installation on 12 April at 6pm"
                        :hint="`${headerFooterForm.announcement_text.length}/200 characters.`"
                        :error="headerFooterForm.errors.announcement_text"
                    />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input v-model="headerFooterForm.announcement_link_label" label="Link text (optional)" maxlength="60" placeholder="Book your place" />
                        <div class="space-y-1.5">
                            <span :class="LABEL">Link address (optional)</span>
                            <LinkField v-model="headerFooterForm.announcement_link_url" :pages="pages" :club="club" label="Announcement link" />
                            <p v-if="headerFooterForm.errors.announcement_link_url" :class="ERROR">{{ headerFooterForm.errors.announcement_link_url }}</p>
                        </div>
                        <Input v-model="headerFooterForm.announcement_starts_on" type="date" label="Show from (optional)" />
                        <Input v-model="headerFooterForm.announcement_ends_on" type="date" label="Show until, including this day (optional)" :error="headerFooterForm.errors.announcement_ends_on" />
                    </div>

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                        <div class="flex items-center gap-2" role="group" aria-label="Bar colour">
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Colour</span>
                            <button v-for="opt in announcementStyles" :key="opt.id" type="button" @click="headerFooterForm.announcement_style = opt.id" :aria-pressed="headerFooterForm.announcement_style === opt.id" :class="['px-3 py-1.5 rounded-xl border font-medium cursor-pointer', headerFooterForm.announcement_style === opt.id ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-300' : 'border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800']">{{ opt.name }}</button>
                        </div>
                        <label :class="CHECK_LABEL">
                            <input type="checkbox" v-model="headerFooterForm.announcement_dismissible" :class="CHECKBOX" />
                            <span>Visitors can close it</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Header Section -->
            <div class="space-y-5 -mx-6 sm:-mx-8 px-3 sm:px-4 pb-2">
                <h3 :class="BAND">Header</h3>

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
                            <span class="font-semibold text-slate-900 dark:text-white">{{ layout.name }}</span>
                            <Badge v-if="headerFooterForm.header_layout === layout.id" variant="info">Selected</Badge>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 mt-1">{{ layout.description }}</p>
                    </button>
                </div>

                <!-- Visibility toggles -->
                <div class="flex flex-wrap items-center gap-6 pt-1">
                    <label :class="CHECK_LABEL">
                        <input type="checkbox" v-model="headerFooterForm.header_show_logo" :class="CHECKBOX" />
                        <span>Show logo</span>
                    </label>
                    <label :class="CHECK_LABEL">
                        <input type="checkbox" v-model="headerFooterForm.header_show_tagline" :class="CHECKBOX" />
                        <span>Show tagline</span>
                    </label>
                    <label :class="CHECK_LABEL">
                        <input type="checkbox" v-model="headerFooterForm.header_show_account_links" :class="CHECKBOX" />
                        <span>Show login / member area button</span>
                    </label>
                    <label :class="CHECK_LABEL">
                        <input type="checkbox" v-model="headerFooterForm.header_cta_enabled" :class="CHECKBOX" />
                        <span>Show call-to-action button</span>
                    </label>
                </div>

                <div v-if="headerFooterForm.header_cta_enabled" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <Input v-model="headerFooterForm.header_cta_text" label="Button text" placeholder="e.g. Join our club" />
                    <div class="space-y-1.5">
                        <span :class="LABEL">Button link</span>
                        <LinkField v-model="headerFooterForm.header_cta_link" :pages="pages" :club="club" label="Header button" />
                        <p v-if="headerFooterForm.errors.header_cta_link" :class="ERROR">{{ headerFooterForm.errors.header_cta_link }}</p>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="space-y-5 -mx-6 sm:-mx-8 px-3 sm:px-4 pb-2">
                <h3 :class="BAND">Footer</h3>

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
                            <span class="font-semibold text-slate-900 dark:text-white">{{ layout.name }}</span>
                            <Badge v-if="headerFooterForm.footer_layout === layout.id" variant="info">Selected</Badge>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 mt-1">{{ layout.description }}</p>
                    </button>
                </div>

                <!-- Text under the club name, on the left of the footer -->
                <Textarea
                    v-model="headerFooterForm.footer_about_text"
                    label="Text under the club name"
                    :rows="3"
                    maxlength="300"
                    placeholder="e.g. Meeting at Stockton Masonic Hall, 4th Thursday, September to May."
                    :hint="`${headerFooterForm.footer_about_text.length}/300. Shown under your club's name at the left of the footer. Leave blank to show your club's tagline instead.`"
                    class="min-w-0"
                />

                <!-- Navigation links (Columns layout only): a column of the site's menu pages -->
                <div v-if="headerFooterForm.footer_layout === 'columns'" class="space-y-3 min-w-0">
                    <div class="flex items-center justify-between gap-2 h-8">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Navigation links</label>
                        <div class="flex items-center gap-3">
                            <Button v-if="headerFooterForm.footer_show_nav" variant="secondary" size="sm" @click="$emit('choose-nav')">Choose pages</Button>
                            <ToggleSwitch v-model="headerFooterForm.footer_show_nav" label="Show navigation links in the footer" />
                        </div>
                    </div>
                    <p :class="HINT">A "Navigate" column listing pages from your top menu. Use Choose pages to pick which ones. To add a page that isn't in the top menu, tick "Show in footer menu" on that page instead.</p>
                    <div v-if="headerFooterForm.footer_show_nav" class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ headerFooterForm.footer_nav_page_ids === null ? `All ${publishedNavPages.length} menu pages` : `${footerNavPages.length} of ${publishedNavPages.length} menu pages` }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-for="navPage in footerNavPages" :key="navPage.id">{{ navPage.title }}</Badge>
                            <span v-if="!footerNavPages.length" class="text-slate-400 italic">{{ publishedNavPages.length ? 'No pages chosen, so the column will be empty.' : 'No pages are in the top menu yet.' }}</span>
                        </div>
                    </div>
                    <p v-else :class="EMPTY">Hidden. The footer won't list your pages.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                <div :class="footerDetailsSideBySide ? '' : 'sm:col-span-2'" class="space-y-3 min-w-0">
                    <div class="flex items-center justify-between gap-2 h-8">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Social links</label>
                        <ToggleSwitch v-model="headerFooterForm.footer_show_social" label="Show social links in the footer" />
                    </div>
                    <p :class="[HINT, 'sm:min-h-[3.25rem]']">Where visitors can follow you, such as your Facebook page or YouTube channel. They show in the footer under "Follow". Leave a box empty to hide that link.</p>

                    <p v-if="!headerFooterForm.footer_show_social" :class="EMPTY">Hidden. The footer won't show social links. Your addresses are kept.</p>
                    <div v-else :class="footerDetailsSideBySide ? '' : 'sm:grid-cols-3'" class="grid grid-cols-1 gap-3 p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <Input v-model="headerFooterForm.social_facebook" label="Facebook" placeholder="https://facebook.com/yourclub" class="min-w-0" />
                    <Input v-model="headerFooterForm.social_instagram" label="Instagram" placeholder="https://instagram.com/yourclub" class="min-w-0" />
                    <Input v-model="headerFooterForm.social_twitter" label="X (Twitter)" placeholder="https://x.com/yourclub" :error="headerFooterForm.errors.social_twitter" class="min-w-0" />
                    <Input v-model="headerFooterForm.social_youtube" label="YouTube" placeholder="https://youtube.com/@yourclub" :error="headerFooterForm.errors.social_youtube" class="min-w-0" />
                    <Input v-model="headerFooterForm.social_linkedin" label="LinkedIn" placeholder="https://linkedin.com/company/yourclub" :error="headerFooterForm.errors.social_linkedin" class="min-w-0" />
                    <Input v-model="headerFooterForm.social_tiktok" label="TikTok" placeholder="https://tiktok.com/@yourclub" :error="headerFooterForm.errors.social_tiktok" class="min-w-0" />
                    <Input v-model="headerFooterForm.social_whatsapp" label="WhatsApp" placeholder="https://chat.whatsapp.com/…" :error="headerFooterForm.errors.social_whatsapp" class="min-w-0" />
                </div>
                </div>

                <!-- Custom footer link columns (Columns layout only) -->
                <div v-if="headerFooterForm.footer_layout === 'columns'" class="space-y-3 min-w-0">
                    <div class="flex items-center justify-between gap-2 h-8">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Custom link columns</label>
                        <div class="flex items-center gap-3">
                        <!-- One column is all a lodge normally needs, so the button only shows when there is none (after deleting it) -->
                        <Button
                            v-if="headerFooterForm.footer_show_custom_columns && !headerFooterForm.footer_link_columns.length"
                            variant="secondary"
                            size="sm"
                            @click="addFooterColumn"
                            :disabled="headerFooterForm.footer_link_columns.length >= maxFooterColumns"
                        >
                            <template #icon><UiIcon name="plus" :size="14" /></template>
                            Add column
                        </Button>
                        <ToggleSwitch v-model="headerFooterForm.footer_show_custom_columns" label="Show custom link columns in the footer" />
                        </div>
                    </div>
                    <p :class="[HINT, 'sm:min-h-[3.25rem]']">Add extra footer columns for things like "Useful links" — e.g. your Grand Lodge, Province, or a data protection notice.</p>

                    <p v-if="!headerFooterForm.footer_show_custom_columns" :class="EMPTY">Hidden. The footer won't show these links. Your columns are kept.</p>
                    <div v-else-if="!headerFooterForm.footer_link_columns.length" :class="EMPTY">
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
                            <Input v-model="column.title" :aria-label="`Column ${colIndex + 1} heading`" placeholder="Heading, e.g. Useful links" class="flex-1 min-w-0" />
                            <Button variant="ghost" size="sm" class="!px-2" @click="removeFooterColumn(colIndex)" title="Remove column" aria-label="Remove column">
                                <UiIcon name="trash" />
                            </Button>
                        </div>

                        <div v-for="(link, linkIndex) in column.links" :key="link.id" class="flex items-start gap-1.5">
                          <div class="flex-1 min-w-0 space-y-1.5">
                            <Input v-model="link.label" :aria-label="`Column ${colIndex + 1}, link ${linkIndex + 1} text`" placeholder="Link text" class="min-w-0" />
                            <Input v-model="link.url" :aria-label="`Column ${colIndex + 1}, link ${linkIndex + 1} URL`" placeholder="https://... or /site/your-club/page" class="min-w-0" />
                          </div>
                            <Button variant="ghost" size="sm" class="!px-2" @click="removeFooterLink(colIndex, linkIndex)" title="Remove link" aria-label="Remove link">
                                <UiIcon name="x" />
                            </Button>
                        </div>

                        <Button
                            variant="secondary"
                            size="sm"
                            @click="addFooterLink(colIndex)"
                            :disabled="column.links.length >= maxFooterColumnLinks"
                        >
                            <template #icon><UiIcon name="plus" :size="14" /></template>
                            Add link
                        </Button>
                    </div>
                    </div>
                </div>

                </div>

                <!-- Copyright line: its own panel, with the year kept out of the text -->
                <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 p-4 sm:p-5 space-y-3">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Copyright line</h4>
                        <p :class="[HINT, 'mt-0.5']">The © symbol and the year are added for you and change on their own every January, so you never need to edit them.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
                        <div class="space-y-1.5">
                            <span :class="LABEL">Symbol and year</span>
                            <div class="px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-500 dark:text-slate-400 select-none" aria-label="Symbol and year, added automatically">© {{ currentYear }} <span class="font-normal text-[10px]">(automatic)</span></div>
                        </div>
                        <Input v-model="headerFooterForm.footer_copyright_holder" label="Copyright holder" maxlength="150" :placeholder="club.name" :hint="`Leave blank to use ${club.name}.`" />
                        <Input v-model="headerFooterForm.footer_copyright_text" label="Wording after it" maxlength="200" placeholder="All rights reserved." hint="Optional." />
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Shown as: <strong class="font-semibold text-slate-800 dark:text-slate-100">{{ copyrightPreview }}</strong></p>
                </div>
            </div>

            <div class="pt-4 flex justify-end">
                <Button type="submit" variant="secondary" :loading="headerFooterForm.processing">
                    <template #icon><UiIcon name="save" /></template>
                    Save header and footer
                </Button>
            </div>
        </form>
    </Card>
</template>
