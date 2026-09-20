<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    district: { type: Object, required: true },
    grandLodges: { type: Array, default: () => [] },
});

const deleteForm = useForm({});

function confirmDelete() {
    if (confirm(`Are you sure you want to permanently delete '${props.district.name}'? This action cannot be undone.`)) {
        deleteForm.delete(route('superadmin.districts.destroy', props.district.id));
    }
}

function typeBadge(type) {
    switch (type) {
        case 'district': return { label: 'Active District', cls: 'bg-blue-50 text-blue-700 border-blue-200' };
        case 'group':    return { label: 'Group of Lodges', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
        case 'dormant':  return { label: 'Dormant', cls: 'bg-rose-50 text-rose-700 border-rose-200' };
        default:         return { label: type, cls: 'bg-slate-100 text-slate-700 border-slate-300' };
    }
}

const badge = typeBadge(props.district.type);
</script>

<template>
    <SuperAdminLayout :title="district.name">
        <Head :title="`${district.name} - Districts &amp; Groups`" />

        <div class="space-y-6 max-w-5xl mx-auto">

            <!-- Header & Breadcrumbs -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1">
                        <Link :href="route('superadmin.dashboard')" class="hover:text-slate-900 transition">Dashboard</Link>
                        <span>/</span>
                        <Link :href="route('superadmin.districts.index')" class="hover:text-slate-900 transition">Districts &amp; Groups</Link>
                        <span>/</span>
                        <span class="text-blue-600 font-bold">{{ district.name }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ district.name }}</h1>
                    <div class="flex items-center gap-3 mt-1.5">
                        <span :class="['px-2.5 py-0.5 rounded text-[11px] font-bold border', badge.cls]">{{ badge.label }}</span>
                        <span class="font-mono text-xs text-blue-600">{{ district.code }}</span>
                        <span v-if="district.grand_lodge" class="text-xs text-slate-500">
                            via <span class="text-blue-700 font-semibold">{{ district.grand_lodge.short_name || district.grand_lodge.name }}</span>
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <Link
                        :href="route('superadmin.districts.edit', district.id)"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl border border-blue-500 transition"
                    >
                        ✏️ Edit District
                    </Link>
                    <button
                        @click="confirmDelete"
                        :disabled="deleteForm.processing"
                        class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-xl border border-rose-200 transition"
                    >
                        🗑️ Delete
                    </button>
                </div>
            </div>

            <!-- Dormant Warning Banner -->
            <div v-if="district.type === 'dormant'" class="bg-rose-50 border border-rose-200 rounded-xl p-4 flex items-start gap-3">
                <span class="text-xl">⚠️</span>
                <div>
                    <p class="text-sm font-bold text-rose-700">Dormant District</p>
                    <p class="text-xs text-rose-700 mt-0.5">{{ district.description || 'Freemasonry is banned or suspended in this jurisdiction.' }}</p>
                </div>
            </div>

            <!-- Info Cards Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

                <!-- Identification -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4">
                    <h2 class="text-xs font-extrabold text-blue-600 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2">
                        <span>🌍</span> <span>Identification</span>
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Official Name</dt>
                            <dd class="text-slate-900 font-bold text-right max-w-xs">{{ district.name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Type</dt>
                            <dd><span :class="['px-2 py-0.5 rounded text-[11px] font-bold border', badge.cls]">{{ badge.label }}</span></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium">System Code</dt>
                            <dd class="font-mono text-blue-600">{{ district.code }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Country</dt>
                            <dd class="text-slate-800 font-semibold">{{ district.country || '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Region</dt>
                            <dd class="text-slate-800">{{ district.region || '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Governing Grand Lodge</dt>
                            <dd class="text-blue-700 font-semibold">{{ district.grand_lodge?.name || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Executive Leadership -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4">
                    <h2 class="text-xs font-extrabold text-amber-700 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2">
                        <span>👑</span> <span>District Executive Rulers</span>
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium">District Grand Master</dt>
                            <dd class="text-slate-900 font-semibold">{{ district.district_grand_master || '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium">District Grand Secretary</dt>
                            <dd class="text-slate-900 font-semibold">{{ district.district_grand_secretary || '—' }}</dd>
                        </div>
                    </dl>
                    <p v-if="!district.district_grand_master && !district.district_grand_secretary" class="text-xs text-slate-500 italic mt-2">
                        No leadership details recorded yet. <Link :href="route('superadmin.districts.edit', district.id)" class="text-blue-600 hover:underline">Add them →</Link>
                    </p>
                </div>

                <!-- Office Address & Contact -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4">
                    <h2 class="text-xs font-extrabold text-blue-600 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2">
                        <span>📍</span> <span>Office Address &amp; Contact</span>
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div v-if="district.address_line_1" class="space-y-0.5">
                            <dt class="text-slate-500 font-medium">Address</dt>
                            <dd class="text-slate-800">
                                <div>{{ district.address_line_1 }}</div>
                                <div v-if="district.address_line_2">{{ district.address_line_2 }}</div>
                                <div v-if="district.town || district.county">{{ [district.town, district.county].filter(Boolean).join(', ') }}</div>
                                <div v-if="district.postcode" class="font-mono text-blue-700">{{ district.postcode }}</div>
                            </dd>
                        </div>
                        <div v-else class="text-slate-500 italic">No address recorded.</div>
                        <div v-if="district.telephone" class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Telephone</dt>
                            <dd class="text-slate-800 font-mono">{{ district.telephone }}</dd>
                        </div>
                        <div v-if="district.email" class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Email</dt>
                            <dd><a :href="`mailto:${district.email}`" class="text-blue-600 hover:underline">{{ district.email }}</a></dd>
                        </div>
                        <div v-if="district.website_url" class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Website</dt>
                            <dd>
                                <a :href="district.website_url" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1">
                                    <span>Visit ↗</span>
                                </a>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Social Media & Online Presence -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4">
                    <h2 class="text-xs font-extrabold text-sky-700 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2">
                        <span>🌐</span> <span>Social Media &amp; Online</span>
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div v-if="district.twitter_url" class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Twitter / X</dt>
                            <dd><a :href="district.twitter_url" target="_blank" class="text-blue-600 hover:underline">View Profile ↗</a></dd>
                        </div>
                        <div v-if="district.facebook_url" class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Facebook</dt>
                            <dd><a :href="district.facebook_url" target="_blank" class="text-blue-600 hover:underline">View Page ↗</a></dd>
                        </div>
                        <p v-if="!district.twitter_url && !district.facebook_url" class="text-xs text-slate-500 italic">
                            No social media profiles recorded. <Link :href="route('superadmin.districts.edit', district.id)" class="text-blue-600 hover:underline">Add them →</Link>
                        </p>
                    </dl>
                </div>
            </div>

            <!-- Description / Notes -->
            <div v-if="district.description" class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xl">
                <h2 class="text-xs font-extrabold text-emerald-700 uppercase tracking-wider border-b border-slate-200 pb-3 flex items-center gap-2 mb-4">
                    <span>📖</span> <span>Overview &amp; Notes</span>
                </h2>
                <p class="text-sm text-slate-700 leading-relaxed">{{ district.description }}</p>
            </div>

            <!-- Bottom Actions -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-200">
                <Link
                    :href="route('superadmin.districts.index')"
                    class="text-xs text-slate-500 hover:text-slate-900 transition flex items-center gap-1"
                >
                    ← Back to Districts &amp; Groups
                </Link>
                <Link
                    :href="route('superadmin.districts.edit', district.id)"
                    class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition"
                >
                    ✏️ Edit This Entry
                </Link>
            </div>
        </div>
    </SuperAdminLayout>
</template>
