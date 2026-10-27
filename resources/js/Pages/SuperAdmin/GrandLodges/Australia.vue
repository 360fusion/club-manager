<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Alert from '@/Components/Ui/Alert.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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

            <PageHeader
                title="Australian Grand Lodges"
                description="Australia has no national Grand Lodge. Each state operates a fully independent, sovereign Grand Lodge in amity with UGLE."
                :breadcrumbs="[{ label: 'Dashboard', href: route('superadmin.dashboard') }, { label: 'Australian Grand Lodges' }]"
            />

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <StatCard label="State Grand Lodges" :value="6" caption="Fully sovereign bodies" />
                <StatCard label="In database" :value="australianGrandLodges.length" caption="of 6 seeded" />
                <StatCard
                    label="Total registered lodges"
                    :value="australianGrandLodges.reduce((sum, gl) => sum + (gl.clubs_count || 0), 0) || '—'"
                    caption="Across all Australian states"
                />
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm dark:bg-slate-900 dark:border-slate-800">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Coordination body</p>
                    <p class="mt-1 text-sm font-semibold leading-tight text-slate-900 dark:text-white">Australasian Masonic Forum</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Advisory only, no authority</p>
                </div>
            </div>

            <Card padding="sm">
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                    <div class="relative flex-1 max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                            <UiIcon name="search" />
                        </div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            aria-label="Search Grand Lodges"
                            placeholder="Search by state, code, city or name..."
                            class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-9 text-sm text-slate-900 placeholder-slate-400 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            type="button"
                            aria-label="Clear search"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-700 dark:text-slate-500 dark:hover:text-white"
                        >
                            <UiIcon name="x" />
                        </button>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Showing <span class="font-semibold text-slate-900 dark:text-white">{{ filteredLodges.length }}</span> of 6 Grand Lodges
                    </div>
                </div>
            </Card>

            <Card padding="none" class="overflow-hidden">
                <div class="p-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 dark:border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider dark:text-slate-400">
                        Showing {{ filteredLodges.length }} of 6 Australian state Grand Lodges
                    </h2>
                    <Badge v-if="australianGrandLodges.length < 6" variant="warning">
                        <UiIcon name="warning" :size="16" />
                        {{ 6 - australianGrandLodges.length }} not yet in database
                    </Badge>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="py-3 px-4 font-medium">Grand Lodge</th>
                                <th scope="col" class="py-3 px-3 font-medium">State or territory</th>
                                <th scope="col" class="py-3 px-3 font-medium">Code</th>
                                <th scope="col" class="py-3 px-3 font-medium">HQ city</th>
                                <th scope="col" class="py-3 px-3 font-medium">Est.</th>
                                <th scope="col" class="py-3 px-3 font-medium">Approx. lodges</th>
                                <th scope="col" class="py-3 px-3 font-medium">Database</th>
                                <th scope="col" class="py-3 px-3 font-medium">Website</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-xs dark:divide-slate-800">
                            <tr v-for="gl in filteredLodges" :key="gl.code" class="hover:bg-slate-50 transition-colors dark:hover:bg-slate-800/40">

                                <!-- Name -->
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ gl.name }}</div>
                                </td>

                                <!-- State -->
                                <td class="py-3.5 px-3">
                                    <Badge>{{ gl.stateCode }}</Badge>
                                    <div class="text-[10px] text-slate-500 mt-0.5 dark:text-slate-400">{{ gl.state }}</div>
                                </td>

                                <!-- Code -->
                                <td class="py-3.5 px-3 font-mono text-blue-600 dark:text-blue-400">
                                    {{ gl.code }}
                                </td>

                                <!-- HQ -->
                                <td class="py-3.5 px-3 text-slate-700 font-semibold dark:text-slate-300">
                                    {{ gl.headquartersCity }}
                                </td>

                                <!-- Founded -->
                                <td class="py-3.5 px-3 text-slate-500 dark:text-slate-400">
                                    {{ gl.founded }}
                                </td>

                                <!-- Lodge Count -->
                                <td class="py-3.5 px-3">
                                    <Badge variant="info">{{ gl.lodgeCount }}</Badge>
                                </td>

                                <!-- DB Status -->
                                <td class="py-3.5 px-3">
                                    <Badge v-if="dbEntry(gl.code)" variant="success">Seeded</Badge>
                                    <Badge v-else variant="danger">Missing</Badge>
                                </td>

                                <!-- Website -->
                                <td class="py-3.5 px-3">
                                    <a :href="gl.website" target="_blank" rel="noopener" class="text-blue-600 hover:underline flex items-center gap-1 whitespace-nowrap dark:text-blue-400">
                                        <span>Website</span>
                                        <UiIcon name="external-link" />
                                    </a>
                                </td>
                            </tr>

                            <tr v-if="filteredLodges.length === 0">
                                <td colspan="8" class="py-12 px-4 text-center text-slate-500 dark:text-slate-400">
                                    <span class="mb-2 flex justify-center text-slate-400 dark:text-slate-500"><UiIcon name="search" :size="20" /></span>
                                    <p class="font-medium text-sm text-slate-700 dark:text-slate-300">No Grand Lodges found matching your search</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Alert variant="info" title="Note">
                These six Grand Lodges are all fully sovereign — equivalent in standing to UGLE itself. They are not subordinate to UGLE or to each other.
                Lodges in each state hold their warrant directly from their state Grand Lodge.
                All six are in amity with UGLE and coordinate via the <span class="font-semibold">Australasian Masonic Forum</span> (advisory only).
            </Alert>

        </div>
    </SuperAdminLayout>
</template>
