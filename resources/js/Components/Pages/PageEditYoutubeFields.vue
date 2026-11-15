<script setup>
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { checkbox, field, fieldLabel, VIDEO_LAYOUTS, VIDEO_SHAPES, VIDEO_TEXT_ALIGNS } from '@/Support/pageEditorFields';
import { formatTimestamp, parseYouTubeUrl } from '@/Utils/youtube';

// The settings of a YouTube video element.
defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
    onYoutubeUrlInput: { type: Function, required: true },
    openMediaLibrary: { type: Function, required: true },
    setYoutubeTime: { type: Function, required: true },
});
</script>

<template>
    <div class="space-y-3">
        <div>
            <label :for="`block-${index}-yt-url`" :class="[fieldLabel, 'mb-1.5']">YouTube link</label>
            <input :id="`block-${index}-yt-url`" v-model="block.url" @input="onYoutubeUrlInput(block)" type="text" placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/..." :class="[field, 'font-mono text-xs']" />
            <p v-if="!block.url" class="text-xs text-slate-500 dark:text-slate-400 mt-1">Paste any YouTube link: a normal video, a youtu.be short link or a Short.</p>
            <p v-else-if="parseYouTubeUrl(block.url)" class="flex items-center gap-1 text-xs text-emerald-700 dark:text-emerald-400 font-medium mt-1"><UiIcon name="check" :size="14" />Video found</p>
            <p v-else class="flex items-center gap-1 text-xs text-rose-600 dark:text-rose-400 font-medium mt-1"><UiIcon name="warning" :size="14" />That isn't a YouTube video link, so nothing will show on the site.</p>
        </div>

        <Input v-model="block.title" label="Name (optional)" maxlength="200" placeholder="For example, Installation ceremony 2026" />
        <Textarea v-model="block.description" label="Description (optional, plain text)" :rows="3" maxlength="2000" />

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
            <Select v-model="block.layout" label="Layout" :options="VIDEO_LAYOUTS" />
            <Select v-model="block.aspect" label="Shape" :options="VIDEO_SHAPES" />
            <Select v-model="block.text_align" label="Text and buttons" :options="VIDEO_TEXT_ALIGNS" />
        </div>

        <div>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                <label :for="`block-${index}-yt-cover`" :class="fieldLabel">Cover image (optional)</label>
                <div class="flex items-center gap-1.5">
                    <Button v-if="block.cover_url" variant="ghost" size="sm" @click="block.cover_url = ''">
                        <UiIcon name="trash" :size="14" />
                        <span>Clear</span>
                    </Button>
                    <Button variant="secondary" size="sm" @click="openMediaLibrary('video_cover', block, 'pages')">
                        <UiIcon name="folder" :size="14" />
                        <span>Media library</span>
                    </Button>
                </div>
            </div>
            <input :id="`block-${index}-yt-cover`" v-model="block.cover_url" type="text" placeholder="https://example.com/cover.jpg" :class="[field, 'font-mono text-xs']" />
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Without a cover, the YouTube thumbnail is used and loads from YouTube's image server when the page opens. A cover from your media library keeps visitors' details away from YouTube until they press play.</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 items-end">
            <div>
                <label :for="`block-${index}-yt-start`" :class="[fieldLabel, 'mb-1.5']">Start at</label>
                <input :id="`block-${index}-yt-start`" :value="formatTimestamp(block.start)" @change="setYoutubeTime(block, 'start', $event)" type="text" placeholder="e.g. 1:30" :class="[field, 'font-mono text-xs']" />
            </div>
            <div>
                <label :for="`block-${index}-yt-end`" :class="[fieldLabel, 'mb-1.5']">Stop at</label>
                <input :id="`block-${index}-yt-end`" :value="formatTimestamp(block.end)" @change="setYoutubeTime(block, 'end', $event)" type="text" placeholder="e.g. 4:05" :class="[field, 'font-mono text-xs']" />
            </div>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer pb-2">
                <input type="checkbox" v-model="block.loop" :class="checkbox" /> Repeat (loop)
            </label>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer pb-2">
                <input type="checkbox" v-model="block.captions" :class="checkbox" /> Show captions
            </label>
        </div>

        <div class="space-y-3 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" v-model="block.show_youtube_link" :class="checkbox" /> Show a "Watch on YouTube" link
            </label>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" v-model="block.button_enabled" :class="checkbox" /> Add a button
            </label>
            <div v-if="block.button_enabled" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Input v-model="block.button_label" label="Button label" maxlength="100" placeholder="For example, Book your place" />
                <Input v-model="block.button_url" label="Button link" placeholder="https://..." class="sm:col-span-2" />
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer sm:col-span-3">
                    <input type="checkbox" v-model="block.button_new_tab" :class="checkbox" /> Open the button link in a new tab
                </label>
            </div>
        </div>
    </div>
</template>
