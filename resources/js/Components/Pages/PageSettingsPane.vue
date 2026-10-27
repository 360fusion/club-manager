<script setup>
import { computed } from 'vue';
import DomainSetupGuide from '@/Components/DomainSetupGuide.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// A section of the website builder page, in its own file so the page stays readable.
const props = defineProps({
    club: { type: [Object, Array], default: undefined },
    pages: { type: [Object, Array], default: undefined },
    websiteSettings: { type: [Object, Array], default: undefined },
    checkDomainNow: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    checkingDomain: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    domainExample: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    form: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isSavedSuccess: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openMediaLibrary: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    requestNavigation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    settingsForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitWebsiteSettings: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const ANALYTICS_OPTIONS = [
    { value: 'none', label: 'None' },
    { value: 'plausible', label: 'Plausible (no cookies)' },
    { value: 'google', label: 'Google Analytics 4' },
];

const REVISION_AGE_OPTIONS = [
    { value: 30, label: '1 month' },
    { value: 90, label: '3 months' },
    { value: 180, label: '6 months' },
    { value: 365, label: '1 year' },
    { value: 0, label: 'Never (only the number above applies)' },
];

const notFoundOptions = computed(() => [
    { value: '', label: 'The standard "not found" screen' },
    ...props.pages.filter((pg) => pg.is_published && !pg.is_members_only).map((p) => ({ value: p.id, label: p.title })),
]);

const LABEL = 'block text-xs font-medium text-slate-700 dark:text-slate-300';
const HINT = 'text-xs text-slate-500 dark:text-slate-400';
const SECTION_TITLE = 'text-base font-semibold text-slate-900 dark:text-white';
const CHECKBOX = 'w-4 h-4 mt-0.5 rounded border-slate-300 accent-blue-600 cursor-pointer dark:border-slate-700';
</script>

<template>
    <Card padding="lg" class="space-y-6">
        <div class="flex items-center justify-between gap-3 flex-wrap border-b border-slate-200 dark:border-slate-800 pb-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Website and SEO settings</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Manage the custom domain, search engine optimisation and public contact details.</p>
            </div>
            <Button :loading="settingsForm.processing" @click="submitWebsiteSettings">
                <template #icon><UiIcon name="save" /></template>
                Save website settings
            </Button>
        </div>

        <Alert v-if="isSavedSuccess" variant="success">Website settings saved.</Alert>

        <form @submit.prevent="submitWebsiteSettings" class="space-y-6 text-xs">

            <!-- Section 1: Custom Domain Setup -->
            <div class="space-y-4">
                <h3 :class="SECTION_TITLE">Custom domain</h3>
                <div class="p-5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-4">
                    <div>
                        <Input v-model="settingsForm.custom_domain" label="Custom domain name" :placeholder="`e.g. ${domainExample}`" class="sm:w-96 font-mono" />
                        <p :class="[HINT, 'mt-1']">Connect your custom domain (e.g. <code class="bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-100 px-1 py-0.5 rounded font-medium">{{ domainExample }}</code>) to your club portal.</p>
                    </div>

                    <DomainSetupGuide
                        v-if="settingsForm.custom_domain"
                        :domain="settingsForm.custom_domain"
                        :status="websiteSettings.domain_status"
                        :verified-at="websiteSettings.domain_verified_at"
                        :instructions="websiteSettings.domain_instructions"
                        :ownership="websiteSettings.domain_ownership"
                        :provider="websiteSettings.domain_provider"
                        :saved-domain="websiteSettings.custom_domain"
                        :checking="checkingDomain"
                        @check="checkDomainNow"
                    />

                    <label v-if="websiteSettings.custom_domain" class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" v-model="settingsForm.site_redirect_to_domain" :class="CHECKBOX" />
                        <span>
                            <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">Send visitors to {{ websiteSettings.custom_domain }}</span>
                            <span :class="[HINT, 'block mt-0.5']">Your pages are on your domain once it is Active. Tick this to forward the address <code class="font-mono">/site/{{ club.slug }}</code> there too; leave it unticked to keep the pages at both addresses.</span>
                        </span>
                    </label>
                </div>
            </div>

            <hr class="border-slate-200 dark:border-slate-800" />

            <!-- Section 2: Search Engine Optimization (SEO) -->
            <div class="space-y-4">
                <h3 :class="SECTION_TITLE">Search engine optimisation (SEO)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input v-model="settingsForm.seo_title_suffix" label="Page title suffix" placeholder="| The Lodge of Fraternity" hint="Appended to page titles in browser tabs and search engines." />

                    <Textarea v-model="settingsForm.seo_meta_description" label="Default meta description" :rows="3" placeholder="Official homepage for events, membership, news, and history." class="sm:col-span-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <label for="site-share-image" :class="LABEL">Default share image</label>
                            <div class="flex items-center gap-1.5">
                                <Button v-if="settingsForm.share_image_url" variant="ghost" size="sm" @click="settingsForm.share_image_url = ''">
                                    <template #icon><UiIcon name="trash" :size="14" /></template>
                                    Clear
                                </Button>
                                <Button variant="secondary" size="sm" @click="openMediaLibrary('site_share_image', null, 'pages')">
                                    <template #icon><UiIcon name="folder" :size="14" /></template>
                                    Media library
                                </Button>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <Input id="site-share-image" v-model="settingsForm.share_image_url" placeholder="https://… or choose from the media library" :error="settingsForm.errors.share_image_url" class="flex-1 min-w-0 font-mono" />
                            <img v-if="settingsForm.share_image_url" :src="settingsForm.share_image_url" alt="" class="w-16 h-12 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shrink-0 bg-slate-100 dark:bg-slate-800" />
                        </div>
                        <p :class="[HINT, 'mt-1']">The picture shown when any page is shared on Facebook, WhatsApp or Messages, unless the page has its own. About 1200 × 630 works best. Falls back to the club logo.</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <label for="site-icon" :class="LABEL">Site icon (favicon)</label>
                            <div class="flex items-center gap-1.5">
                                <Button v-if="settingsForm.site_icon_url" variant="ghost" size="sm" @click="settingsForm.site_icon_url = ''">
                                    <template #icon><UiIcon name="trash" :size="14" /></template>
                                    Clear
                                </Button>
                                <Button variant="secondary" size="sm" @click="openMediaLibrary('site_icon', null, 'pages')">
                                    <template #icon><UiIcon name="folder" :size="14" /></template>
                                    Media library
                                </Button>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <Input id="site-icon" v-model="settingsForm.site_icon_url" placeholder="https://… or choose from the media library" :error="settingsForm.errors.site_icon_url" class="flex-1 min-w-0 font-mono" />
                            <img v-if="settingsForm.site_icon_url" :src="settingsForm.site_icon_url" alt="" class="w-12 h-12 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shrink-0 bg-slate-100 dark:bg-slate-800" />
                        </div>
                        <p :class="[HINT, 'mt-1']">The small picture in the browser tab and on a phone home screen. A square image at least 180 × 180 works best.</p>
                    </div>

                </div>

                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                    <input type="checkbox" v-model="settingsForm.noindex_site" :class="CHECKBOX" />
                    <span>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Hide the whole website from search engines</span>
                        <span :class="[HINT, 'block mt-0.5']">Useful while the site is being built. Visitors can still open it. Your pages are listed for search engines automatically at <span class="font-mono">/site/{{ club.slug }}/sitemap.xml</span> unless this is on.</span>
                    </span>
                </label>
            </div>

            <hr class="border-slate-200 dark:border-slate-800" />

            <!-- Section 3: Public Contact Details -->
            <div class="space-y-4">
                <h3 :class="SECTION_TITLE">Public contact details</h3>
                <p :class="[HINT, '-mt-2']">Header CTA, social links and footer branding have moved to the <button type="button" @click="requestNavigation('header_footer')" class="underline font-medium text-blue-600 dark:text-blue-400 cursor-pointer">Header & footer</button> tab.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input v-model="settingsForm.contact_email" type="email" label="Public contact email" placeholder="admin@club.org" />

                    <Input v-model="settingsForm.phone" label="Public phone number" placeholder="+44 20 7946 0912" />
                </div>
            </div>


            <hr class="border-slate-200 dark:border-slate-800" />

            <!-- Section 4: Visitor statistics & cookies -->
            <div class="space-y-4">
                <h3 :class="SECTION_TITLE">Visitor statistics and cookies</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Select
                        v-model="settingsForm.analytics_provider"
                        label="Analytics service"
                        :options="ANALYTICS_OPTIONS"
                        :error="settingsForm.errors.analytics_provider"
                        hint="Plausible counts visits without cookies and needs no consent. Google Analytics uses cookies, so visitors are asked first and nothing loads until they accept."
                    />
                    <Input
                        v-if="settingsForm.analytics_provider !== 'none'"
                        v-model="settingsForm.analytics_id"
                        :label="settingsForm.analytics_provider === 'google' ? 'Measurement ID' : 'Site domain'"
                        :placeholder="settingsForm.analytics_provider === 'google' ? 'G-ABC123XYZ' : 'yourlodge.org.uk'"
                        :error="settingsForm.errors.analytics_id"
                        class="font-mono"
                    />
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                    <input type="checkbox" v-model="settingsForm.cookie_banner_enabled" :class="CHECKBOX" />
                    <span>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Show a cookie notice</span>
                        <span :class="[HINT, 'block mt-0.5']">Always shown when Google Analytics is chosen. Turn it on for other services too if you want to tell visitors how their visit is counted.</span>
                    </span>
                </label>

                <div v-if="settingsForm.cookie_banner_enabled || settingsForm.analytics_provider === 'google'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Textarea v-model="settingsForm.cookie_banner_text" label="Notice wording (optional)" :rows="2" maxlength="500" placeholder="We use cookies to count visits so we can improve this website." class="sm:col-span-2" />
                    <Input v-model="settingsForm.cookie_banner_link_label" label="Link text (optional)" maxlength="60" placeholder="Privacy policy" />
                    <Input v-model="settingsForm.cookie_banner_link_url" label="Link address (optional)" :placeholder="`/site/${club.slug}/privacy`" :error="settingsForm.errors.cookie_banner_link_url" class="font-mono" />
                </div>
            </div>

            <hr class="border-slate-200 dark:border-slate-800" />

            <!-- Section 5: Page not found -->
            <div class="space-y-3">
                <h3 :class="SECTION_TITLE">Page not found</h3>
                <Select
                    v-model.number="settingsForm.not_found_page_id"
                    label="Page to show for a missing address"
                    :options="notFoundOptions"
                    :error="settingsForm.errors.not_found_page_id"
                    hint="Build a friendly page (a message and a few links) and pick it here. Visitors still get a proper “not found” status, so search engines don't index the missing address."
                    class="sm:max-w-md"
                />
            </div>

            <hr class="border-slate-200 dark:border-slate-800" />

            <!-- Section 6: Page history -->
            <div class="space-y-3">
                <h3 :class="SECTION_TITLE">Page history</h3>
                <p :class="HINT">Every time a page is published or saved, the website builder keeps the earlier version so you can go back to it. To stop this filling up, older versions are deleted automatically once a page has more than the number below, or a version gets too old. The newest version of every page is always kept.</p>
                <div class="grid grid-cols-1 gap-4 sm:max-w-2xl sm:grid-cols-2">
                    <Input
                        v-model.number="settingsForm.revisions_keep"
                        type="number"
                        min="5"
                        max="50"
                        label="Versions kept per page"
                        hint="Between 5 and 50. Fifteen is plenty for most lodges."
                        :error="settingsForm.errors.revisions_keep"
                    />
                    <Select
                        v-model.number="settingsForm.revisions_max_age_days"
                        label="Delete versions older than"
                        :options="REVISION_AGE_OPTIONS"
                        :error="settingsForm.errors.revisions_max_age_days"
                    />
                </div>
            </div>

            <div class="pt-4 flex justify-end">
                <Button type="submit" variant="secondary" :loading="settingsForm.processing">
                    <template #icon><UiIcon name="save" /></template>
                    Save website settings
                </Button>
            </div>
        </form>
    </Card>
</template>
