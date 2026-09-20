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
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
                        <Link :href="route('superadmin.dashboard')" class="hover:text-white transition">Dashboard</Link>
                        <span>/</span>
                        <Link :href="route('superadmin.districts.index')" class="hover:text-white transition">Districts &amp; Groups</Link>
                        <span>/</span>
                        <Link :href="route('superadmin.districts.show', district.id)" class="hover:text-white transition">{{ district.name }}</Link>
                        <span>/</span>
                        <span class="text-indigo-400 font-bold">Edit</span>
                    </div>
                    <h1 class="text-2xl font-bold text-white tracking-tight">Edit {{ district.name }}</h1>
                    <p class="text-xs text-slate-400 mt-0.5">Update official details, executive leaders, contact information, and online presence.</p>
                </div>

                <Link
                    :href="route('superadmin.districts.show', district.id)"
                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700 transition flex-shrink-0"
                >
                    ← Cancel &amp; Back
                </Link>
            </div>

            <!-- Edit Form -->
            <form @submit.prevent="submitUpdate" class="space-y-8">

                <!-- Section 1: Classification -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                    <h3 class="text-xs font-extrabold text-purple-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
                        <span>🌍</span>
                        <span>Identification &amp; Classification</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Official Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-bold"
                                required
                            />
                            <p v-if="form.errors.name" class="text-rose-400 mt-1">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Type</label>
                            <select
                                v-model="form.type"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 cursor-pointer font-medium"
                                required
                            >
                                <option value="district">District Grand Lodge</option>
                                <option value="group">Group of Lodges</option>
                                <option value="dormant">Dormant</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Governing Grand Lodge</label>
                            <select
                                v-model="form.grand_lodge_id"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 cursor-pointer font-medium"
                            >
                                <option :value="null">— None / Independent —</option>
                                <option v-for="gl in grandLodges" :key="gl.id" :value="gl.id">
                                    {{ gl.name }} ({{ gl.short_name || gl.country }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">System Code Key</label>
                            <input
                                v-model="form.code"
                                type="text"
                                placeholder="e.g. singapore"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-indigo-500"
                                required
                            />
                            <p v-if="form.errors.code" class="text-rose-400 mt-1">{{ form.errors.code }}</p>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Country</label>
                            <input
                                v-model="form.country"
                                type="text"
                                placeholder="e.g. Singapore"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Geographic Region</label>
                            <input
                                v-model="form.region"
                                type="text"
                                placeholder="e.g. South East Asia"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 2: Executive Leadership -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                    <h3 class="text-xs font-extrabold text-amber-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
                        <span>👑</span>
                        <span>District Executive Rulers</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-300 mb-1">District Grand Master</label>
                            <input
                                v-model="form.district_grand_master"
                                type="text"
                                placeholder="e.g. W. Bro. John Smith"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">District Grand Secretary</label>
                            <input
                                v-model="form.district_grand_secretary"
                                type="text"
                                placeholder="e.g. W. Bro. James Brown"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 3: Office Address & Contact -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                    <h3 class="text-xs font-extrabold text-indigo-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
                        <span>📍</span>
                        <span>Office Address &amp; Contact Information</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Address Line 1</label>
                            <input
                                v-model="form.address_line_1"
                                type="text"
                                placeholder="e.g. 10 Marina Boulevard"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Address Line 2 (Optional)</label>
                            <input
                                v-model="form.address_line_2"
                                type="text"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Town / City</label>
                            <input
                                v-model="form.town"
                                type="text"
                                placeholder="e.g. Singapore City"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">County / State / Province</label>
                            <input
                                v-model="form.county"
                                type="text"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Postal Code</label>
                            <input
                                v-model="form.postcode"
                                type="text"
                                placeholder="e.g. 018983"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Telephone Number</label>
                            <input
                                v-model="form.telephone"
                                type="text"
                                placeholder="e.g. +65 6123 4567"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Official Email Address</label>
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="e.g. secretary@freemasons.org.sg"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Official Website URL</label>
                            <input
                                v-model="form.website_url"
                                type="url"
                                placeholder="https://www.freemasons.org.sg"
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 4: Social Media -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                    <h3 class="text-xs font-extrabold text-sky-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
                        <span>🌐</span>
                        <span>Social Media Handles &amp; Portals</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Twitter / X URL</label>
                            <input
                                v-model="form.twitter_url"
                                type="url"
                                placeholder="https://x.com/..."
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Facebook Page URL</label>
                            <input
                                v-model="form.facebook_url"
                                type="url"
                                placeholder="https://facebook.com/..."
                                class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3.5 py-2.5 font-mono focus:ring-2 focus:ring-indigo-500 font-medium"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 5: Description & Notes -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                    <h3 class="text-xs font-extrabold text-emerald-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
                        <span>📖</span>
                        <span>Overview, Background &amp; Notes</span>
                    </h3>

                    <div class="text-xs">
                        <label class="block font-bold text-slate-300 mb-1">Description &amp; Historical Notes</label>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            placeholder="Enter historical background, status notes (e.g. dormant reason), number of lodges, charity activities, etc."
                            class="w-full bg-slate-950 border border-slate-700 text-white rounded-2xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 font-medium leading-relaxed"
                        ></textarea>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <Link
                        :href="route('superadmin.districts.show', district.id)"
                        class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl border border-slate-700 transition"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs rounded-xl shadow-lg transition flex items-center gap-2"
                    >
                        <span>{{ form.processing ? 'Saving...' : 'Save Changes' }}</span>
                    </button>
                </div>

            </form>
        </div>
    </SuperAdminLayout>
</template>
