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
        case 'district': return { label: 'Active District', cls: 'bg-indigo-950 text-indigo-300 border-indigo-800/60' };
        case 'group':    return { label: 'Group of Lodges', cls: 'bg-emerald-950 text-emerald-300 border-emerald-800/60' };
        case 'dormant':  return { label: 'Dormant', cls: 'bg-rose-950 text-rose-300 border-rose-800/60' };
        default:         return { label: type, cls: 'bg-slate-950 text-slate-300 border-slate-700' };
    }
}

const badge = typeBadge(props.district.type);
</script>

<template>
    <SuperAdminLayout :title="district.name">
        <Head :title="`${district.name} - Districts &amp; Groups`" />

        <div class="space-y-6 max-w-5xl mx-auto">

            <!-- Header & Breadcrumbs -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
                        <Link :href="route('superadmin.dashboard')" class="hover:text-white transition">Dashboard</Link>
                        <span>/</span>
                        <Link :href="route('superadmin.districts.index')" class="hover:text-white transition">Districts &amp; Groups</Link>
                        <span>/</span>
                        <span class="text-indigo-400 font-bold">{{ district.name }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-white tracking-tight">{{ district.name }}</h1>
                    <div class="flex items-center gap-3 mt-1.5">
                        <span :class="['px-2.5 py-0.5 rounded text-[11px] font-bold border', badge.cls]">{{ badge.label }}</span>
                        <span class="font-mono text-xs text-indigo-400">{{ district.code }}</span>
                        <span v-if="district.grand_lodge" class="text-xs text-slate-400">
                            via <span class="text-purple-300 font-semibold">{{ district.grand_lodge.short_name || district.grand_lodge.name }}</span>
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <Link
                        :href="route('superadmin.districts.edit', district.id)"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl border border-indigo-500 transition"
                    >
                        ✏️ Edit District
                    </Link>
                    <button
                        @click="confirmDelete"
                        :disabled="deleteForm.processing"
                        class="px-4 py-2 bg-rose-950/60 hover:bg-rose-900 text-rose-300 text-xs font-bold rounded-xl border border-rose-800/50 transition"
                    >
                        🗑️ Delete
                    </button>
                </div>
            </div>

            <!-- Dormant Warning Banner -->
            <div v-if="district.type === 'dormant'" class="bg-rose-950/40 border border-rose-800/60 rounded-xl p-4 flex items-start gap-3">
                <span class="text-xl">⚠️</span>
                <div>
                    <p class="text-sm font-bold text-rose-300">Dormant District</p>
                    <p class="text-xs text-rose-400 mt-0.5">{{ district.description || 'Freemasonry is banned or suspended in this jurisdiction.' }}</p>
                </div>
            </div>

            <!-- Info Cards Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

                <!-- Identification -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                    <h2 class="text-xs font-extrabold text-purple-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
                        <span>🌍</span> <span>Identification</span>
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-slate-400 font-medium">Official Name</dt>
                            <dd class="text-white font-bold text-right max-w-xs">{{ district.name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400 font-medium">Type</dt>
                            <dd><span :class="['px-2 py-0.5 rounded text-[11px] font-bold border', badge.cls]">{{ badge.label }}</span></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400 font-medium">System Code</dt>
                            <dd class="font-mono text-indigo-400">{{ district.code }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400 font-medium">Country</dt>
                            <dd class="text-slate-200 font-semibold">{{ district.country || '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400 font-medium">Region</dt>
                            <dd class="text-slate-200">{{ district.region || '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400 font-medium">Governing Grand Lodge</dt>
                            <dd class="text-purple-300 font-semibold">{{ district.grand_lodge?.name || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Executive Leadership -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                    <h2 class="text-xs font-extrabold text-amber-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
                        <span>👑</span> <span>District Executive Rulers</span>
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-slate-400 font-medium">District Grand Master</dt>
                            <dd class="text-white font-semibold">{{ district.district_grand_master || '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400 font-medium">District Grand Secretary</dt>
                            <dd class="text-white font-semibold">{{ district.district_grand_secretary || '—' }}</dd>
                        </div>
                    </dl>
                    <p v-if="!district.district_grand_master && !district.district_grand_secretary" class="text-xs text-slate-500 italic mt-2">
                        No leadership details recorded yet. <Link :href="route('superadmin.districts.edit', district.id)" class="text-indigo-400 hover:underline">Add them →</Link>
                    </p>
                </div>

                <!-- Office Address & Contact -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                    <h2 class="text-xs font-extrabold text-indigo-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
                        <span>📍</span> <span>Office Address &amp; Contact</span>
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div v-if="district.address_line_1" class="space-y-0.5">
                            <dt class="text-slate-400 font-medium">Address</dt>
                            <dd class="text-slate-200">
                                <div>{{ district.address_line_1 }}</div>
                                <div v-if="district.address_line_2">{{ district.address_line_2 }}</div>
                                <div v-if="district.town || district.county">{{ [district.town, district.county].filter(Boolean).join(', ') }}</div>
                                <div v-if="district.postcode" class="font-mono text-indigo-300">{{ district.postcode }}</div>
                            </dd>
                        </div>
                        <div v-else class="text-slate-500 italic">No address recorded.</div>
                        <div v-if="district.telephone" class="flex justify-between">
                            <dt class="text-slate-400 font-medium">Telephone</dt>
                            <dd class="text-slate-200 font-mono">{{ district.telephone }}</dd>
                        </div>
                        <div v-if="district.email" class="flex justify-between">
                            <dt class="text-slate-400 font-medium">Email</dt>
                            <dd><a :href="`mailto:${district.email}`" class="text-indigo-400 hover:underline">{{ district.email }}</a></dd>
                        </div>
                        <div v-if="district.website_url" class="flex justify-between">
                            <dt class="text-slate-400 font-medium">Website</dt>
                            <dd>
                                <a :href="district.website_url" target="_blank" class="text-indigo-400 hover:underline flex items-center gap-1">
                                    <span>Visit ↗</span>
                                </a>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Social Media & Online Presence -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                    <h2 class="text-xs font-extrabold text-sky-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2">
                        <span>🌐</span> <span>Social Media &amp; Online</span>
                    </h2>
                    <dl class="space-y-3 text-xs">
                        <div v-if="district.twitter_url" class="flex justify-between">
                            <dt class="text-slate-400 font-medium">Twitter / X</dt>
                            <dd><a :href="district.twitter_url" target="_blank" class="text-indigo-400 hover:underline">View Profile ↗</a></dd>
                        </div>
                        <div v-if="district.facebook_url" class="flex justify-between">
                            <dt class="text-slate-400 font-medium">Facebook</dt>
                            <dd><a :href="district.facebook_url" target="_blank" class="text-indigo-400 hover:underline">View Page ↗</a></dd>
                        </div>
                        <p v-if="!district.twitter_url && !district.facebook_url" class="text-xs text-slate-500 italic">
                            No social media profiles recorded. <Link :href="route('superadmin.districts.edit', district.id)" class="text-indigo-400 hover:underline">Add them →</Link>
                        </p>
                    </dl>
                </div>
            </div>

            <!-- Description / Notes -->
            <div v-if="district.description" class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
                <h2 class="text-xs font-extrabold text-emerald-400 uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center gap-2 mb-4">
                    <span>📖</span> <span>Overview &amp; Notes</span>
                </h2>
                <p class="text-sm text-slate-300 leading-relaxed">{{ district.description }}</p>
            </div>

            <!-- Bottom Actions -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-800">
                <Link
                    :href="route('superadmin.districts.index')"
                    class="text-xs text-slate-400 hover:text-white transition flex items-center gap-1"
                >
                    ← Back to Districts &amp; Groups
                </Link>
                <Link
                    :href="route('superadmin.districts.edit', district.id)"
                    class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition"
                >
                    ✏️ Edit This Entry
                </Link>
            </div>
        </div>
    </SuperAdminLayout>
</template>
