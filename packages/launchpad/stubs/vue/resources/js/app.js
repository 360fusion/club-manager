import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';
import { install as i18n } from './i18n';

const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    // every page is drawn inside the site layout unless it brings its own
    resolve: async (name) => {
        const load = pages[`./Pages/${name}.vue`];

        if (!load) {
            throw new Error(`There is no page called ${name}`);
        }

        const page = await load();
        page.default.layout ??= AppLayout;

        return page;
    },
    title: (title, page) => (title ? `${title} | ${page.props.site.name}` : page.props.site.name),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .mount(el);
    },
    progress: { color: 'var(--lp-accent, #4f46e5)' },
});
