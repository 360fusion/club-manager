/**
 * The colour of an order (Craft, Royal Arch, Rose Croix...), stored on the club type
 * and shown wherever a club appears: date tiles, calendar entries, chips and dots.
 *
 * The keys must match App\Support\OrderColours::KEYS. Class names are written out in
 * full so Tailwind can see them.
 */
const PALETTE = {
    red: { label: 'Red', tile: 'bg-red-600 text-white', soft: 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300', dot: 'bg-red-500' },
    orange: { label: 'Orange', tile: 'bg-orange-500 text-white', soft: 'bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300', dot: 'bg-orange-500' },
    amber: { label: 'Gold', tile: 'bg-amber-400 text-slate-900', soft: 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300', dot: 'bg-amber-500' },
    yellow: { label: 'Yellow', tile: 'bg-yellow-400 text-slate-900', soft: 'bg-yellow-50 text-yellow-800 dark:bg-yellow-950/50 dark:text-yellow-300', dot: 'bg-yellow-400' },
    lime: { label: 'Lime', tile: 'bg-lime-600 text-white', soft: 'bg-lime-50 text-lime-800 dark:bg-lime-950/50 dark:text-lime-300', dot: 'bg-lime-500' },
    green: { label: 'Green', tile: 'bg-green-600 text-white', soft: 'bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300', dot: 'bg-green-500' },
    emerald: { label: 'Emerald', tile: 'bg-emerald-600 text-white', soft: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300', dot: 'bg-emerald-500' },
    teal: { label: 'Teal', tile: 'bg-teal-600 text-white', soft: 'bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-300', dot: 'bg-teal-500' },
    cyan: { label: 'Cyan', tile: 'bg-cyan-600 text-white', soft: 'bg-cyan-50 text-cyan-800 dark:bg-cyan-950/50 dark:text-cyan-300', dot: 'bg-cyan-500' },
    sky: { label: 'Light blue', tile: 'bg-sky-600 text-white', soft: 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300', dot: 'bg-sky-400' },
    blue: { label: 'Blue', tile: 'bg-blue-600 text-white', soft: 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300', dot: 'bg-blue-500' },
    indigo: { label: 'Indigo', tile: 'bg-indigo-600 text-white', soft: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300', dot: 'bg-indigo-500' },
    violet: { label: 'Violet', tile: 'bg-violet-600 text-white', soft: 'bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300', dot: 'bg-violet-500' },
    purple: { label: 'Purple', tile: 'bg-purple-700 text-white', soft: 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300', dot: 'bg-purple-600' },
    fuchsia: { label: 'Magenta', tile: 'bg-fuchsia-600 text-white', soft: 'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-950/50 dark:text-fuchsia-300', dot: 'bg-fuchsia-500' },
    pink: { label: 'Pink', tile: 'bg-pink-500 text-white', soft: 'bg-pink-50 text-pink-700 dark:bg-pink-950/50 dark:text-pink-300', dot: 'bg-pink-400' },
    rose: { label: 'Rose', tile: 'bg-rose-600 text-white', soft: 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300', dot: 'bg-rose-500' },
    slate: { label: 'Black', tile: 'bg-slate-700 text-white dark:bg-slate-600', soft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300', dot: 'bg-slate-700 dark:bg-slate-400' },
    stone: { label: 'Stone', tile: 'bg-stone-500 text-white', soft: 'bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300', dot: 'bg-stone-500' },
};

export const ORDER_COLOURS = Object.entries(PALETTE).map(([key, value]) => ({ key, label: value.label, dot: value.dot }));

export function orderColour(key) {
    return PALETTE[key] ?? PALETTE.slate;
}
