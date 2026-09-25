// A "text size" mark for the rich text editor. It stores a class (`rt-size-large`) rather than an inline style, so the
// server's HTML sanitiser keeps it and the sizes stay relative to the surrounding text (see resources/css/app.css).
import { Mark, mergeAttributes } from '@tiptap/core';

export const FONT_SIZES = [
    { id: 'small', label: 'Small' },
    { id: 'large', label: 'Large' },
    { id: 'xlarge', label: 'Extra large' },
    { id: 'huge', label: 'Huge' },
];

const SIZE_CLASS = /(?:^|\s)rt-size-(small|large|xlarge|huge)(?:\s|$)/;

export const FontSize = Mark.create({
    name: 'fontSize',

    addAttributes() {
        return {
            size: {
                default: null,
                parseHTML: (element) => (element.getAttribute('class') || '').match(SIZE_CLASS)?.[1] ?? null,
                renderHTML: (attributes) => (attributes.size ? { class: `rt-size-${attributes.size}` } : {}),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'span', getAttrs: (element) => (SIZE_CLASS.test(element.getAttribute('class') || '') ? {} : false) }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['span', mergeAttributes(HTMLAttributes), 0];
    },

    addCommands() {
        return {
            setFontSize: (size) => ({ commands }) => commands.setMark(this.name, { size }),
            unsetFontSize: () => ({ commands }) => commands.unsetMark(this.name),
        };
    },
});
