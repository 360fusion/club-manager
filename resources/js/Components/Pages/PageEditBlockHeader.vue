<script setup>
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { BLOCK_ICONS, iconButton, smallSelect } from '@/Support/pageEditorFields';

// The bar on top of each element card: its number and type, and the show, width, copy, save, move and delete controls.
const blockToDelete = defineModel('blockToDelete', { type: [Number, null], default: null });

defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
    form: { type: Object, required: true },
    copyTargets: { type: Array, default: () => [] },
    isDirty: { type: Boolean, default: false },
    isSavedSuccess: { type: Boolean, default: false },
    copyBlockTo: { type: Function, required: true },
    duplicateBlock: { type: Function, required: true },
    isCollapsed: { type: Function, required: true },
    moveBlockDown: { type: Function, required: true },
    moveBlockUp: { type: Function, required: true },
    submitForm: { type: Function, required: true },
    toggleCollapsed: { type: Function, required: true },
});
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-800 dark:text-slate-100">
        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="toggleCollapsed(block)"
                :class="iconButton"
                :aria-expanded="!isCollapsed(block)"
                :title="isCollapsed(block) ? 'Expand to edit this element' : 'Compact this element'"
                :aria-label="isCollapsed(block) ? 'Expand element' : 'Compact element'"
            >
                <UiIcon name="chevron-down" :size="14" class="transition-transform" :class="isCollapsed(block) ? '-rotate-90' : ''" />
            </button>
            <span class="w-5 h-5 rounded bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 flex items-center justify-center text-[11px] font-semibold border border-slate-200 dark:border-slate-700">
                {{ index + 1 }}
            </span>

            <!-- Element type -->
            <Badge>
                <UiIcon :name="BLOCK_ICONS[block.type]" :size="14" />
                <template v-if="block.type === 'text' || block.type === 'rich_text'">Text block</template>
                <template v-else-if="block.type === 'image'">Single image</template>
                <template v-else-if="block.type === 'images'">Image gallery ({{ block.columns || 3 }} columns)</template>
                <template v-else-if="block.type === 'notice'">Callout box</template>
                <template v-else-if="block.type === 'button'">Button link</template>
                <template v-else-if="block.type === 'youtube'">YouTube video</template>
                <template v-else-if="block.type === 'downloads'">Downloads</template>
                <template v-else-if="block.type === 'faq'">FAQ</template>
                <template v-else-if="block.type === 'map'">Map</template>
                <template v-else-if="block.type === 'cta_banner'">Call-to-action banner</template>
                <template v-else-if="block.type === 'calendar'">Events calendar</template>
                <template v-else-if="block.type === 'hero'">Hero banner</template>
                <template v-else-if="block.type === 'news_feed' || block.type === 'news_list'">News list ({{ String(block.columns) === 'masonry' ? 'masonry' : (block.columns || 3) + ' columns' }})</template>
                <template v-else-if="block.type === 'events_calendar'">Events and summons</template>
                <template v-else-if="block.type === 'pricing_cards'">Membership dues</template>
                <template v-else-if="block.type === 'donation_campaign'">Donation campaign</template>
                <template v-else-if="block.type === 'contact_details'">Contact cards</template>
                <template v-else-if="block.type === 'contact_form'">Contact form</template>
                <template v-else-if="block.type === 'slideshow'">Slideshow ({{ (block.slides || []).filter((slide) => slide.image_url).length }} photos)</template>
                <template v-else-if="block.type === 'section_heading'">Section heading</template>
                <template v-else-if="block.type === 'feature_cards'">Icon cards ({{ block.columns || 3 }} columns)</template>
                <template v-else-if="block.type === 'stats'">Stats row</template>
                <template v-else-if="block.type === 'media_text'">Image and text</template>
                <template v-else-if="block.type === 'quote_motto'">Motto</template>
            </Badge>
        </div>

        <!-- Controls: show/hide, width, copy, save, move, duplicate, delete -->
        <div class="flex flex-wrap items-center gap-1">
            <ToggleSwitch
                :model-value="!block.hidden"
                :label="`Show element ${index + 1} on the live site`"
                class="mr-1"
                @update:model-value="block.hidden = !$event"
            />
            <template v-if="block.type !== 'hero'">
                <select
                    :value="block.block_width || 'full'"
                    @change="block.block_width = $event.target.value"
                    :class="smallSelect"
                    title="How wide this element is on the page"
                    aria-label="Element width"
                >
                    <option value="full">Full width</option>
                    <option value="three_quarter">75% width</option>
                    <option value="half">50% width</option>
                </select>
                <select
                    v-if="block.block_width && block.block_width !== 'full'"
                    :value="block.block_align || 'center'"
                    @change="block.block_align = $event.target.value"
                    :class="smallSelect"
                    title="Where a narrower element sits"
                    aria-label="Element position"
                >
                    <option value="center">Centred</option>
                    <option value="left">Left</option>
                    <option value="right">Right</option>
                </select>
            </template>
            <select
                v-if="copyTargets.length"
                @change="copyBlockTo(block, $event)"
                :class="[smallSelect, 'max-w-[7.5rem]']"
                title="Add a copy of this element to the end of another page"
                aria-label="Copy this element to another page"
            >
                <option value="">Copy to…</option>
                <option v-for="target in copyTargets" :key="target.id" :value="target.id">{{ target.title }}</option>
            </select>
            <Button
                :variant="isDirty && !isSavedSuccess ? 'primary' : 'secondary'"
                size="sm"
                class="mr-1"
                :disabled="form.processing"
                title="Save this page"
                aria-label="Save this page"
                @click="submitForm()"
            >
                <UiIcon :name="isSavedSuccess ? 'check' : 'save'" :size="14" />
                <span>{{ form.processing ? 'Saving…' : isSavedSuccess ? 'Saved' : 'Save' }}</span>
            </Button>
            <button
                type="button"
                @click="moveBlockUp(index)"
                :disabled="index === 0"
                :class="iconButton"
                title="Move up"
                aria-label="Move block up"
            >
                <UiIcon name="chevron-down" class="rotate-180" />
            </button>
            <button
                type="button"
                @click="moveBlockDown(index)"
                :disabled="index === form.blocks.length - 1"
                :class="iconButton"
                title="Move down"
                aria-label="Move block down"
            >
                <UiIcon name="chevron-down" />
            </button>
            <button
                type="button"
                @click="duplicateBlock(index)"
                :class="iconButton"
                title="Duplicate element"
                aria-label="Duplicate block"
            >
                <UiIcon name="copy" />
            </button>
            <button
                type="button"
                @click="blockToDelete = index"
                :class="[iconButton, 'hover:!text-rose-600 dark:hover:!text-rose-400']"
                title="Delete element"
                aria-label="Delete block"
            >
                <UiIcon name="trash" />
            </button>
        </div>
    </div>
</template>
