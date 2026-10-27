<script setup>
import DomainSetupGuide from '@/Components/DomainSetupGuide.vue';

// A section of the website builder page, in its own file so the page stays readable.
defineProps({
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
</script>

<template>
    <div class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">🌐 Global Website & SEO Settings</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Manage custom domain, global search engine optimization, and public contact details.</p>
            </div>
            <button
                type="button"
                @click="submitWebsiteSettings"
                :disabled="settingsForm.processing"
                class="py-2.5 px-5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md transition-all flex items-center gap-2 disabled:opacity-50 cursor-pointer"
            >
                <span>💾 Save Website Settings</span>
            </button>
        </div>

        <!-- Saved Success Alert -->
        <div v-if="isSavedSuccess" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-2xl text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center gap-2">
            <span>✅ Website settings saved successfully!</span>
        </div>

        <form @submit.prevent="submitWebsiteSettings" class="space-y-6 text-xs">
            
            <!-- Section 1: Custom Domain Setup -->
            <div class="space-y-4">
                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Custom Domain Setup</h3>
                <div class="p-5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-4">
                    <div>
                        <label for="custom-domain" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Custom Domain Name</label>
                        <input id="custom-domain" v-model="settingsForm.custom_domain" type="text" :placeholder="`e.g. ${domainExample}`" class="w-full sm:w-96 px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">Connect your custom domain (e.g. <code class="bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-100 px-1 py-0.5 rounded font-bold">{{ domainExample }}</code>) to your club portal.</p>
                    </div>

                    <DomainSetupGuide
                        v-if="settingsForm.custom_domain"
                        :domain="settingsForm.custom_domain"
                        :status="websiteSettings.domain_status"
                        :verified-at="websiteSettings.domain_verified_at"
                        :instructions="websiteSettings.domain_instructions"
                        :provider="websiteSettings.domain_provider"
                        :saved-domain="websiteSettings.custom_domain"
                        :checking="checkingDomain"
                        @check="checkDomainNow"
                    />

                    <label v-if="websiteSettings.custom_domain" class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" v-model="settingsForm.site_redirect_to_domain" class="w-4 h-4 mt-0.5 rounded accent-blue-600 cursor-pointer" />
                        <span>
                            <span class="block font-bold text-slate-700 dark:text-slate-200">Send visitors to {{ websiteSettings.custom_domain }}</span>
                            <span class="block text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Your pages are on your domain once it is Active. Tick this to forward the address <code class="font-mono">/site/{{ club.slug }}</code> there too; leave it unticked to keep the pages at both addresses.</span>
                        </span>
                    </label>
                </div>
            </div>

            <hr class="border-slate-100 dark:border-slate-800" />

            <!-- Section 2: Search Engine Optimization (SEO) -->
            <div class="space-y-4">
                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Search Engine Optimization (SEO)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="seo-title-suffix" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Page Title Suffix</label>
                        <input id="seo-title-suffix" v-model="settingsForm.seo_title_suffix" type="text" placeholder="| The Lodge of Fraternity" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        <p class="text-[10px] text-slate-400 mt-1">Appended to page titles in browser tabs and search engines.</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="seo-meta-description" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default Meta Description</label>
                        <textarea id="seo-meta-description" v-model="settingsForm.seo_meta_description" rows="3" placeholder="Official homepage for events, membership, news, and history." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-medium text-slate-800 dark:text-slate-100"></textarea>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="site-share-image" class="block font-bold text-slate-700 dark:text-slate-200 mb-1 !mb-0">Default share image</label>
                            <div class="flex items-center gap-1.5">
                                <button v-if="settingsForm.share_image_url" type="button" @click="settingsForm.share_image_url = ''" class="px-2 py-0.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-bold rounded-lg border border-rose-200 dark:border-rose-800/60 cursor-pointer">🗑️ Clear</button>
                                <button type="button" @click="openMediaLibrary('site_share_image', null, 'pages')" class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-lg border border-blue-200 dark:border-blue-800/60 cursor-pointer">📁 Media Library</button>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <input id="site-share-image" v-model="settingsForm.share_image_url" type="text" placeholder="https://… or choose from the media library" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-mono text-[11px]" />
                            <img v-if="settingsForm.share_image_url" :src="settingsForm.share_image_url" alt="" class="w-16 h-12 rounded-lg object-cover border border-slate-200 dark:border-slate-700 shrink-0 bg-slate-100 dark:bg-slate-800" />
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">The picture shown when any page is shared on Facebook, WhatsApp or Messages, unless the page has its own. About 1200 × 630 works best. Falls back to the club logo.</p>
                        <p v-if="settingsForm.errors.share_image_url" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.share_image_url }}</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="site-icon" class="block font-bold text-slate-700 dark:text-slate-200 mb-1 !mb-0">Site icon (favicon)</label>
                            <div class="flex items-center gap-1.5">
                                <button v-if="settingsForm.site_icon_url" type="button" @click="settingsForm.site_icon_url = ''" class="px-2 py-0.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-bold rounded-lg border border-rose-200 dark:border-rose-800/60 cursor-pointer">🗑️ Clear</button>
                                <button type="button" @click="openMediaLibrary('site_icon', null, 'pages')" class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-lg border border-blue-200 dark:border-blue-800/60 cursor-pointer">📁 Media Library</button>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <input id="site-icon" v-model="settingsForm.site_icon_url" type="text" placeholder="https://… or choose from the media library" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-mono text-[11px]" />
                            <img v-if="settingsForm.site_icon_url" :src="settingsForm.site_icon_url" alt="" class="w-12 h-12 rounded-lg object-cover border border-slate-200 dark:border-slate-700 shrink-0 bg-slate-100 dark:bg-slate-800" />
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">The small picture in the browser tab and on a phone home screen. A square image at least 180 × 180 works best.</p>
                        <p v-if="settingsForm.errors.site_icon_url" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.site_icon_url }}</p>
                    </div>

                </div>

                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                    <input type="checkbox" v-model="settingsForm.noindex_site" class="w-4 h-4 mt-0.5 rounded accent-blue-600 cursor-pointer" />
                    <span>
                        <span class="font-bold text-slate-700 dark:text-slate-200">Hide the whole website from search engines</span>
                        <span class="block text-[10px] text-slate-400 mt-0.5">Useful while the site is being built. Visitors can still open it. Your pages are listed for search engines automatically at <span class="font-mono">/site/{{ club.slug }}/sitemap.xml</span> unless this is on.</span>
                    </span>
                </label>
            </div>

            <hr class="border-slate-100 dark:border-slate-800" />

            <!-- Section 3: Public Contact Details -->
            <div class="space-y-4">
                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Public Contact Details</h3>
                <p class="text-[10px] text-slate-400 -mt-2">Header CTA, social links and footer branding have moved to the <button type="button" @click="requestNavigation('header_footer')" class="underline font-bold text-blue-600 dark:text-blue-400 cursor-pointer">Header & Footer</button> tab.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="public-contact-email" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Public Contact Email</label>
                        <input id="public-contact-email" v-model="settingsForm.contact_email" type="email" placeholder="admin@club.org" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label for="public-phone" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Public Phone Number</label>
                        <input id="public-phone" v-model="settingsForm.phone" type="text" placeholder="+44 20 7946 0912" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                </div>
            </div>


            <hr class="border-slate-100 dark:border-slate-800" />

            <!-- Section 4: Visitor statistics & cookies -->
            <div class="space-y-4">
                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Visitor Statistics &amp; Cookies</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="analytics-provider" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Analytics service</label>
                        <select id="analytics-provider" v-model="settingsForm.analytics_provider" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                            <option value="none">None</option>
                            <option value="plausible">Plausible (no cookies)</option>
                            <option value="google">Google Analytics 4</option>
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Plausible counts visits without cookies and needs no consent. Google Analytics uses cookies, so visitors are asked first and nothing loads until they accept.</p>
                        <p v-if="settingsForm.errors.analytics_provider" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.analytics_provider }}</p>
                    </div>
                    <div v-if="settingsForm.analytics_provider !== 'none'">
                        <label for="analytics-id" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ settingsForm.analytics_provider === 'google' ? 'Measurement ID' : 'Site domain' }}</label>
                        <input id="analytics-id" v-model="settingsForm.analytics_id" type="text" :placeholder="settingsForm.analytics_provider === 'google' ? 'G-ABC123XYZ' : 'yourlodge.org.uk'" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-mono" />
                        <p v-if="settingsForm.errors.analytics_id" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.analytics_id }}</p>
                    </div>
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                    <input type="checkbox" v-model="settingsForm.cookie_banner_enabled" class="w-4 h-4 mt-0.5 rounded accent-blue-600 cursor-pointer" />
                    <span>
                        <span class="font-bold text-slate-700 dark:text-slate-200">Show a cookie notice</span>
                        <span class="block text-[10px] text-slate-400 mt-0.5">Always shown when Google Analytics is chosen. Turn it on for other services too if you want to tell visitors how their visit is counted.</span>
                    </span>
                </label>

                <div v-if="settingsForm.cookie_banner_enabled || settingsForm.analytics_provider === 'google'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="cookie-text" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Notice wording <span class="font-normal text-slate-400">(optional)</span></label>
                        <textarea id="cookie-text" v-model="settingsForm.cookie_banner_text" rows="2" maxlength="500" placeholder="We use cookies to count visits so we can improve this website." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label for="cookie-link-label" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Link text <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="cookie-link-label" v-model="settingsForm.cookie_banner_link_label" type="text" maxlength="60" placeholder="Privacy policy" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label for="cookie-link-url" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Link address <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="cookie-link-url" v-model="settingsForm.cookie_banner_link_url" type="text" placeholder="/site/{{ club.slug }}/privacy" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-mono text-[11px]" />
                        <p v-if="settingsForm.errors.cookie_banner_link_url" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.cookie_banner_link_url }}</p>
                    </div>
                </div>
            </div>

            <hr class="border-slate-100 dark:border-slate-800" />

            <!-- Section 5: Page not found -->
            <div class="space-y-3">
                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Page Not Found</h3>
                <div class="sm:max-w-md">
                    <label for="not-found-page" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Page to show for a missing address</label>
                    <select id="not-found-page" v-model="settingsForm.not_found_page_id" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                        <option value="">The standard "not found" screen</option>
                        <option v-for="p in pages.filter(pg => pg.is_published && !pg.is_members_only)" :key="p.id" :value="p.id">{{ p.title }}</option>
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">Build a friendly page (a message and a few links) and pick it here. Visitors still get a proper "not found" status, so search engines don't index the missing address.</p>
                    <p v-if="settingsForm.errors.not_found_page_id" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.not_found_page_id }}</p>
                </div>
            </div>

            <hr class="border-slate-100 dark:border-slate-800" />

            <!-- Section 6: Page history -->
            <div class="space-y-3">
                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Page History</h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Every time a page is published or saved, the website builder keeps the earlier version so you can go back to it. To stop this filling up, older versions are deleted automatically once a page has more than the number below, or a version gets too old. The newest version of every page is always kept.</p>
                <div class="grid grid-cols-1 gap-4 sm:max-w-2xl sm:grid-cols-2">
                    <div>
                        <label for="revisions-keep" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Versions kept per page</label>
                        <input id="revisions-keep" v-model.number="settingsForm.revisions_keep" type="number" min="5" max="50" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-bold" />
                        <p class="text-[10px] text-slate-400 mt-1">Between 5 and 50. Fifteen is plenty for most lodges.</p>
                        <p v-if="settingsForm.errors.revisions_keep" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.revisions_keep }}</p>
                    </div>
                    <div>
                        <label for="revisions-age" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Delete versions older than</label>
                        <select id="revisions-age" v-model.number="settingsForm.revisions_max_age_days" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-bold">
                            <option :value="30">1 month</option>
                            <option :value="90">3 months</option>
                            <option :value="180">6 months</option>
                            <option :value="365">1 year</option>
                            <option :value="0">Never (only the number above applies)</option>
                        </select>
                        <p v-if="settingsForm.errors.revisions_max_age_days" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.revisions_max_age_days }}</p>
                    </div>
                </div>
            </div>

            <div class="pt-4 flex justify-end">
                <button
                    type="submit"
                    :disabled="settingsForm.processing"
                    class="py-3 px-6 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-lg shadow-blue-600/20 transition-all flex items-center gap-2 disabled:opacity-50 cursor-pointer"
                >
                    <span>💾 Save Website Settings</span>
                </button>
            </div>
        </form>
    </div>
</template>
