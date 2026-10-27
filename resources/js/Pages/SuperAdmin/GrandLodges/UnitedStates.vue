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
    usGrandLodges: { type: Array, default: () => [] },
});

const searchQuery = ref('');
const activeRegion = ref('All');

// Full static reference data — merged with live DB records by `code`.
// Founding years are well documented. Headquarters cities and approximate
// lodge counts are best-known values and are worth spot-checking.
const stateGrandLodges = [
    // Northeast
    { code: 'usglct', name: 'Grand Lodge of Connecticut AF&AM', shortName: 'GL Connecticut', state: 'Connecticut', stateCode: 'CT', region: 'Northeast', founded: 1789, website: 'https://ctfreemasons.net', headquartersCity: 'Wallingford', lodgeCount: '90+' },
    { code: 'usglme', name: 'Grand Lodge of Maine AF&AM', shortName: 'GL Maine', state: 'Maine', stateCode: 'ME', region: 'Northeast', founded: 1820, website: 'https://mainemason.org', headquartersCity: 'Holden', lodgeCount: '150+' },
    { code: 'usglma', name: 'Grand Lodge of Massachusetts AF&AM', shortName: 'GL Massachusetts', state: 'Massachusetts', stateCode: 'MA', region: 'Northeast', founded: 1733, website: 'https://massfreemasonry.org', headquartersCity: 'Boston', lodgeCount: '200+' },
    { code: 'usglnh', name: 'Grand Lodge of New Hampshire F&AM', shortName: 'GL New Hampshire', state: 'New Hampshire', stateCode: 'NH', region: 'Northeast', founded: 1789, website: 'https://nhgrandlodge.org', headquartersCity: 'Concord', lodgeCount: '60+' },
    { code: 'usglnj', name: 'Grand Lodge of New Jersey F&AM', shortName: 'GL New Jersey', state: 'New Jersey', stateCode: 'NJ', region: 'Northeast', founded: 1786, website: 'https://newjerseygrandlodge.org', headquartersCity: 'Burlington', lodgeCount: '100+' },
    { code: 'usglny', name: 'Grand Lodge of New York F&AM', shortName: 'GL New York', state: 'New York', stateCode: 'NY', region: 'Northeast', founded: 1781, website: 'https://nymasons.org', headquartersCity: 'New York City', lodgeCount: '400+' },
    { code: 'usglpa', name: 'Grand Lodge of Pennsylvania F&AM', shortName: 'GL Pennsylvania', state: 'Pennsylvania', stateCode: 'PA', region: 'Northeast', founded: 1731, website: 'https://pagrandlodge.org', headquartersCity: 'Philadelphia', lodgeCount: '400+' },
    { code: 'usglri', name: 'Grand Lodge of Rhode Island AF&AM', shortName: 'GL Rhode Island', state: 'Rhode Island', stateCode: 'RI', region: 'Northeast', founded: 1791, website: 'https://rimasons.org', headquartersCity: 'East Providence', lodgeCount: '25+' },
    { code: 'usglvt', name: 'Grand Lodge of Vermont F&AM', shortName: 'GL Vermont', state: 'Vermont', stateCode: 'VT', region: 'Northeast', founded: 1794, website: 'https://vtfreemasons.org', headquartersCity: 'Barre', lodgeCount: '70+' },

    // South
    { code: 'usglal', name: 'Grand Lodge of Alabama F&AM', shortName: 'GL Alabama', state: 'Alabama', stateCode: 'AL', region: 'South', founded: 1821, website: 'https://alagl.org', headquartersCity: 'Montgomery', lodgeCount: '250+' },
    { code: 'usglar', name: 'Grand Lodge of Arkansas F&AM', shortName: 'GL Arkansas', state: 'Arkansas', stateCode: 'AR', region: 'South', founded: 1838, website: null, headquartersCity: 'Little Rock', lodgeCount: '250+' },
    { code: 'usglde', name: 'Grand Lodge of Delaware AF&AM', shortName: 'GL Delaware', state: 'Delaware', stateCode: 'DE', region: 'South', founded: 1806, website: 'https://masonsindelaware.org', headquartersCity: 'Wilmington', lodgeCount: '20+' },
    { code: 'usgldc', name: 'Grand Lodge of the District of Columbia FAAM', shortName: 'GL District of Columbia', state: 'District of Columbia', stateCode: 'DC', region: 'South', founded: 1811, website: 'https://dcgrandlodge.org', headquartersCity: 'Washington, D.C.', lodgeCount: '30+' },
    { code: 'usglfl', name: 'Grand Lodge of Florida F&AM', shortName: 'GL Florida', state: 'Florida', stateCode: 'FL', region: 'South', founded: 1830, website: 'https://grandlodgefl.com', headquartersCity: 'Jacksonville', lodgeCount: '300+' },
    { code: 'usglga', name: 'Grand Lodge of Georgia F&AM', shortName: 'GL Georgia', state: 'Georgia', stateCode: 'GA', region: 'South', founded: 1786, website: 'https://glofga.org', headquartersCity: 'Macon', lodgeCount: '300+' },
    { code: 'usglky', name: 'Grand Lodge of Kentucky F&AM', shortName: 'GL Kentucky', state: 'Kentucky', stateCode: 'KY', region: 'South', founded: 1800, website: 'https://glky.org', headquartersCity: 'Louisville', lodgeCount: '350+' },
    { code: 'usglla', name: 'Grand Lodge of Louisiana F&AM', shortName: 'GL Louisiana', state: 'Louisiana', stateCode: 'LA', region: 'South', founded: 1812, website: 'https://la-mason.com', headquartersCity: 'Alexandria', lodgeCount: '180+' },
    { code: 'usglmd', name: 'Grand Lodge of Maryland AF&AM', shortName: 'GL Maryland', state: 'Maryland', stateCode: 'MD', region: 'South', founded: 1783, website: 'https://mdmasons.org', headquartersCity: 'Cockeysville', lodgeCount: '90+' },
    { code: 'usglms', name: 'Grand Lodge of Mississippi F&AM', shortName: 'GL Mississippi', state: 'Mississippi', stateCode: 'MS', region: 'South', founded: 1818, website: 'https://msgrandlodge.org', headquartersCity: 'Meridian', lodgeCount: '200+' },
    { code: 'usglnc', name: 'Grand Lodge of North Carolina AF&AM', shortName: 'GL North Carolina', state: 'North Carolina', stateCode: 'NC', region: 'South', founded: 1787, website: 'https://ncfreemasons.org', headquartersCity: 'Raleigh', lodgeCount: '300+' },
    { code: 'usglok', name: 'Grand Lodge of Oklahoma AF&AM', shortName: 'GL Oklahoma', state: 'Oklahoma', stateCode: 'OK', region: 'South', founded: 1909, website: 'https://gloklahoma.com', headquartersCity: 'Guthrie', lodgeCount: '180+' },
    { code: 'usglsc', name: 'Grand Lodge of South Carolina AFM', shortName: 'GL South Carolina', state: 'South Carolina', stateCode: 'SC', region: 'South', founded: 1787, website: 'https://scgrandlodgeafm.org', headquartersCity: 'Columbia', lodgeCount: '250+' },
    { code: 'usgltn', name: 'Grand Lodge of Tennessee F&AM', shortName: 'GL Tennessee', state: 'Tennessee', stateCode: 'TN', region: 'South', founded: 1813, website: 'https://grandlodge-tn.org', headquartersCity: 'Nashville', lodgeCount: '300+' },
    { code: 'usgltx', name: 'Grand Lodge of Texas AF&AM', shortName: 'GL Texas', state: 'Texas', stateCode: 'TX', region: 'South', founded: 1837, website: 'https://grandlodgeoftexas.org', headquartersCity: 'Waco', lodgeCount: '800+' },
    { code: 'usglva', name: 'Grand Lodge of Virginia AF&AM', shortName: 'GL Virginia', state: 'Virginia', stateCode: 'VA', region: 'South', founded: 1778, website: 'https://grandlodgeofvirginia.org', headquartersCity: 'Richmond', lodgeCount: '250+' },
    { code: 'usglwv', name: 'Grand Lodge of West Virginia AF&AM', shortName: 'GL West Virginia', state: 'West Virginia', stateCode: 'WV', region: 'South', founded: 1865, website: 'https://wvmasons.org', headquartersCity: 'Charleston', lodgeCount: '100+' },

    // Midwest
    { code: 'usglil', name: 'Grand Lodge of Illinois AF&AM', shortName: 'GL Illinois', state: 'Illinois', stateCode: 'IL', region: 'Midwest', founded: 1840, website: 'https://ilmason.org', headquartersCity: 'Springfield', lodgeCount: '400+' },
    { code: 'usglin', name: 'Grand Lodge of Indiana F&AM', shortName: 'GL Indiana', state: 'Indiana', stateCode: 'IN', region: 'Midwest', founded: 1818, website: 'https://indianafreemasons.com', headquartersCity: 'Indianapolis', lodgeCount: '350+' },
    { code: 'usglia', name: 'Grand Lodge of Iowa AF&AM', shortName: 'GL Iowa', state: 'Iowa', stateCode: 'IA', region: 'Midwest', founded: 1844, website: 'https://gl-iowa.org', headquartersCity: 'Cedar Rapids', lodgeCount: '200+' },
    { code: 'usglks', name: 'Grand Lodge of Kansas AF&AM', shortName: 'GL Kansas', state: 'Kansas', stateCode: 'KS', region: 'Midwest', founded: 1856, website: 'https://kansasmason.org', headquartersCity: 'Topeka', lodgeCount: '200+' },
    { code: 'usglmi', name: 'Grand Lodge of Michigan F&AM', shortName: 'GL Michigan', state: 'Michigan', stateCode: 'MI', region: 'Midwest', founded: 1826, website: 'https://michiganmasons.org', headquartersCity: 'Grand Rapids', lodgeCount: '250+' },
    { code: 'usglmn', name: 'Grand Lodge of Minnesota AF&AM', shortName: 'GL Minnesota', state: 'Minnesota', stateCode: 'MN', region: 'Midwest', founded: 1853, website: 'https://mnfreemasons.org', headquartersCity: 'Bloomington', lodgeCount: '130+' },
    { code: 'usglmo', name: 'Grand Lodge of Missouri AF&AM', shortName: 'GL Missouri', state: 'Missouri', stateCode: 'MO', region: 'Midwest', founded: 1821, website: 'https://momason.org', headquartersCity: 'Columbia', lodgeCount: '300+' },
    { code: 'usglne', name: 'Grand Lodge of Nebraska AF&AM', shortName: 'GL Nebraska', state: 'Nebraska', stateCode: 'NE', region: 'Midwest', founded: 1857, website: 'https://glne.org', headquartersCity: 'Omaha', lodgeCount: '120+' },
    { code: 'usglnd', name: 'Grand Lodge of North Dakota AF&AM', shortName: 'GL North Dakota', state: 'North Dakota', stateCode: 'ND', region: 'Midwest', founded: 1889, website: 'https://ndmasons.com', headquartersCity: 'Fargo', lodgeCount: '40+' },
    { code: 'usgloh', name: 'Grand Lodge of Ohio F&AM', shortName: 'GL Ohio', state: 'Ohio', stateCode: 'OH', region: 'Midwest', founded: 1808, website: 'https://freemason.com', headquartersCity: 'Worthington', lodgeCount: '400+' },
    { code: 'usglsd', name: 'Grand Lodge of South Dakota AF&AM', shortName: 'GL South Dakota', state: 'South Dakota', stateCode: 'SD', region: 'Midwest', founded: 1875, website: 'https://sdgrandlodge.org', headquartersCity: 'Sioux Falls', lodgeCount: '70+' },
    { code: 'usglwi', name: 'Grand Lodge of Wisconsin F&AM', shortName: 'GL Wisconsin', state: 'Wisconsin', stateCode: 'WI', region: 'Midwest', founded: 1843, website: 'https://wimasons.org', headquartersCity: 'Dousman', lodgeCount: '120+' },

    // West
    { code: 'usglak', name: 'Grand Lodge of Alaska F&AM', shortName: 'GL Alaska', state: 'Alaska', stateCode: 'AK', region: 'West', founded: 1981, website: 'http://alaska-mason.org', headquartersCity: 'Anchorage', lodgeCount: '15+' },
    { code: 'usglaz', name: 'Grand Lodge of Arizona F&AM', shortName: 'GL Arizona', state: 'Arizona', stateCode: 'AZ', region: 'West', founded: 1882, website: 'https://azmasons.org', headquartersCity: 'Phoenix', lodgeCount: '50+' },
    { code: 'usglca', name: 'Grand Lodge of California F&AM', shortName: 'GL California', state: 'California', stateCode: 'CA', region: 'West', founded: 1850, website: 'https://freemason.org', headquartersCity: 'San Francisco', lodgeCount: '300+' },
    { code: 'usglco', name: 'Grand Lodge of Colorado AF&AM', shortName: 'GL Colorado', state: 'Colorado', stateCode: 'CO', region: 'West', founded: 1861, website: 'https://coloradofreemasons.org', headquartersCity: 'Colorado Springs', lodgeCount: '120+' },
    { code: 'usglhi', name: 'Grand Lodge of Hawaii F&AM', shortName: 'GL Hawaii', state: 'Hawaii', stateCode: 'HI', region: 'West', founded: 1989, website: 'https://hawaiifreemason.org', headquartersCity: 'Honolulu', lodgeCount: '10+' },
    { code: 'usglid', name: 'Grand Lodge of Idaho AF&AM', shortName: 'GL Idaho', state: 'Idaho', stateCode: 'ID', region: 'West', founded: 1867, website: 'https://idahomasons.org', headquartersCity: 'Boise', lodgeCount: '50+' },
    { code: 'usglmt', name: 'Grand Lodge of Montana AF&AM', shortName: 'GL Montana', state: 'Montana', stateCode: 'MT', region: 'West', founded: 1866, website: 'https://grandlodgemontana.org', headquartersCity: 'Helena', lodgeCount: '90+' },
    { code: 'usglnv', name: 'Grand Lodge of Nevada F&AM', shortName: 'GL Nevada', state: 'Nevada', stateCode: 'NV', region: 'West', founded: 1865, website: 'https://nvmasons.org', headquartersCity: 'Reno', lodgeCount: '35+' },
    { code: 'usglnm', name: 'Grand Lodge of New Mexico AF&AM', shortName: 'GL New Mexico', state: 'New Mexico', stateCode: 'NM', region: 'West', founded: 1877, website: 'https://nmmasons.org', headquartersCity: 'Albuquerque', lodgeCount: '50+' },
    { code: 'usglor', name: 'Grand Lodge of Oregon AF&AM', shortName: 'GL Oregon', state: 'Oregon', stateCode: 'OR', region: 'West', founded: 1851, website: 'https://oregonfreemasonry.com', headquartersCity: 'Forest Grove', lodgeCount: '90+' },
    { code: 'usglut', name: 'Grand Lodge of Utah F&AM', shortName: 'GL Utah', state: 'Utah', stateCode: 'UT', region: 'West', founded: 1872, website: 'https://utahgrandlodge.org', headquartersCity: 'Salt Lake City', lodgeCount: '20+' },
    { code: 'usglwa', name: 'Grand Lodge of Washington F&AM', shortName: 'GL Washington', state: 'Washington', stateCode: 'WA', region: 'West', founded: 1858, website: 'https://freemason-wa.org', headquartersCity: 'Des Moines, WA', lodgeCount: '180+' },
    { code: 'usglwy', name: 'Grand Lodge of Wyoming AF&AM', shortName: 'GL Wyoming', state: 'Wyoming', stateCode: 'WY', region: 'West', founded: 1874, website: 'https://wyomingmasons.com', headquartersCity: 'Casper', lodgeCount: '35+' },
];

