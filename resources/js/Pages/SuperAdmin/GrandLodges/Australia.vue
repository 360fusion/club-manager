<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    australianGrandLodges: { type: Array, default: () => [] },
});

const searchQuery = ref('');

// Full static reference data — merged with live DB records
const stateGrandLodges = [
    {
        code: 'uglnsw',
        name: 'United Grand Lodge of New South Wales & ACT',
        shortName: 'UGL NSW & ACT',
        state: 'New South Wales & ACT',
        stateCode: 'NSW',
        founded: 1888,
        website: 'https://masons.org.au',
        headquartersCity: 'Sydney',
        lodgeCount: '400+',
        description: 'Governing body for New South Wales and the Australian Capital Territory. Headquartered at the Sydney Masonic Centre, one of the most impressive Masonic buildings in the southern hemisphere.',
    },
    {
        code: 'uglvic',
        name: 'United Grand Lodge of Victoria',
        shortName: 'UGL Victoria',
        state: 'Victoria',
        stateCode: 'VIC',
        founded: 1883,
        website: 'https://masonsvictoria.com.au',
        headquartersCity: 'Melbourne',
        lodgeCount: '300+',
        description: 'Governing body for Victoria. Headquartered at the Melbourne Masonic Centre, Collins Street. Founded in 1883 from the amalgamation of two rival Grand Lodges.',
    },
    {
        code: 'uglqld',
        name: 'United Grand Lodge of Queensland',
        shortName: 'UGL Queensland',
        state: 'Queensland',
        stateCode: 'QLD',
        founded: 1921,
        website: 'https://uglq.org.au',
        headquartersCity: 'Brisbane',
        lodgeCount: '200+',
        description: 'Governing body for Queensland. Formed in 1921 by the union of two competing Queensland Masonic jurisdictions. Headquartered in Brisbane.',
    },
    {
        code: 'glsant',
        name: 'Grand Lodge of South Australia & Northern Territory',
        shortName: 'GLSANT',
        state: 'South Australia & NT',
        stateCode: 'SA/NT',
        founded: 1884,
        website: 'https://glsa.org.au',
        headquartersCity: 'Adelaide',
        lodgeCount: '130+',
        description: 'Governing body for South Australia and the Northern Territory. Covers an enormous geographic area including remote outback lodges. Headquartered in Adelaide.',
    },
    {
        code: 'glwa',
        name: 'Grand Lodge of Western Australia',
        shortName: 'GL Western Australia',
        state: 'Western Australia',
        stateCode: 'WA',
        founded: 1900,
        website: 'https://freemasonrywa.org.au',
        headquartersCity: 'Perth',
        lodgeCount: '100+',
        description: 'Governing body for Western Australia. Founded in 1900 at the height of the WA gold rush era. Headquartered at the Perth Masonic Centre.',
    },
    {
        code: 'gltas',
        name: 'Grand Lodge of Tasmania',
        shortName: 'GL Tasmania',
        state: 'Tasmania',
        stateCode: 'TAS',
        founded: 1890,
        website: 'https://freemasonrytasmania.org',
        headquartersCity: 'Hobart',
        lodgeCount: '40+',
        description: 'The smallest of Australia\'s six state Grand Lodges. Many Tasmanian lodges hold colonial-era warrants dating back to convict-era Australia. Headquartered in Hobart.',
    },
];

const filteredLodges = computed(() => {
    if (!searchQuery.value.trim()) return stateGrandLodges;
    const q = searchQuery.value.toLowerCase().trim();
    return stateGrandLodges.filter(gl =>
        gl.name.toLowerCase().includes(q) ||
        gl.shortName.toLowerCase().includes(q) ||
        gl.state.toLowerCase().includes(q) ||
        gl.stateCode.toLowerCase().includes(q) ||
        gl.headquartersCity.toLowerCase().includes(q) ||
        gl.code.toLowerCase().includes(q)
    );
});

function dbEntry(code) {
    return props.australianGrandLodges.find(gl => gl.code === code);
}
</script>

