// Translations for the Vue pages. The English sentence is the key, as in the Blade pages, so one lang/<locale>.json serves both. In a template:
//
//     {{ dollar-t('Hello, :name', { name }) }}      a sentence with values (dollar-t is the $t this plugin adds)
//     {{ dollar-tc(':count team|:count teams', n) }}  a counted sentence: singular|plural, or Laravel's {0} .. |{1} .. |[2,*] .. forms
//     <Trans k="I agree to the :terms.">            a sentence with a link or emphasis in it, kept whole so a translator can reorder
//         <template #terms><a href="/terms">Terms</a></template>        it: each :placeholder that has a slot gets the slot's
//     </Trans>                                      elements, the others are filled from :values="{ name: value }"
//
// ($ is spelled out above only because launchpad:lang:missing --vue reads literal dollar-t calls, and must not read this comment.)
// The shared Inertia prop `translations` holds the sentences of the current language (empty for the default one, or when it has no JSON file);
// a sentence that is not in it is shown as it is. Translated text is only ever rendered as text, never as HTML.
import { h, Fragment } from 'vue';
import { usePage } from '@inertiajs/vue3';

// the language as the browser's Intl wants it (fr_CA is fr-CA), for dates and relative times
export const locale = () => String(usePage().props.locale ?? 'en').replace('_', '-');

const lines = () => usePage().props.translations ?? {};

// :name, :Name (first letter capitalised) and :NAME (all capitals) as Laravel fills them; a name without a value is left as written
const fill = (text, values) => text.replace(/:([A-Za-z_]+)/g, (whole, name) => {
    if (name in values) return values[name];
    const plain = name.toLowerCase();
    if (!(plain in values)) return whole;
    const value = String(values[plain]);

    return name === name.toUpperCase() ? value.toUpperCase() : value.charAt(0).toUpperCase() + value.slice(1);
});

export const t = (key, values = {}) => fill(lines()[key] ?? key, values);

// Laravel's trans_choice forms: "{0} none|{1} one|[2,*] many" pick by number; plain "one|other" forms by count === 1.
// shortcut: plain forms are only singular|plural, so a language with more plural forms (Welsh, Arabic) needs explicit {n} / [a,b] forms or this extended
export const tc = (key, count, values = {}) => {
    const forms = (lines()[key] ?? key).split('|');
    const bounds = (n) => (n === '*' ? Infinity : Number(n));
    const parsed = forms.map((form) => {
        const [, one, from, to, text] = form.match(/^\s*(?:\{(\d+)\}|\[(\d+|\*),(\d+|\*)\])\s*([\s\S]*)$/) ?? [];

        return { text: text ?? form, explicit: text !== undefined, match: one !== undefined ? count === Number(one) : count >= bounds(from === '*' ? 0 : from) && count <= bounds(to) };
    });
    // no condition fits: as Laravel does, drop the conditions and choose by the plural rule (1 is the first form, anything else the last)
    const plain = parsed.filter((f) => !f.explicit);
    const pool = plain.length ? plain : parsed;
    const chosen = parsed.find((f) => f.explicit && f.match) ?? pool[count === 1 ? 0 : pool.length - 1];

    return fill(chosen.text, { count, ...values });
};

// the sentence cut at its :placeholders, the ones with a slot replaced by what the slot draws (a plain value otherwise)
export const Trans = {
    props: { k: { type: String, required: true }, values: { type: Object, default: () => ({}) } },
    setup(props, { slots }) {
        return () => h(Fragment, (lines()[props.k] ?? props.k).split(/(:[A-Za-z_]+)/).map((part) => {
            const name = part.startsWith(':') ? part.slice(1) : null;

            return name && slots[name] ? slots[name]() : (name ? fill(part, props.values) : part);
        }));
    },
};

export const install = (app) => {
    app.config.globalProperties.$t = t;
    app.config.globalProperties.$tc = tc;
    app.component('Trans', Trans);
};
