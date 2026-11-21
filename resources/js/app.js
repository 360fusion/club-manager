import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { currencySymbol, formatMoney } from './Utils/currency';
import focusTrap from './Directives/focusTrap';
import { startNativeApp } from './Support/nativeApp';
import { startWebApp } from './Support/webPush';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const pages = import.meta.glob('./Pages/**/*.vue');
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

// The pages the phone's bottom tabs open: their code is fetched once the app is idle, so the first tap on each tab
// doesn't wait for a download. Only these, not every page (the admin screens are large).
const TAB_PAGES = ['Members/Dashboard', 'Members/Calendar', 'Members/AllEvents', 'Members/Notifications', 'Members/Lodges', 'Member/Dashboard'];
const warmTabPages = () => TAB_PAGES.forEach((name) => pages[`./Pages/${name}.vue`]?.());

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, pages),
    defaults: {
        // A prefetched page (the tab bar fetches its pages ahead) is fresh for 30 seconds and may be shown for up to five
        // minutes while a fresh copy loads behind it.
        prefetch: { cacheFor: ['30s', '5m'] },
        // A quick cross-fade between pages (the header and tab bar stay put, app.css), not for partial reloads, polling or
        // anyone who asks their phone for less motion.
        visitOptions: (href, options) => ({
            viewTransition: !reduceMotion.matches && (options.method ?? 'get') === 'get' && !options.async && !(options.only?.length) && !(options.except?.length),
        }),
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // On a club's static website there is no server to answer Inertia's requests: every visit is a full page load.
        if (props.initialPage?.props?.staticSite) {
            router.on('before', (event) => {
                if (event.detail.visit.method !== 'get') return true;
                window.location.href = event.detail.visit.url.href;
                return false;
            });
        }

        // Anything saved can change what another page shows: drop the pages fetched ahead so none is shown out of date.
        router.on('finish', (event) => {
            if ((event.detail.visit.method ?? 'get') !== 'get' && event.detail.visit.completed) {
                router.flushAll();
            }
        });

        if (!props.initialPage?.props?.staticSite && props.initialPage?.props?.auth?.user) {
            (window.requestIdleCallback ?? ((callback) => setTimeout(callback, 1500)))(warmTabPages);
        }

        // $cs is the symbol of the current club's currency and $money(amount) a whole amount in it, for use in templates.
        Object.defineProperty(app.config.globalProperties, '$cs', { get: () => currencySymbol() });
        app.config.globalProperties.$money = formatMoney;

        app.directive('focus-trap', focusTrap);

        startNativeApp(props.initialPage);
        startWebApp(props.initialPage);

        return app
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#6366f1',
    },
});