const TOTAL = stateGrandLodges.length;

const regions = ['All', 'Northeast', 'South', 'Midwest', 'West'];

function regionCount(region) {
    if (region === 'All') return TOTAL;
    return stateGrandLodges.filter(gl => gl.region === region).length;
}

const filteredLodges = computed(() => {
    let list = stateGrandLodges;

    if (activeRegion.value !== 'All') {
        list = list.filter(gl => gl.region === activeRegion.value);
    }

    const q = searchQuery.value.toLowerCase().trim();
    if (!q) return list;

    return list.filter(gl =>
        gl.name.toLowerCase().includes(q) ||
        gl.shortName.toLowerCase().includes(q) ||
        gl.state.toLowerCase().includes(q) ||
        gl.stateCode.toLowerCase().includes(q) ||
        gl.headquartersCity.toLowerCase().includes(q) ||
        gl.code.toLowerCase().includes(q)
    );
});

const seededCount = computed(() =>
    stateGrandLodges.filter(gl => dbEntry(gl.code)).length
);

const totalClubs = computed(() =>
    props.usGrandLodges.reduce((sum, gl) => sum + (gl.clubs_count || 0), 0)
);

const oldestFounded = computed(() =>
    Math.min(...stateGrandLodges.map(gl => gl.founded))
);

