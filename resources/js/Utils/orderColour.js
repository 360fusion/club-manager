/**
 * The colour of an order (Craft, Royal Arch, Rose Croix...), stored on the club type
 * and shown wherever a club appears: date tiles, calendar entries, chips and dots.
 *
 * The keys must match App\Support\OrderColours::KEYS. Class names are written out in
 * full so Tailwind can see them. `header` is the lodge header in the members area (MembersLayout): a deep shade,
 * so white text reads on it.
 */
const PALETTE = {
    red: { label: 'Red', tile: 'bg-red-600 text-white', soft: 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300', dot: 'bg-red-500', header: 'bg-red-800 dark:bg-red-900' },
    orange: { label: 'Orange', tile: 'bg-orange-500 text-white', soft: 'bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300', dot: 'bg-orange-500', header: 'bg-orange-800 dark:bg-orange-900' },
    amber: { label: 'Gold', tile: 'bg-amber-400 text-slate-900', soft: 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300', dot: 'bg-amber-500', header: 'bg-amber-800 dark:bg-amber-900' },
    yellow: { label: 'Yellow', tile: 'bg-yellow-400 text-slate-900', soft: 'bg-yellow-50 text-yellow-800 dark:bg-yellow-950/50 dark:text-yellow-300', dot: 'bg-yellow-400', header: 'bg-yellow-800 dark:bg-yellow-900' },
    lime: { label: 'Lime', tile: 'bg-lime-600 text-white', soft: 'bg-lime-50 text-lime-800 dark:bg-lime-950/50 dark:text-lime-300', dot: 'bg-lime-500', header: 'bg-lime-800 dark:bg-lime-900' },
    green: { label: 'Green', tile: 'bg-green-600 text-white', soft: 'bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300', dot: 'bg-green-500', header: 'bg-green-800 dark:bg-green-900' },
    emerald: { label: 'Emerald', tile: 'bg-emerald-700 text-white', soft: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300', dot: 'bg-emerald-500', header: 'bg-emerald-800 dark:bg-emerald-900' },
    teal: { label: 'Teal', tile: 'bg-teal-600 text-white', soft: 'bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-300', dot: 'bg-teal-500', header: 'bg-teal-800 dark:bg-teal-900' },
    cyan: { label: 'Cyan', tile: 'bg-cyan-600 text-white', soft: 'bg-cyan-50 text-cyan-800 dark:bg-cyan-950/50 dark:text-cyan-300', dot: 'bg-cyan-500', header: 'bg-cyan-800 dark:bg-cyan-900' },
    sky: { label: 'Light blue', tile: 'bg-sky-600 text-white', soft: 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300', dot: 'bg-sky-400', header: 'bg-sky-800 dark:bg-sky-900' },
    blue: { label: 'Blue', tile: 'bg-blue-600 text-white', soft: 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300', dot: 'bg-blue-500', header: 'bg-blue-800 dark:bg-blue-900' },
    indigo: { label: 'Indigo', tile: 'bg-indigo-600 text-white', soft: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300', dot: 'bg-indigo-500', header: 'bg-indigo-800 dark:bg-indigo-900' },
    violet: { label: 'Violet', tile: 'bg-violet-600 text-white', soft: 'bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300', dot: 'bg-violet-500', header: 'bg-violet-800 dark:bg-violet-900' },
    purple: { label: 'Purple', tile: 'bg-purple-700 text-white', soft: 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300', dot: 'bg-purple-600', header: 'bg-purple-800 dark:bg-purple-900' },
    fuchsia: { label: 'Magenta', tile: 'bg-fuchsia-600 text-white', soft: 'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-950/50 dark:text-fuchsia-300', dot: 'bg-fuchsia-500', header: 'bg-fuchsia-800 dark:bg-fuchsia-900' },
    pink: { label: 'Pink', tile: 'bg-pink-500 text-white', soft: 'bg-pink-50 text-pink-700 dark:bg-pink-950/50 dark:text-pink-300', dot: 'bg-pink-400', header: 'bg-pink-800 dark:bg-pink-900' },
    rose: { label: 'Rose', tile: 'bg-rose-600 text-white', soft: 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300', dot: 'bg-rose-500', header: 'bg-rose-800 dark:bg-rose-900' },
    slate: { label: 'Black', tile: 'bg-slate-700 text-white dark:bg-slate-600', soft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300', dot: 'bg-slate-700 dark:bg-slate-400', header: 'bg-slate-800 dark:bg-slate-900' },
    stone: { label: 'Stone', tile: 'bg-stone-500 text-white', soft: 'bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300', dot: 'bg-stone-500', header: 'bg-stone-700 dark:bg-stone-800' },
};

export const ORDER_COLOURS = Object.entries(PALETTE).map(([key, value]) => ({ key, label: value.label, dot: value.dot }));

export function orderColour(key) {
    return PALETTE[key] ?? PALETTE.slate;
}
