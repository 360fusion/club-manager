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

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
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
