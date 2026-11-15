import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { BLOCK_TYPES, newBlock } from '@/Support/pageBlockCatalog';
import { writeCollapsed } from '@/Utils/collapsedBlocks';
import { parseYouTubeUrl, parseTimestamp, formatTimestamp } from '@/Utils/youtube';

/**
 * The page editor's elements: the insert menu, adding, moving, copying, folding and deleting elements, and the small
 * helpers of the image, gallery and video editors. `form` is the page form and `collapsedBlocks` its folded-element ids
 * (both from usePageEditorForm); `props` gives the club, its addresses and the website settings new elements start from.
 */
export function usePageEditorBlocks(props, { form, collapsedBlocks }) {
    const activeInsertIndex = ref(null);
    const insertMenuPlacement = ref('down');
    // The menu is centred on its button; this nudges it sideways so it never runs off either edge of the window and
    // keeps a wider gap on the right than the left.
    const insertMenuShift = ref(0);
    const INSERT_MENU_WIDTH = 1100;
    const INSERT_MENU_MARGIN_LEFT = 16;
    const INSERT_MENU_MARGIN_RIGHT = 40;

    const toggleInsertMenu = (index, event = null) => {
        if (activeInsertIndex.value === index) {
            activeInsertIndex.value = null;
            return;
        }

        activeInsertIndex.value = index;

        if (event && event.currentTarget) {
            const rect = event.currentTarget.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;

            const width = Math.min(INSERT_MENU_WIDTH, window.innerWidth - INSERT_MENU_MARGIN_LEFT - INSERT_MENU_MARGIN_RIGHT);
            const centredLeft = rect.left + rect.width / 2 - width / 2;
            const clampedLeft = Math.max(INSERT_MENU_MARGIN_LEFT, Math.min(centredLeft, window.innerWidth - INSERT_MENU_MARGIN_RIGHT - width));
            insertMenuShift.value = Math.round(clampedLeft - centredLeft);

            if (spaceBelow < 300 && spaceAbove > spaceBelow) {
                insertMenuPlacement.value = 'up';
            } else {
                insertMenuPlacement.value = 'down';
            }
        } else {
            insertMenuPlacement.value = 'down';
            insertMenuShift.value = 0;
        }
    };

    const handleDocumentClick = (e) => {
        if (activeInsertIndex.value !== null && !e.target.closest('.insert-menu-container')) {
            activeInsertIndex.value = null;
        }
    };

    onMounted(() => {
        document.addEventListener('click', handleDocumentClick);
    });

    onUnmounted(() => {
        document.removeEventListener('click', handleDocumentClick);
    });

    // "Use this look on every element": copies one element's section style (not its anchor) to the others on the page.
    const applySectionToAll = (source) => {
        const { anchor: _anchor, ...look } = source.section || {};

        form.blocks.forEach((block) => {
            if (block !== source && block.type !== 'hero') block.section = { ...look, anchor: block.section?.anchor || '' };
        });
    };

    const newBlockId = ref(null);
    const blockCardId = (block, index) => `block-card-${block.id || index}`;

    // Bring a block that was just added to the top of the view (the admin may have been looking at another block
    // when they added it), and outline it for a moment so it is obvious which one it is.
    const revealBlock = (block, index) => {
        newBlockId.value = block.id;
        nextTick(() => {
            document.getElementById(blockCardId(block, index))?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => { if (newBlockId.value === block.id) newBlockId.value = null; }, 2500);
        });
    };

    const addBlock = (type, targetIndex = null) => {
        const block = newBlock(type, { club: props.club, clubAddresses: props.clubAddresses, websiteSettings: props.websiteSettings });

        if (block) {
            const index = targetIndex !== null && targetIndex >= 0 && targetIndex <= form.blocks.length ? targetIndex : form.blocks.length;
            form.blocks.splice(index, 0, block);
            revealBlock(block, index);
        }
        activeInsertIndex.value = null;
    };

    // Compact view: which elements are folded down to a read-only summary. This is view state only (not part of
    // `form.blocks`), so folding never makes the page "unsaved" and is never saved.
    const isCollapsed = (block) => collapsedBlocks.value.has(block.id);
    const toggleCollapsed = (block) => {
        const next = new Set(collapsedBlocks.value);
        if (!next.delete(block.id)) next.add(block.id);
        collapsedBlocks.value = next;
    };
    const collapseAll = () => { collapsedBlocks.value = new Set(form.blocks.map((b) => b.id)); };
    const expandAll = () => { collapsedBlocks.value = new Set(); };
    const collapsedCount = computed(() => form.blocks.filter((b) => collapsedBlocks.value.has(b.id)).length);

    // Save the folded elements whenever they change (only for a saved page, and only ids that still exist on it).
    watch(collapsedBlocks, (folded) => {
        if (!form.id) return;
        const present = new Set(form.blocks.map((b) => b.id));
        writeCollapsed(props.club.slug, form.id, [...folded].filter((id) => present.has(id)));
    });

    const removeBlock = (index) => {
        form.blocks.splice(index, 1);
    };

    // Deleting an element asks first, since it takes its content with it (the page is only changed once saved).
    const blockToDelete = ref(null);
    const blockToDeleteName = computed(() => {
        const block = blockToDelete.value === null ? null : form.blocks[blockToDelete.value];
        if (!block) return '';
        const label = BLOCK_TYPES.find((t) => t.type === block.type)?.label || 'element';
        const name = block.heading || block.title || block.label || block.question || '';
        return name ? `${label}: "${String(name).slice(0, 60)}"` : label;
    });
    const confirmRemoveBlock = () => {
        if (blockToDelete.value !== null) removeBlock(blockToDelete.value);
        blockToDelete.value = null;
    };

    const moveBlockUp = (index) => {
        if (index > 0) {
            const temp = form.blocks[index];
            form.blocks[index] = form.blocks[index - 1];
            form.blocks[index - 1] = temp;
        }
    };

    const moveBlockDown = (index) => {
        if (index < form.blocks.length - 1) {
            const temp = form.blocks[index];
            form.blocks[index] = form.blocks[index + 1];
            form.blocks[index + 1] = temp;
        }
    };

    const duplicateBlock = (index) => {
        const source = form.blocks[index];
        const copy = JSON.parse(JSON.stringify(source));
        copy.id = 'block-' + Date.now();
        form.blocks.splice(index + 1, 0, copy);
        revealBlock(copy, index + 1);
    };

    const removeBlockImage = (block) => {
        block.url = '';
    };

    const addGalleryImage = (block) => {
        const gId = block.id + '-g' + Date.now();
        block.items.push({ id: gId, url: '', caption: '' });
    };

    const removeGalleryImage = (block, idx) => {
        block.items.splice(idx, 1);
    };

    // A pasted Shorts link switches a fresh video block to portrait, and a link with a time (?t=90) fills the
    // start time; both only when the admin has not already chosen something.
    const onYoutubeUrlInput = (block) => {
        const video = parseYouTubeUrl(block.url);
        if (!video) return;
        if (video.isShort && block.aspect === '16:9') block.aspect = '9:16';
        if (video.start && !block.start) block.start = video.start;
    };

    const setYoutubeTime = (block, field, event) => {
        block[field] = parseTimestamp(event.target.value);
        event.target.value = formatTimestamp(block[field]);
    };

    return {
        activeInsertIndex, insertMenuPlacement, insertMenuShift, toggleInsertMenu, applySectionToAll, addBlock, newBlockId, blockCardId,
        isCollapsed, toggleCollapsed, collapseAll, expandAll, collapsedCount, blockToDelete, blockToDeleteName, confirmRemoveBlock,
        moveBlockUp, moveBlockDown, duplicateBlock, removeBlockImage, addGalleryImage, removeGalleryImage, onYoutubeUrlInput, setYoutubeTime,
    };
}
