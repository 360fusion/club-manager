<script>
import { h } from 'vue';

/**
 * Renders HTML the server has already made safe: rich text cleaned by RichTextSanitizer (the SanitizedHtml casts on posts,
 * pages, newsletters and blocks) or markup the server generates itself (QR code SVGs). Never pass anything a visitor typed
 * that has not been through the sanitiser. This is the app's one place that writes raw HTML, so it is the one to review.
 */
export default {
    props: {
        html: { type: String, default: '' },
        as: { type: String, default: 'div' },
    },
    setup(props) {
        return () => h(props.as, { innerHTML: props.html ?? '' });
    },
};
</script>
