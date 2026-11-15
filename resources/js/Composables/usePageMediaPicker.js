import { ref } from 'vue';

/**
 * The website builder's media library pop-up: what the chosen file is for (`type`) and the element or row it goes on
 * (`targetObj`). `form` is the page form and `settingsForm` the site settings form, which some kinds of file fill.
 */
export function usePageMediaPicker({ form, settingsForm }) {
    const showMediaModal = ref(false);
    const mediaTarget = ref(null);
    const mediaDefaultFolder = ref('pages');

    const openMediaLibrary = (type, targetObj = null, folder = 'pages') => {
        mediaTarget.value = { type, targetObj };
        mediaDefaultFolder.value = folder;
        showMediaModal.value = true;
    };

    const onMediaSelect = (mediaItem) => {
        if (!mediaTarget.value) return;
        const { type, targetObj } = mediaTarget.value;
        if (type === 'block_image' && targetObj) {
            targetObj.url = mediaItem.url;
        } else if (type === 'gallery_image' && targetObj) {
            targetObj.url = mediaItem.url;
        } else if (type === 'video_cover' && targetObj) {
            targetObj.cover_url = mediaItem.url;
        } else if (type === 'cta_image' && targetObj) {
            targetObj.image_url = mediaItem.url;
        } else if (type === 'cta_side_image' && targetObj) {
            targetObj.side_image_url = mediaItem.url;
        } else if (type === 'slideshow_image' && targetObj) {
            targetObj.image_url = mediaItem.url;
        } else if (type === 'media_text_image' && targetObj) {
            targetObj.image_url = mediaItem.url;
        } else if (type === 'hero_image' && targetObj) {
            targetObj.image_url = mediaItem.url;
        } else if (type === 'section_image' && targetObj) {
            targetObj.section = { ...(targetObj.section || {}), bg: 'image', bg_image: mediaItem.url };
        } else if (type === 'page_share_image') {
            form.share_image = mediaItem.url;
        } else if (type === 'site_share_image') {
            settingsForm.share_image_url = mediaItem.url;
        } else if (type === 'site_icon') {
            settingsForm.site_icon_url = mediaItem.url;
        } else if (type === 'download_link' && targetObj) {
            targetObj.url = mediaItem.url;
            if (!targetObj.title) targetObj.title = mediaItem.name || '';
        }
    };

    return { showMediaModal, mediaDefaultFolder, openMediaLibrary, onMediaSelect };
}
