// Fails when a template reads a name that its <script setup> does not define (it would be undefined at runtime).
// Checks every component, so a section moved out of a big page cannot leave the page or the new component pointing at nothing.
import { globSync, readFileSync } from 'node:fs';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';

const GLOBAL_COMPONENTS = new Set([]); // components registered on the app itself; none today
const GLOBALS = new Set(['route']); // Ziggy's route() is global; $-names (such as $page, $cs) are Vue and app globals
const files = process.argv.slice(2).length ? process.argv.slice(2) : globSync('resources/js/**/*.vue');
let problems = 0;

for (const file of files) {
  const source = readFileSync(file, 'utf8');
  const { descriptor, errors } = parse(source, { filename: file });
  if (errors.length || !descriptor.template) continue;
  const id = file;
  let bindings;
  if (descriptor.scriptSetup || descriptor.script) {
    bindings = compileScript(descriptor, { id }).bindings;
  }
  const out = compileTemplate({ source: descriptor.template.content, filename: file, id, compilerOptions: { bindingMetadata: bindings, prefixIdentifiers: true } });
  const names = new Set([...out.code.matchAll(/_ctx\.([A-Za-z_]\w*)/g)].map((m) => m[1]));
  const unknown = [...names].filter((n) => !GLOBALS.has(n));
  // A <Component /> tag with no import is left in the page as an unknown element, so it renders nothing and Vue only warns in development.
  const tags = [...out.code.matchAll(/_resolveComponent\("([^"]+)"/g)].map((m) => m[1]);
  const unimported = tags.filter((tag) => !GLOBAL_COMPONENTS.has(tag) && !bindings?.[tag] && !bindings?.[tag.replace(/-(\w)/g, (_, c) => c.toUpperCase())]);
  if (unimported.length) {
    problems += unimported.length;
    console.log(`${file}\n   uses components that are not imported: ${unimported.join(', ')}`);
  }
  if (unknown.length) {
    problems += unknown.length;
    console.log(`${file}\n   reads names the script does not define: ${unknown.join(', ')}`);
  }
}

console.log(problems ? `\n${problems} unresolved name(s)` : `ok: every template name resolves (${files.length} components)`);
process.exit(problems ? 1 : 0);
