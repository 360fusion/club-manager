<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    district: { type: Object, required: true },
    grandLodges: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.district.name || '',
    code: props.district.code || '',
    type: props.district.type || 'district',
    grand_lodge_id: props.district.grand_lodge_id || null,
    country: props.district.country || '',
    region: props.district.region || '',
    website_url: props.district.website_url || '',
    district_grand_master: props.district.district_grand_master || '',
    district_grand_secretary: props.district.district_grand_secretary || '',
    address_line_1: props.district.address_line_1 || '',
    address_line_2: props.district.address_line_2 || '',
    town: props.district.town || '',
    county: props.district.county || '',
    postcode: props.district.postcode || '',
    telephone: props.district.telephone || '',
    email: props.district.email || '',
    twitter_url: props.district.twitter_url || '',
    facebook_url: props.district.facebook_url || '',
    description: props.district.description || '',
});

function submitUpdate() {
    form.put(route('superadmin.districts.update', props.district.id));
}
</script>

<template>
    <SuperAdminLayout :title="`Edit ${district.name}`">
        <Head :title="`Edit - ${district.name}`" />

        <div class="space-y-8 max-w-5xl mx-auto">

            <!-- Header & Breadcrumbs -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1 dark:text-slate-400">
                        <Link :href="route('superadmin.dashboard')" class="hover:text-slate-900 transition dark:hover:text-white">Dashboard</Link>
                        <span>/</span>
                        <Link :href="route('superadmin.districts.index')" class="hover:text-slate-900 transition dark:hover:text-white">Districts &amp; Groups</Link>
                        <span>/</span>
                        <Link :href="route('superadmin.districts.show', district.id)" class="hover:text-slate-900 transition dark:hover:text-white">{{ district.name }}</Link>
                        <span>/</span>
                        <span class="text-blue-600 font-bold dark:text-blue-400">Edit</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Edit {{ district.name }}</h1>
                    <p class="text-xs text-slate-500 mt-0.5 dark:text-slate-400">Update official details, executive leaders, contact information, and online presence.</p>
                </div>

                <Link
                    :href="route('superadmin.districts.show', district.id)"
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-xl border border-slate-300 transition flex-shrink-0 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700"
                >
                    ← Cancel &amp; Back
                </Link>
            </div>

            <!-- Edit Form -->
            <form @submit.prevent="submitUpdate" class="space-y-8">

                <!-- Section 1: Classification -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                    <h3 class="text-xs font-extrabold text-blue-600 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2 dark:text-blue-400 dark:border-slate-800">
                        <span>🌍</span>
                        <span>Identification &amp; Classification</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Official Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-bold dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                                required
                            />
                            <p v-if="form.errors.name" class="text-rose-700 mt-1 dark:text-rose-400">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Type</label>
                            <select
                                v-model="form.type"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 cursor-pointer font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                                required
                            >
                                <option value="district">District Grand Lodge</option>
                                <option value="group">Group of Lodges</option>
                                <option value="dormant">Dormant</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Governing Grand Lodge</label>
                            <select
                                v-model="form.grand_lodge_id"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 cursor-pointer font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            >
                                <option :value="null">— None / Independent —</option>
                                <option v-for="gl in grandLodges" :key="gl.id" :value="gl.id">
                                    {{ gl.name }} ({{ gl.short_name || gl.country }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">System Code Key</label>
                            <input
                                v-model="form.code"
                                type="text"
                                placeholder="e.g. singapore"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                                required
                            />
                            <p v-if="form.errors.code" class="text-rose-700 mt-1 dark:text-rose-400">{{ form.errors.code }}</p>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Country</label>
                            <input
                                v-model="form.country"
                                type="text"
                                placeholder="e.g. Singapore"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Geographic Region</label>
                            <input
                                v-model="form.region"
                                type="text"
                                placeholder="e.g. South East Asia"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 2: Executive Leadership -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                    <h3 class="text-xs font-extrabold text-amber-700 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2 dark:text-amber-400 dark:border-slate-800">
                        <span>👑</span>
                        <span>District Executive Rulers</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">District Grand Master</label>
                            <input
                                v-model="form.district_grand_master"
                                type="text"
                                placeholder="e.g. W. Bro. John Smith"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">District Grand Secretary</label>
                            <input
                                v-model="form.district_grand_secretary"
                                type="text"
                                placeholder="e.g. W. Bro. James Brown"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 3: Office Address & Contact -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                    <h3 class="text-xs font-extrabold text-blue-600 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2 dark:text-blue-400 dark:border-slate-800">
                        <span>📍</span>
                        <span>Office Address &amp; Contact Information</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Address Line 1</label>
                            <input
                                v-model="form.address_line_1"
                                type="text"
                                placeholder="e.g. 10 Marina Boulevard"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Address Line 2 (Optional)</label>
                            <input
                                v-model="form.address_line_2"
                                type="text"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Town / City</label>
                            <input
                                v-model="form.town"
                                type="text"
                                placeholder="e.g. Singapore City"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">County / State / Province</label>
                            <input
                                v-model="form.county"
                                type="text"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Postal Code</label>
                            <input
                                v-model="form.postcode"
                                type="text"
                                placeholder="e.g. 018983"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Telephone Number</label>
                            <input
                                v-model="form.telephone"
                                type="text"
                                placeholder="e.g. +65 6123 4567"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Official Email Address</label>
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="e.g. secretary@freemasons.org.sg"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Official Website URL</label>
                            <input
                                v-model="form.website_url"
                                type="url"
                                placeholder="https://www.freemasons.org.sg"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 4: Social Media -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                    <h3 class="text-xs font-extrabold text-sky-700 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2 dark:text-sky-400 dark:border-slate-800">
                        <span>🌐</span>
                        <span>Social Media Handles &amp; Portals</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Twitter / X URL</label>
                            <input
                                v-model="form.twitter_url"
                                type="url"
                                placeholder="https://x.com/..."
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Facebook Page URL</label>
                            <input
                                v-model="form.facebook_url"
                                type="url"
                                placeholder="https://facebook.com/..."
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 5: Description & Notes -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900 dark:border-slate-800">
                    <h3 class="text-xs font-extrabold text-emerald-700 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2 dark:text-emerald-400 dark:border-slate-800">
                        <span>📖</span>
                        <span>Overview, Background &amp; Notes</span>
                    </h3>

                    <div class="text-xs">
                        <label class="block font-bold text-slate-700 mb-1 dark:text-slate-300">Description &amp; Historical Notes</label>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            placeholder="Enter historical background, status notes (e.g. dormant reason), number of lodges, charity activities, etc."
                            class="w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-blue-500 font-medium leading-relaxed dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                        ></textarea>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <Link
                        :href="route('superadmin.districts.show', district.id)"
                        class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-300 transition dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-extrabold text-xs rounded-xl shadow-lg transition flex items-center gap-2"
                    >
                        <span>{{ form.processing ? 'Saving...' : 'Save Changes' }}</span>
                    </button>
                </div>

            </form>
        </div>
    </SuperAdminLayout>
</template>
