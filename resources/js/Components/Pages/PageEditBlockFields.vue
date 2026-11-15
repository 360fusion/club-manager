<script setup>
import CalendarEditor from '@/Components/Blocks/Editors/CalendarEditor.vue';
import CtaBannerEditor from '@/Components/Blocks/Editors/CtaBannerEditor.vue';
import DownloadsEditor from '@/Components/Blocks/Editors/DownloadsEditor.vue';
import FaqEditor from '@/Components/Blocks/Editors/FaqEditor.vue';
import FeatureCardsEditor from '@/Components/Blocks/Editors/FeatureCardsEditor.vue';
import HeroEditor from '@/Components/Blocks/Editors/HeroEditor.vue';
import LinkField from '@/Components/Blocks/Editors/LinkField.vue';
import MapEditor from '@/Components/Blocks/Editors/MapEditor.vue';
import MediaTextEditor from '@/Components/Blocks/Editors/MediaTextEditor.vue';
import QuoteMottoEditor from '@/Components/Blocks/Editors/QuoteMottoEditor.vue';
import SectionHeadingEditor from '@/Components/Blocks/Editors/SectionHeadingEditor.vue';
import SectionStyleEditor from '@/Components/Blocks/Editors/SectionStyleEditor.vue';
import SlideshowEditor from '@/Components/Blocks/Editors/SlideshowEditor.vue';
import StatsEditor from '@/Components/Blocks/Editors/StatsEditor.vue';
import PageEditYoutubeFields from '@/Components/Pages/PageEditYoutubeFields.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { checkbox, field, fieldLabel, IMAGE_POSITIONS, IMAGE_SIZES, NEWS_COLUMNS, NEWS_IMAGE_POSITIONS, NOTICE_STYLES, TEXT_ALIGNS } from '@/Support/pageEditorFields';

// The settings of one element on the page, by type; the newer types have their own editor in Blocks/Editors.
defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
    form: { type: Object, required: true },
    club: { type: Object, required: true },
    pages: { type: Array, default: () => [] },
    clubAddresses: { type: Array, default: () => [] },
    settingsForm: { type: Object, required: true },
    addGalleryImage: { type: Function, required: true },
    applySectionToAll: { type: Function, required: true },
    onYoutubeUrlInput: { type: Function, required: true },
    openMediaLibrary: { type: Function, required: true },
    removeBlockImage: { type: Function, required: true },
    removeGalleryImage: { type: Function, required: true },
    setYoutubeTime: { type: Function, required: true },
});
</script>

