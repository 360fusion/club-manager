<div class="bg-white border border-slate-200/90 rounded-2xl p-2 shadow-sm flex items-center justify-between gap-2 overflow-x-auto mb-4">
    <div class="flex items-center gap-1.5">
        <a
            href="{{ route('admin.club_acc.charity.index', $clubSlug) }}"
            class="px-4 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2 whitespace-nowrap {{ request()->routeIs('admin.club_acc.charity.index') ? 'bg-purple-700 text-white shadow-md font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}"
        >
            <span>📊</span>
            <span>Charity Dashboard</span>
        </a>

        <a
            href="{{ route('admin.charity.giftaid.transactions_page', $clubSlug) }}"
            class="px-4 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2 whitespace-nowrap {{ request()->routeIs('admin.charity.giftaid.*', 'admin.accounting.giftaid.*') ? 'bg-purple-700 text-white shadow-md font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}"
        >
            <span>🏛️</span>
            <span>Gift Aid Page</span>
        </a>

        <a
            href="{{ route('admin.club_acc.charity.festival', $clubSlug) }}"
            class="px-4 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2 whitespace-nowrap {{ request()->routeIs('admin.club_acc.charity.festival') ? 'bg-purple-700 text-white shadow-md font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}"
        >
            <span>🏆</span>
            <span>Festival Information</span>
        </a>
    </div>
</div>
