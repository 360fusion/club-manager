// Fails when the app's Vue pages or Blade views break the design system's rules in a new place: a colour outside the
// palette or a hex colour, a button with 6px corners, an emoji in a button or link, or a Title Case button or link label.
// What was already there is listed in tests/js/design-baseline.json (counts per file and rule), so only new cases fail.
// After fixing some, run `node tests/js/design-check.mjs --update` to lower the baseline; the counts should only go down.
// Club websites, emails and PDF/print views are left out: they keep their own themes and styles.
import { globSync, readFileSync, writeFileSync } from 'node:fs';

const BASELINE = 'tests/js/design-baseline.json';
const SKIP = /Pages\/Public\/|Components\/(Site|Blocks)\/|views\/(emails|pdf|summons|events|club-accounting)\//;

// Tailwind colour families that are not in the palette (slate, blue, gold and the amber/emerald/rose status colours are).
const OFF_PALETTE = /(?<![\w-])(?:[a-z-]+:)*(?:bg|text|border|ring|from|to|via|divide|outline|decoration|fill|stroke|placeholder|accent|caret|shadow)-(?:red|green|yellow|orange|indigo|purple|violet|gray|zinc|neutral|stone|sky|cyan|teal|lime|pink|fuchsia)-\d{2,3}\b/g;
const HEX = /(?<![\w-])(?:[a-z-]+:)*[a-z-]+-\[#[0-9a-fA-F]{3,8}\]/g;
const EMOJI = /[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{2B50}\u{2B1C}\u{2705}\u{274C}]/u;

// Words that keep their capital in a sentence-case label: proper names, brands, places and Masonic bodies.
const KEEP = new Set(`Grand Provincial Royal Gift Masonic Stripe PayPal SumUp GoCardless Apple Google Xero HMRC UGLE Eventbrite Paddle
Relief Worshipful Freemasonry England Scotland Ireland Wales English Scottish Irish US UK ClubManager Cashier I P Visa Mastercard Craft
WhatsApp Facebook Instagram LinkedIn Outlook Microsoft Word Excel PowerPoint Zoom Festival Monday Tuesday Wednesday Thursday Friday Saturday Sunday
January February March April May June July August September October November December`.split(/\s+/));
// Words that keep their capital only right after the word that makes them a name ("Grand Lodge", "Apple Pay").
const AFTER = { Lodge: ['Grand'], Lodges: ['Grand'], Arch: ['Royal'], Aid: ['Gift'], Chest: ['Relief'], Master: ['Worshipful'], Pay: ['Apple', 'Google'], Connect: ['Stripe'] };

const BUTTON_OPEN = /<(button|Button|x-button|Link|a|label)\b((?:[^>"']|"[^"]*"|'[^']*')*)>/g;
const LABELLED = /<(button|Button|x-button|Link|a)\b(?:[^>"']|"[^"]*"|'[^']*')*>([\s\S]*?)<\/\1>/g;

const classesOf = (attrs) => [...attrs.matchAll(/\s:?class="([^"]*)"/g)].map((m) => m[1]).join(' ');

/** A filled or outlined button (or button-styled link or option) whose corners are rounded-lg (6px) instead of the kit's 8px. */
function sixPixelButtons(source) {
  let count = 0;
  for (const [, tag, attrs] of source.matchAll(BUTTON_OPEN)) {
    const classes = classesOf(attrs);
    if (!/(^|[\s'":])rounded-lg\b/.test(classes)) continue;
    if (!/(^|[\s'"])(hover:)?bg-|(^|[\s'"])border\b|border-[a-z]/.test(classes)) continue;
    if (['a', 'Link', 'label'].includes(tag) && !/\bp[xy]?-\d/.test(classes)) continue;
    count++;
  }
  return count;
}

/** Whether a label has a word after its first that is capitalised but is not a proper name. */
function isTitleCase(label) {
  const words = label.replace(/\bForm P\b/g, 'P').match(/[A-Za-z][A-Za-z']*/g) || [];
  return words.slice(1).some((word, i) => /^[A-Z][a-z]/.test(word) && !/[A-Z]/.test(word.slice(1)) && !KEEP.has(word) && !(AFTER[word] || []).includes(words[i]));
}

/** The labels inside a button or link: its text between tags and sentence ends, and each quoted string in its {{ }} expressions. */
function labelsOf(inner) {
  const labels = [];
  for (const expression of inner.match(/\{\{[\s\S]*?\}\}/g) || []) {
    for (const [, literal] of expression.matchAll(/'([^'{}]*[A-Za-z][^'{}]*)'/g)) {
      if (/^\W*[A-Z][a-z]/.test(literal) && literal.includes(' ')) labels.push(literal);
    }
  }
  // Tags go first, so Vue's @click inside a tag is not mistaken for a Blade directive between tags.
  const text = inner.replace(/\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\}/g, '<>').replace(/<[^>]*>/g, '<>').replace(/@\w+(\([^)]*\))?|&[a-z]+;|&#\d+;/g, '<>');
  for (const piece of text.split(/<>|(?<=[?.!…])\s+/)) {
    if (/[A-Za-z]/.test(piece)) labels.push(piece.trim());
  }
  return labels;
}

function countFile(file) {
  // A Blade @class([... => ...]) inside a tag would otherwise read as the end of the tag.
  const source = readFileSync(file, 'utf8').replace(/=>/g, '=:');
  const counts = {
    'off-palette colour': (source.match(OFF_PALETTE) || []).length,
    'hex colour': (source.match(HEX) || []).length,
    '6px button': sixPixelButtons(source),
    'emoji in a button or link': 0,
    'Title Case label': 0,
  };
  for (const [, , inner] of source.matchAll(LABELLED)) {
    if (/<(button|Button|a|Link|x-button|div|table|ul|section|p)\b/.test(inner) || inner.includes('Aa Bb')) continue;
    // Quoted labels inside {{ }} count too ('✓ Subscribed'); the rest of an expression does not.
    const shown = inner.replace(/\{\{[\s\S]*?\}\}/g, (expression) => (expression.match(/'[^']*'/g) || []).join(' '));
    if (EMOJI.test(shown)) counts['emoji in a button or link']++;
    counts['Title Case label'] += labelsOf(inner).filter(isTitleCase).length;
  }
  return Object.fromEntries(Object.entries(counts).filter(([, n]) => n > 0));
}

const files = [...globSync('resources/js/**/*.vue'), ...globSync('resources/views/**/*.blade.php')].filter((f) => !SKIP.test(f)).sort();
const current = Object.fromEntries(files.map((f) => [f, countFile(f)]).filter(([, c]) => Object.keys(c).length));

if (process.argv.includes('--update')) {
  writeFileSync(BASELINE, JSON.stringify(current, null, 2) + '\n');
  const total = Object.values(current).reduce((sum, c) => sum + Object.values(c).reduce((a, b) => a + b, 0), 0);
  console.log(`baseline written: ${total} known case(s) in ${Object.keys(current).length} file(s)`);
  process.exit(0);
}

const baseline = JSON.parse(readFileSync(BASELINE, 'utf8'));
let failures = 0;
let improved = 0;
for (const file of new Set([...Object.keys(current), ...Object.keys(baseline)])) {
  for (const rule of new Set([...Object.keys(current[file] || {}), ...Object.keys(baseline[file] || {})])) {
    const now = current[file]?.[rule] ?? 0;
    const allowed = baseline[file]?.[rule] ?? 0;
    if (now > allowed) {
      failures++;
      console.log(`${file}\n   ${rule}: ${now} (the baseline allows ${allowed})`);
    } else if (now < allowed) {
      improved++;
    }
  }
}

if (improved && !failures) console.log(`${improved} count(s) went down: run \`node tests/js/design-check.mjs --update\` to lower the baseline`);
console.log(failures
  ? `\n${failures} new design problem(s). Use the kit (resources/js/Components/Ui): palette colours, rounded-xl buttons, UiIcon instead of emoji, sentence-case labels.`
  : `ok: no new design problems (${files.length} files)`);
process.exit(failures ? 1 : 0);