function dbEntry(code) {
    return props.usGrandLodges.find(gl => gl.code === code);
}
</script>

<template>
    <SuperAdminLayout title="US Grand Lodges">
        <Head title="US Grand Lodges - Superadmin" />

        <div class="space-y-6">

            <PageHeader
                title="United States Grand Lodges"
                description="The United States has no national Grand Lodge. Each state and the District of Columbia operates a fully independent, sovereign Grand Lodge."
                :breadcrumbs="[{ label: 'Dashboard', href: route('superadmin.dashboard') }, { label: 'US Grand Lodges' }]"
            />

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <StatCard label="Jurisdictions" :value="TOTAL" caption="50 states + D.C." />
                <StatCard label="In database" :value="seededCount" :caption="`of ${TOTAL} seeded`" />
                <StatCard label="Total registered lodges" :value="totalClubs || '—'" caption="Across all US jurisdictions" />
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm dark:bg-slate-900 dark:border-slate-800">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Oldest jurisdiction</p>
                    <p class="mt-1 text-sm font-semibold leading-tight text-slate-900 dark:text-white">Pennsylvania · {{ oldestFounded }}</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Earliest in North America</p>
                </div>
            </div>

            <Card padding="sm" class="space-y-4">
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
                        Showing <span class="font-semibold text-slate-900 dark:text-white">{{ filteredLodges.length }}</span> of {{ TOTAL }} Grand Lodges
                    </div>
                </div>

                <div class="max-w-full overflow-x-auto">
                    <div role="group" aria-label="Region" class="flex w-fit items-center gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
                        <button
                            v-for="region in regions"
                            :key="region"
                            type="button"
                            @click="activeRegion = region"
                            :aria-pressed="activeRegion === region"
                            :class="[
                                'inline-flex items-center gap-1 rounded-xl px-4 py-2 text-xs font-semibold whitespace-nowrap transition-colors',
                                activeRegion === region
                                    ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
                                    : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white'
                            ]"
                        >
                            {{ region }}
                            <span class="opacity-60">({{ regionCount(region) }})</span>
                        </button>
                    </div>
                </div>
            </Card>

            <Card padding="none" class="overflow-hidden">
                <div class="p-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 dark:border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider dark:text-slate-400">
                        Showing {{ filteredLodges.length }} of {{ TOTAL }} US Grand Lodges
                    </h2>
                    <Badge v-if="seededCount < TOTAL" variant="warning">
                        <UiIcon name="warning" />
                        {{ TOTAL - seededCount }} not yet in database
                    </Badge>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="py-3 px-4 font-medium">Grand Lodge</th>
                                <th scope="col" class="py-3 px-3 font-medium">State</th>
                                <th scope="col" class="py-3 px-3 font-medium">Region</th>
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
                                    <div class="text-[11px] text-slate-500 mt-0.5 dark:text-slate-400">{{ gl.shortName }}</div>
                                </td>

                                <!-- State -->
                                <td class="py-3.5 px-3">
                                    <Badge>{{ gl.stateCode }}</Badge>
                                    <div class="text-[10px] text-slate-500 mt-0.5 dark:text-slate-400">{{ gl.state }}</div>
                                </td>

                                <!-- Region -->
                                <td class="py-3.5 px-3 text-slate-500 dark:text-slate-400">
                                    {{ gl.region }}
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
                                    <a v-if="gl.website" :href="gl.website" target="_blank" rel="noopener" class="text-blue-600 hover:underline flex items-center gap-1 whitespace-nowrap dark:text-blue-400">
                                        <span>Website</span>
                                        <UiIcon name="external-link" />
                                    </a>
                                    <span v-else class="text-slate-400 text-[11px] dark:text-slate-500">None known</span>
                                </td>
                            </tr>

                            <tr v-if="filteredLodges.length === 0">
                                <td colspan="9" class="py-12 px-4 text-center text-slate-500 dark:text-slate-400">
                                    <span class="mb-2 flex justify-center text-slate-400 dark:text-slate-500"><UiIcon name="search" :size="20" /></span>
                                    <p class="font-medium text-sm text-slate-700 dark:text-slate-300">No Grand Lodges found matching your search</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Alert variant="info" title="Note">
                These {{ TOTAL }} Grand Lodges are all fully sovereign — equivalent in standing to UGLE itself. None is subordinate to another, and there is no national American Grand Lodge.
                Lodges hold their warrant directly from their state Grand Lodge. Mutual recognition is coordinated through the
                <span class="font-semibold">Conference of Grand Masters of Masons in North America</span> (advisory only).
                Prince Hall Affiliation Grand Lodges form a separate parallel system and are not listed here.
            </Alert>

        </div>
    </SuperAdminLayout>
</template>
