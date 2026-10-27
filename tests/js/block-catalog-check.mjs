// Every element in the page editor's palette must produce a starting block of its own type.
import { BLOCK_COLUMNS, BLOCK_TYPES, newBlock } from '../../resources/js/Support/pageBlockCatalog.js';

const context = { club: { slug: 'check', name: 'Check Lodge', address: '1 High Street', settings: {} }, clubAddresses: [{ address: '1 High Street' }], websiteSettings: {} };
const problems = [];

for (const { type } of BLOCK_TYPES) {
    const block = newBlock(type, context);
    if (!block || block.type !== type) problems.push(`${type}: got ${block?.type ?? 'nothing'}`);
}

const menu = BLOCK_COLUMNS.flatMap((column) => column.items.map((item) => item.type));
for (const type of BLOCK_TYPES.map((t) => t.type)) {
    if (!menu.includes(type)) problems.push(`${type}: in the palette but not in the insert menu`);
}

if (problems.length) {
    console.error(problems.join('\n'));
    process.exit(1);
}
console.log(`ok: ${BLOCK_TYPES.length} element types build and appear in the insert menu`);