<template>
    <SuperAdminLayout title="Australian Grand Lodges">
        <Head title="Australian Grand Lodges - Superadmin" />

        <div class="space-y-6">

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
                        <Link :href="route('superadmin.dashboard')" class="hover:text-white transition">Dashboard</Link>
                        <span>/</span>
                        <span class="text-emerald-400 font-bold">Australian Grand Lodges</span>
                    </div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <span>🦘</span>
                        <span>Australian Grand Lodges</span>
                    </h1>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Australia has no national Grand Lodge. Each state operates a fully independent, sovereign Grand Lodge in amity with UGLE.
                    </p>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-lg space-y-1">
                    <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider block">State Grand Lodges</span>
                    <span class="text-2xl font-black text-white block">6</span>
                    <span class="text-[11px] text-slate-400 block font-medium">Fully sovereign bodies</span>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-lg space-y-1">
                    <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider block">In Database</span>
                    <span class="text-2xl font-black text-white block">{{ australianGrandLodges.length }}</span>
                    <span class="text-[11px] text-slate-400 block font-medium">of 6 seeded</span>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-lg space-y-1">
                    <span class="text-[10px] font-bold text-purple-400 uppercase tracking-wider block">Total Registered Lodges</span>
                    <span class="text-2xl font-black text-white block">
                        {{ australianGrandLodges.reduce((sum, gl) => sum + (gl.clubs_count || 0), 0) || '—' }}
                    </span>
                    <span class="text-[11px] text-slate-400 block font-medium">Across all Australian states</span>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-lg space-y-1">
                    <span class="text-[10px] font-bold text-purple-400 uppercase tracking-wider block">Coordination Body</span>
                    <span class="text-sm font-black text-white block leading-tight">Australasian Masonic Forum</span>
                    <span class="text-[11px] text-slate-400 block font-medium">Advisory only, no authority</span>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-lg">
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                    <div class="relative flex-1 max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                            🔍
                        </div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by state, code, city, or name..."
                            class="w-full bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-xs rounded-xl pl-9 pr-8 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white text-xs"
                        >
                            ✕
                        </button>
                    </div>
                    <div class="text-xs text-slate-400 font-medium">
                        Showing <strong class="text-white">{{ filteredLodges.length }}</strong> of 6 Grand Lodges
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-xl">
                <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                    <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        Showing {{ filteredLodges.length }} of 6 Australian State Grand Lodges
                    </h2>
                    <span v-if="australianGrandLodges.length < 6" class="text-[10px] text-amber-400 font-semibold">
                        ⚠️ {{ 6 - australianGrandLodges.length }} not yet in database
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-950/60 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4">Grand Lodge</th>
                                <th class="py-3 px-3">State / Territory</th>
                                <th class="py-3 px-3">Code</th>
                                <th class="py-3 px-3">HQ City</th>
                                <th class="py-3 px-3">Est.</th>
                                <th class="py-3 px-3">Approx. Lodges</th>
                                <th class="py-3 px-3">Database</th>
                                <th class="py-3 px-3">Website</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-xs">
                            <tr v-for="gl in filteredLodges" :key="gl.code" class="hover:bg-slate-800/40 transition group">

                                <!-- Name -->
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white group-hover:text-emerald-300 transition">{{ gl.name }}</div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">{{ gl.description }}</div>
                                </td>

                                <!-- State -->
                                <td class="py-3.5 px-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-950/60 text-emerald-300 border border-emerald-800/40">
                                        🇦🇺 {{ gl.stateCode }}
                                    </span>
                                    <div class="text-[10px] text-slate-500 mt-0.5">{{ gl.state }}</div>
                                </td>

                                <!-- Code -->
                                <td class="py-3.5 px-3 font-mono text-indigo-400">
                                    {{ gl.code }}
                                </td>

                                <!-- HQ -->
                                <td class="py-3.5 px-3 text-slate-300 font-semibold">
                                    {{ gl.headquartersCity }}
                                </td>

                                <!-- Founded -->
                                <td class="py-3.5 px-3 text-slate-400">
                                    {{ gl.founded }}
                                </td>

                                <!-- Lodge Count -->
                                <td class="py-3.5 px-3">
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-950 text-indigo-300 border border-indigo-800/60">
                                        {{ gl.lodgeCount }}
                                    </span>
                                </td>

                                <!-- DB Status -->
                                <td class="py-3.5 px-3">
                                    <span v-if="dbEntry(gl.code)" class="px-2 py-0.5 bg-emerald-900/60 text-emerald-400 text-[10px] font-bold rounded border border-emerald-700/50">
                                        ✓ Seeded
                                    </span>
                                    <span v-else class="px-2 py-0.5 bg-rose-900/60 text-rose-400 text-[10px] font-bold rounded border border-rose-700/50">
                                        ✗ Missing
                                    </span>
                                </td>

                                <!-- Website -->
                                <td class="py-3.5 px-3">
                                    <a :href="gl.website" target="_blank" rel="noopener" class="text-indigo-400 hover:underline flex items-center gap-1 whitespace-nowrap">
                                        <span>Website</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>

                            <tr v-if="filteredLodges.length === 0">
                                <td colspan="8" class="py-12 px-4 text-center text-slate-400 space-y-2">
                                    <span class="text-2xl block">🔍</span>
                                    <p class="font-bold text-sm text-slate-300">No Grand Lodges found matching your search</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Info footer -->
            <div class="bg-amber-950/20 border border-amber-800/30 rounded-2xl p-4 flex items-start gap-3">
                <span class="text-lg mt-0.5">ℹ️</span>
                <p class="text-xs text-amber-200/70 leading-relaxed">
                    <span class="font-bold text-amber-300">Note:</span>
                    These six Grand Lodges are all fully sovereign — equivalent in standing to UGLE itself. They are not subordinate to UGLE or to each other.
                    Lodges in each state hold their warrant directly from their state Grand Lodge.
                    All six are in amity with UGLE and coordinate via the <span class="font-semibold text-white">Australasian Masonic Forum</span> (advisory only).
                </p>
            </div>

        </div>
    </SuperAdminLayout>
</template>
