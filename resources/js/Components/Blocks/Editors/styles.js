// Class strings shared by the block editors, matching the UI kit's fields (Components/Ui/Input.vue) at the editors' compact size.
const FIELD = 'w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed';
const ICON_BUTTON = 'inline-flex h-7 w-7 items-center justify-center rounded-xl border transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed';

export const LABEL = 'block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1';
export const INPUT = `${FIELD} px-3 py-2`;
export const SMALL_INPUT = `${FIELD} px-3 py-1.5`;
export const SELECT = `${FIELD} px-3 py-1.5`;
export const CARD = 'p-3 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800';
export const CHECK = 'w-4 h-4 rounded border-slate-300 dark:border-slate-700 text-blue-600 accent-blue-600';
export const CHECK_LABEL = 'flex items-center gap-2 font-medium text-slate-700 dark:text-slate-300 cursor-pointer';
// Icon-only row controls (move, duplicate, remove); text buttons use the kit's Button.
export const MINI_BUTTON = `${ICON_BUTTON} border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 hover:border-slate-400 dark:hover:bg-slate-800`;
export const MINI_DANGER = `${ICON_BUTTON} border-rose-200 dark:border-rose-800/60 bg-white dark:bg-slate-900 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40`;
export const HINT = 'text-[11px] text-slate-500 dark:text-slate-400 mt-1';