<template>
    <div class="p-4 space-y-3">

        <!-- 1. Text / rich text -->
        <div v-if="block.type === 'text' || block.type === 'rich_text'" class="space-y-3">
            <Input v-model="block.heading" label="Section heading (optional)" placeholder="For example, our history and mission" />
            <div>
                <label :id="`block-${index}-content-label`" :class="[fieldLabel, 'mb-1.5']">Text</label>
                <RichTextEditor v-model="block.content" placeholder="Write formatted text content here..." :aria-labelledby="`block-${index}-content-label`" />
            </div>
            <Select v-model="block.text_align" label="Text alignment" :options="TEXT_ALIGNS" class="max-w-[12rem]" />
        </div>

        <!-- 2. Single image -->
        <div v-else-if="block.type === 'image'" class="space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                        <label :for="`block-${index}-image-url`" :class="fieldLabel">Image URL</label>
                        <div class="flex items-center gap-1.5">
                            <Button v-if="block.url" variant="ghost" size="sm" @click="removeBlockImage(block)">
                                <UiIcon name="trash" :size="14" />
                                <span>Clear</span>
                            </Button>
                            <Button variant="secondary" size="sm" @click="openMediaLibrary('block_image', block, 'pages')">
                                <UiIcon name="folder" :size="14" />
                                <span>Media library</span>
                            </Button>
                        </div>
                    </div>
                    <input :id="`block-${index}-image-url`" v-model="block.url" type="text" placeholder="https://example.com/photo.jpg" :class="[field, 'font-mono text-xs']" />
                </div>

                <Input v-model="block.caption" label="Caption and alt text" placeholder="For example, the annual dinner at the lodge" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
                <Select v-model="block.position" label="Image position" :options="IMAGE_POSITIONS" />
                <Select v-model="block.size" label="Image size" :options="IMAGE_SIZES" />
            </div>

            <div v-if="block.url" class="pt-2">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400 block mb-1">Layout preview</span>
                <div :class="['flex', block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : block.position === 'center' ? 'justify-center' : 'w-full']">
                    <div :class="[
                        'rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-1',
                        block.size === 'small' ? 'w-1/4' : block.size === 'medium' ? 'w-1/2' : block.size === 'large' ? 'w-3/4' : 'w-full'
                    ]">
                        <img :src="block.url" alt="" class="w-full h-auto max-h-48 object-cover rounded-lg" />
                        <p v-if="block.caption" class="text-[11px] text-center text-slate-500 dark:text-slate-400 italic mt-1">{{ block.caption }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Image gallery -->
        <div v-else-if="block.type === 'images'" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <label :for="`block-${index}-columns`" :class="fieldLabel">Columns</label>
                    <select :id="`block-${index}-columns`" v-model="block.columns" :class="[field, 'w-auto']">
                        <option :value="2">2 columns</option>
                        <option :value="3">3 columns</option>
                        <option :value="4">4 columns</option>
                    </select>
                </div>

                <Button variant="secondary" size="sm" @click="addGalleryImage(block)">
                    <UiIcon name="plus" :size="14" />
                    <span>Add an image</span>
                </Button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div v-for="(gItem, gIdx) in block.items" :key="gItem.id || gIdx" class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2 relative">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Image {{ gIdx + 1 }}</span>
                        <div class="flex items-center gap-1">
                            <Button variant="secondary" size="sm" @click="openMediaLibrary('gallery_image', gItem, 'pages')">
                                <UiIcon name="folder" :size="14" />
                                <span>Media library</span>
                            </Button>
                            <Button variant="ghost" size="sm" :aria-label="`Remove image ${gIdx + 1}`" @click="removeGalleryImage(block, gIdx)">
                                <UiIcon name="x" :size="14" />
                            </Button>
                        </div>
                    </div>

                    <Input v-model="gItem.url" :aria-label="`Image ${gIdx + 1} URL`" placeholder="https://example.com/photo.jpg" />
                    <Input v-model="gItem.caption" :aria-label="`Image ${gIdx + 1} caption / alt text`" placeholder="Caption and alt text" />

                    <img v-if="gItem.url" :src="gItem.url" alt="" class="w-full h-24 object-cover rounded-lg border border-slate-200 dark:border-slate-800 mt-1" />
                </div>
            </div>
        </div>

        <!-- 4. Notice / callout box -->
        <div v-else-if="block.type === 'notice'" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Select v-model="block.style" label="Callout style" :options="NOTICE_STYLES" />
                <Input v-model="block.title" label="Box title" placeholder="For example, an important announcement" />
            </div>

            <Textarea v-model="block.text" label="Notice text" :rows="3" placeholder="Write callout announcement text here..." />
        </div>

        <!-- 5. Button link -->
        <div v-else-if="block.type === 'button'" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Input v-model="block.label" label="Button label" placeholder="For example, Learn more" />

                <div>
                    <span :class="[fieldLabel, 'mb-1.5']">Link target</span>
                    <LinkField v-model="block.url" :pages="pages" :club="club" label="Button link" />
                </div>

                <Select v-model="block.align" label="Button alignment" :options="TEXT_ALIGNS" />
            </div>
        </div>

        <!-- 6a. YouTube video -->
    <PageEditYoutubeFields v-else-if="block.type === 'youtube'" :block="block" :index="index" :on-youtube-url-input="onYoutubeUrlInput" :open-media-library="openMediaLibrary" :set-youtube-time="setYoutubeTime" />

        <!-- 6b. Newer blocks, each with its own editor component -->
        <CtaBannerEditor v-else-if="block.type === 'cta_banner'" :block="block" :index="index" :pages="pages" :club="club" @media="(type, target) => openMediaLibrary(type, target || block, 'pages')" />
        <FaqEditor v-else-if="block.type === 'faq'" :block="block" :index="index" />
        <MapEditor v-else-if="block.type === 'map'" :block="block" :index="index" :club="club" :club-addresses="clubAddresses" />
        <DownloadsEditor v-else-if="block.type === 'downloads'" :block="block" :index="index" :club="club" @media="(type, target) => openMediaLibrary(type, target || block, 'documents')" />
        <CalendarEditor v-else-if="block.type === 'calendar'" :block="block" :index="index" />
        <FeatureCardsEditor v-else-if="block.type === 'feature_cards'" :block="block" :index="index" :pages="pages" :club="club" />
        <SlideshowEditor v-else-if="block.type === 'slideshow'" :block="block" :index="index" :pages="pages" :club="club" @media="(type, target) => openMediaLibrary(type, target || block, 'pages')" />
        <StatsEditor v-else-if="block.type === 'stats'" :block="block" :index="index" />
        <MediaTextEditor v-else-if="block.type === 'media_text'" :block="block" :index="index" :pages="pages" :club="club" @media="(type, target) => openMediaLibrary(type, target || block, 'pages')" />
        <QuoteMottoEditor v-else-if="block.type === 'quote_motto'" :block="block" :index="index" />
        <SectionHeadingEditor v-else-if="block.type === 'section_heading'" :block="block" :index="index" />
        <HeroEditor v-else-if="block.type === 'hero'" :block="block" :index="index" :pages="pages" :club="club" @media="(type, target) => openMediaLibrary(type, target || block, 'pages')" />

        <!-- 7. News feed and news list -->
        <div v-else-if="block.type === 'news_feed' || block.type === 'news_list'" class="space-y-3">
            <Input v-model="block.heading" label="Section heading" placeholder="For example, latest news" />

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Select v-model="block.columns" label="Layout and columns" :options="NEWS_COLUMNS" />
                <Select v-model="block.image_position" label="Featured image position" :options="NEWS_IMAGE_POSITIONS" />

                <div>
                    <label :for="`block-${index}-news-limit`" :class="[fieldLabel, 'mb-1.5']">Articles per page</label>
                    <select :id="`block-${index}-news-limit`" v-model="block.limit" :class="field">
                        <option :value="3">3 articles per page</option>
                        <option :value="6">6 articles per page</option>
                        <option :value="9">9 articles per page</option>
                        <option :value="12">12 articles per page</option>
                        <option :value="24">24 articles per page</option>
                        <option :value="999">Show all articles (no pages)</option>
                    </select>
                </div>
            </div>

            <Alert variant="info">
                <strong class="font-medium">Filled in automatically:</strong> shows published news items in a <strong class="font-medium">{{ String(block.columns) === 'masonry' ? 'masonry grid' : (block.columns || 3) + '-column grid' }}</strong>, with Previous and Next page controls when there are more than {{ block.limit == 999 ? 'all articles' : block.limit + ' per page' }}.
            </Alert>
        </div>

        <!-- 8. Events and summons feed -->
        <div v-else-if="block.type === 'events_calendar'" class="space-y-3">
            <Input v-model="block.heading" label="Section heading" placeholder="Section heading" />
            <Alert variant="info">
                <strong class="font-medium">Filled in automatically:</strong> shows the club's upcoming events, dinners and meetings.
            </Alert>
        </div>

        <!-- 9. Membership dues -->
        <div v-else-if="block.type === 'pricing_cards'" class="space-y-3">
            <Input v-model="block.heading" label="Section heading" placeholder="Section heading" />
            <Alert variant="info">
                <strong class="font-medium">Filled in automatically:</strong> shows the active membership plans and prices set up in the club's settings.
            </Alert>
        </div>

        <!-- 10. Donation campaign -->
        <div v-else-if="block.type === 'donation_campaign'" class="space-y-3">
            <Input v-model="block.heading" label="Section heading" placeholder="Section heading" />
            <Alert variant="info">
                <strong class="font-medium">Filled in automatically:</strong> shows active fundraising campaigns and their progress bars.
            </Alert>
            <LinkField v-model="block.button_url" :pages="pages" :club="club" label="Where the Donate button goes (for example your JustGiving page; leave empty to hide the button)" />
        </div>

        <!-- 11. Contact details -->
        <div v-else-if="block.type === 'contact_details'" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Input v-model="block.eyebrow" label="Small label above the title" placeholder="For example, CONTACT" />
                <Input v-model="block.title" label="Section title" placeholder="For example, Get in touch" />
            </div>

            <Textarea v-model="block.description" label="Introduction" :rows="2" placeholder="Introductory paragraph..." />

            <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-3">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">
                    Contact cards
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-normal">(they start with the details from the website settings)</span>
                </h4>

                <!-- Email card -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <Input v-model="block.email_heading" label="Email heading" placeholder="Email" />
                    <Input
                        v-model="block.email"
                        label="Contact email"
                        :placeholder="'Default: ' + (settingsForm.contact_email || club.contact_email || 'Not configured')"
                        hint="Leave blank to use the contact email from the organisation settings."
                        class="sm:col-span-2"
                    />
                </div>

                <!-- Meeting times card -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <Input v-model="block.times_heading" label="Times heading" placeholder="Meeting times" />
                    <Textarea v-model="block.times" label="Meeting times and schedule" :rows="2" placeholder="e.g. 7:00 pm, 4th Thursday..." class="sm:col-span-2" />
                </div>

                <!-- Location card -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <Input v-model="block.location_heading" label="Location heading" placeholder="Location" />
                    <Textarea v-model="block.location" label="Full address" :rows="3" placeholder="e.g. Masonic Hall, Street, Town..." class="sm:col-span-2" />
                </div>
            </div>
        </div>

        <!-- 12. Contact form -->
        <div v-else-if="block.type === 'contact_form'" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Input v-model="block.heading" label="Section heading" placeholder="For example, Send us a message" />
                <Input v-model="block.button_text" label="Send button label" placeholder="For example, Send message" />
            </div>

            <Textarea v-model="block.subtitle" label="Subtitle or instructions" :rows="2" placeholder="Subheading or instructions..." />

            <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-3">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Where messages go</h4>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Input
                            v-model="block.recipient_email"
                            type="email"
                            label="Secretary or recipient email"
                            :placeholder="'Default: ' + (settingsForm.contact_email || club.contact_email || 'secretary@' + club.slug + '.org.uk')"
                        />
                        <div class="flex flex-wrap items-center gap-1.5">
                            <Button variant="ghost" size="sm" @click="block.recipient_email = ''">Use the organisation default</Button>
                            <Button
                                v-if="settingsForm.contact_email || club.contact_email"
                                variant="secondary"
                                size="sm"
                                @click="block.recipient_email = settingsForm.contact_email || club.contact_email"
                            >
                                <UiIcon name="mail" :size="14" />
                                <span>Fill in {{ settingsForm.contact_email || club.contact_email }}</span>
                            </Button>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-tight">
                            Messages sent through the form go here. Leave blank to use the contact email from the organisation settings ({{ settingsForm.contact_email || club.contact_email || 'not set up' }}).
                        </p>
                    </div>

                    <Input v-model="block.cc_emails" label="CC email addresses (comma-separated)" placeholder="e.g. treasurer@lodge.org, assistant@lodge.org" hint="Copies of each message are sent here." />
                </div>
            </div>

            <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-3">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Required fields</h4>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input type="checkbox" v-model="block.name_required" :class="checkbox" />
                        Name required
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input type="checkbox" v-model="block.email_required" :class="checkbox" />
                        Email required
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input type="checkbox" v-model="block.phone_required" :class="checkbox" />
                        Phone required
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input type="checkbox" v-model="block.message_required" :class="checkbox" />
                        Message required
                    </label>
                </div>
            </div>

            <Input v-model="block.success_message" label="Message shown after sending" placeholder="For example, Thank you, your message has been sent." />
        </div>

        <SectionStyleEditor v-if="block.type !== 'hero'" :block="block" :index="index" :can-apply-to-others="form.blocks.length > 1" @media="(type, target) => openMediaLibrary(type, target || block, 'pages')" @apply-all="applySectionToAll(block)" />
    </div>
</template>
