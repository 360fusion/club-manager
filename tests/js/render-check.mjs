import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import path from 'node:path';

// Renders each component on the server with stand-in data and fails when its template reads a name that does not
// exist. It catches the mistake that is easiest to make when a section is moved out of a big page: a variable the
// section used to get from the page and now is not given.
// Usage: npm run check:components -- [file.vue ...]   (no arguments: the components split out of the big pages)
import { globSync } from 'node:fs';
const files = process.argv.slice(2).length ? process.argv.slice(2) : ['resources/js/Components/Settings/*.vue', 'resources/js/Components/Accounting/Reports/*.vue', 'resources/js/Components/Accounting/Sections/*.vue', 'resources/js/Components/Accounting/Modals/*.vue', 'resources/js/Components/Accounting/Tabs/*.vue', 'resources/js/Components/Accounting/Reconciliation/*.vue', 'resources/js/Components/Pages/*.vue', 'resources/js/Components/Officers/*.vue', 'resources/js/Components/Media/*.vue', 'resources/js/Components/Blocks/BlockRenderer.vue', 'resources/js/Components/Blocks/Editors/*.vue'].flatMap((g) => globSync(g));
const server = await createServer({
  root: process.cwd(),
  configFile: false,
  plugins: [vue()],
  resolve: { alias: { '@': path.resolve('resources/js'), '@inertiajs/vue3': path.resolve('tests/js/inertia-stub.js') } },
  server: { middlewareMode: true, hmr: false, watch: null },
  appType: 'custom',
  logLevel: 'error',
  ssr: { noExternal: ['@inertiajs/vue3'] },
});

// A value that answers every property and call, so a template can be rendered without real data.
const deep = (name = 'x') => new Proxy(function () {}, {
  get: (_, k) => {
    if (k === Symbol.toPrimitive) return () => '';
    if (k === 'toString' || k === 'valueOf') return () => '';
    if (k === 'length') return 0;
    if (k === Symbol.iterator) return function* () {};
    if (k === 'then') return undefined;
    if (k === '__v_isRef' || k === '__v_skip' || k === '__v_isReactive' || k === '__v_isReadonly' || k === 'toJSON') return false;
    return deep(String(k));
  },
  apply: () => deep(),
  has: () => true,
});

let failed = 0;
for (const file of files) {
  const warnings = [];
  try {
    const mod = await server.ssrLoadModule('/' + file);
    const props = {};
    for (const [k, v] of Object.entries(mod.default.props || {})) props[k] = deep(k);
    const app = createSSRApp(mod.default, props);
    app.config.warnHandler = (msg) => { if (!/Extraneous|Invalid prop|Missing required|was accessed during render but is not defined on instance/.test(msg) || /not defined on instance/.test(msg)) warnings.push(msg); };
    app.config.globalProperties.route = () => '#';
    app.config.globalProperties.$cs = '£';
    app.config.globalProperties.$page = { props: { auth: { user: {} }, currency: {} }, url: '/' };
    app.directive('focus-trap', {}); app.directive('focusTrap', {});
    for (const n of ['Link', 'Head']) app.component(n, { template: '<a><slot/></a>' });
    const html = await renderToString(app);
    const bad = warnings.filter((w) => /not defined on instance|Cannot read|is not defined/.test(w));
    console.log((bad.length ? 'WARN ' : 'ok   ') + file + ' (' + html.length + ' chars)' + (bad.length ? '\n   ' + [...new Set(bad)].slice(0, 5).join('\n   ') : ''));
    if (bad.length) failed++;
  } catch (e) {
    failed++;
    console.log('FAIL ' + file + ': ' + String(e.message).split('\n')[0]);
  }
}
await server.close();
process.exit(failed ? 1 : 0);
