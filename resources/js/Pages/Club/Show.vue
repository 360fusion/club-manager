<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    club: Object,
});

const activeTab = ref('events');
const showRsvpModal = ref(false);
const selectedEvent = ref(null);

// The example shown in the domain box: built from this club's own address name.
const domainExample = computed(() => `www.${props.club.slug || 'yourclub'}.org.uk`);

const domainForm = useForm({
    custom_domain: props.club.custom_domain || '',
});

// RSVP Form with Eventbrite Ticketing & Promo
const rsvpForm = ref({
    attendance_status: 'attending',
    selected_tier_id: null,
    promo_code: '',
    discount: 0,
    attending_dining: false,
    starter: '',
    main: '',
    dessert: '',
    dietary_requirements: '',
});

const openRsvp = (event) => {
    selectedEvent.value = event;
    const defaultTier = event.ticket_tiers && event.ticket_tiers.length > 0 ? event.ticket_tiers[0] : null;
    
    rsvpForm.value = {
        attendance_status: 'attending',
        selected_tier_id: defaultTier ? defaultTier.id : null,
        promo_code: '',
        discount: 0,
        attending_dining: event.has_dining,
        starter: event.menu_items.find(m => m.category === 'starter')?.name || '',
        main: event.menu_items.find(m => m.category === 'main')?.name || '',
        dessert: event.menu_items.find(m => m.category === 'dessert')?.name || '',
        dietary_requirements: '',
    };
    showRsvpModal.value = true;
};

const applyPromo = () => {
    if (!selectedEvent.value || !rsvpForm.value.promo_code) return;
    const match = selectedEvent.value.promos.find(p => p.code.toUpperCase() === rsvpForm.value.promo_code.toUpperCase());
    if (match) {
        rsvpForm.value.discount = parseFloat(match.discount_amount);
    } else {
        alert('Invalid promo code');
        rsvpForm.value.discount = 0;
    }
};

const calculateTotal = (event) => {
    let base = 0;
    if (event.ticket_tiers && event.ticket_tiers.length > 0 && rsvpForm.value.selected_tier_id) {
        const tier = event.ticket_tiers.find(t => t.id === rsvpForm.value.selected_tier_id);
        base = tier ? parseFloat(tier.price) : 0;
    } else {
        base = parseFloat(event.price || 0);
    }

    if (rsvpForm.value.attending_dining && event.has_dining) {
        base += parseFloat(event.dining_price || 0);
    }

    if (rsvpForm.value.discount > 0) {
        base = base * (1 - (rsvpForm.value.discount / 100));
    }

    return base.toFixed(2);
};

const saveDomain = () => {
    domainForm.post(`/${props.club.slug}/domain`, {
        preserveScroll: true,
    });
};

const getIcon = (typeCode) => {
    switch (typeCode) {
        case 'rowing': return '🌊';
        case 'rugby': return '🏉';
        case 'tennis': return '🎾';
        default: return '🏆';
    }
};
</script>

<template>
    <Head :title="`${club.name} - Workspace`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans">
        <!-- Header -->
        <header class="sticky top-0 z-50 backdrop-blur-xl bg-slate-950/80 border-b border-slate-800">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/" class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-white transition-colors">
                        ← Back to Hub
                    </Link>
                    <div class="h-6 w-px bg-slate-800"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center text-xl">
                            {{ getIcon(club.type_code) }}
                        </div>
                        <div>
                            <h1 class="font-extrabold text-lg text-white leading-none">
                                {{ club.name }}
                            </h1>
                            <span class="text-xs text-slate-400">
                                {{ club.type_name }} • <span class="text-emerald-400">PostgreSQL Tenant #{{ club.id }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="`/${club.slug}/admin/events`" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20 hover:bg-blue-500/20">
                        🎫 Events
                    </Link>
                    <Link :href="`/${club.slug}/admin/posts`" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20 hover:bg-blue-500/20">
                        📰 Blog & News
                    </Link>
                    <Link :href="`/${club.slug}/admin/memberships`" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20">
                        💳 Memberships
                    </Link>
                    <Link :href="`/${club.slug}/admin/pages`" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-500 text-white shadow-lg shadow-blue-600/20">
                        🎨 CMS Builder
                    </Link>
                    <Link :href="`/${club.slug}/admin/analytics`" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        📊 Analytics
                    </Link>
                    <a :href="`/site/${club.slug}`" target="_blank" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-900 border border-slate-800 text-slate-300 hover:text-white">
                        🌐 Live Site
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="max-w-7xl mx-auto px-6 py-8">
            <!-- Navigation Tabs -->
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-800 pb-4 mb-8">
                <button 
                    @click="activeTab = 'events'"
                    :class="activeTab === 'events' ? 'bg-blue-600 text-white' : 'bg-slate-900 text-slate-400 hover:text-slate-200'"
                    class="px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2"
                >
                    <span>🎫</span> Events & Eventbrite Ticketing ({{ club.events.length }})
                </button>
                <button 
                    @click="activeTab = 'domain'"
                    :class="activeTab === 'domain' ? 'bg-blue-600 text-white' : 'bg-slate-900 text-slate-400 hover:text-slate-200'"
                    class="px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2"
                >
                    <span>🌐</span> Custom Domain Setup
                </button>
                <button 
                    @click="activeTab = 'members'"
                    :class="activeTab === 'members' ? 'bg-blue-600 text-white' : 'bg-slate-900 text-slate-400 hover:text-slate-200'"
                    class="px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2"
                >
                    <span>👥</span> Member Roster ({{ club.members.length }})
                </button>
            </div>

            <!-- Tab Content: Events with Ticket Tiers & Scannable QR Passes -->
            <div v-if="activeTab === 'events'" class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Eventbrite-Grade Event Ticketing & QR Passes</h2>
                        <p class="text-sm text-slate-400">Multiple ticket tiers, capacity limits, promo codes, 3-course dining, and scannable digital QR passes.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div v-for="event in club.events" :key="event.id" class="rounded-2xl bg-slate-900/70 border border-slate-800 p-6 flex flex-col justify-between space-y-6">
                        <div class="space-y-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span v-if="event.ticket_tiers.length > 0" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                            🎟️ {{ event.ticket_tiers.length }} Ticket Tiers
                                        </span>
                                        <span v-if="event.has_dining" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                            🍽️ 3-Course Dining
                                        </span>
                                    </div>
                                    <h3 class="text-xl font-bold text-white">{{ event.title }}</h3>
                                    <p class="text-xs text-slate-400 mt-1">📍 {{ event.location }} • 🕒 {{ event.starts_at }}</p>
                                </div>
                            </div>

                            <p class="text-sm text-slate-300">{{ event.description }}</p>

                            <!-- Eventbrite Ticket Tiers Preview -->
                            <div v-if="event.ticket_tiers.length > 0" class="p-4 rounded-xl bg-slate-950 border border-blue-500/20 space-y-2">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">🎟️ Available Ticket Tiers & Capacity:</span>
                                <div class="space-y-1.5 text-xs">
                                    <div v-for="tier in event.ticket_tiers" :key="tier.id" class="p-2 rounded bg-slate-900 flex items-center justify-between">
                                        <span class="font-bold text-white">{{ tier.name }}</span>
                                        <div class="flex items-center gap-3">
                                            <span class="text-slate-400">Available: <strong class="text-amber-400 font-mono">{{ tier.available }}</strong></span>
                                            <span class="text-emerald-400 font-bold">{{ $cs }}{{ tier.price }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendees & Digital QR Pass -->
                            <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800 space-y-3">
                                <div class="flex items-center justify-between text-xs font-bold text-slate-400">
                                    <span>Issued Digital E-Tickets ({{ event.attendees.length }})</span>
                                </div>

                                <div v-for="att in event.attendees" :key="att.id" class="p-3 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between text-xs">
                                    <div class="space-y-1">
                                        <div class="font-bold text-white">{{ att.name }}</div>
                                        <div class="font-mono text-[10px] text-blue-400">📱 QR Pass: {{ att.ticket_qr_code }}</div>
                                    </div>
                                    <span class="px-2 py-1 rounded bg-emerald-500/10 text-emerald-400 font-bold uppercase text-[10px]">
                                        PAID {{ $cs }}{{ att.amount_paid }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <button @click="openRsvp(event)" class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-blue-600 to-blue-600 text-white font-bold text-sm shadow-xl shadow-blue-600/20">
                            🎟️ Select Ticket Tier & Generate QR Pass
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Custom Domain Setup -->
            <div v-if="activeTab === 'domain'" class="space-y-6">
                <div class="p-8 rounded-3xl bg-slate-900/70 border border-slate-800 max-w-4xl space-y-6">
                    <div class="flex items-start justify-between border-b border-slate-800 pb-4">
                        <div>
                            <h2 class="text-2xl font-bold text-white">Custom Domain Mapping</h2>
                            <p class="text-xs text-slate-400 mt-1">Connect your club's domain name (e.g. <strong class="text-slate-300">{{ domainExample }}</strong>).</p>
                        </div>
                    </div>

                    <form @submit.prevent="saveDomain" class="space-y-4">
                        <input v-model="domainForm.custom_domain" type="text" :placeholder="domainExample" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white font-mono text-sm" />
                        <button type="submit" class="py-3 px-6 rounded-xl bg-emerald-600 text-white font-bold text-sm">Connect Domain</button>
                    </form>
                </div>
            </div>

            <!-- Tab Content: Members -->
            <div v-if="activeTab === 'members'" class="space-y-6">
                <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-white text-lg">Member Roster & Invite Approvals</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Manage active club members and approve invite-only registration requests.</p>
                    </div>
                    <a :href="`/${club.slug}/members/export`" class="py-2.5 px-4 rounded-xl bg-slate-800 text-white text-xs font-bold">Export Roster CSV</a>
                </div>

                <!-- Pending Member Invites Banner -->
                <div v-if="club.members.filter(m => m.status === 'pending').length > 0" class="p-6 rounded-2xl bg-amber-500/10 border border-amber-500/30 space-y-4">
                    <div class="flex items-center gap-2 text-amber-400 font-bold text-sm uppercase tracking-wider">
                        <span>⏳ Pending Registration Approvals ({{ club.members.filter(m => m.status === 'pending').length }})</span>
                    </div>

                    <div class="space-y-2">
                        <div v-for="m in club.members.filter(m => m.status === 'pending')" :key="m.id" class="p-4 rounded-xl bg-slate-950 border border-amber-500/20 flex items-center justify-between">
                            <div>
                                <div class="font-bold text-white text-sm">{{ m.name }}</div>
                                <div class="text-xs text-slate-400">{{ m.email }} • #{{ m.member_number }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link :href="route('clubs.members.approve', { slug: club.slug, userId: m.id })" method="post" as="button" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md">
                                    ✓ Approve Member
                                </Link>
                                <Link :href="route('clubs.members.reject', { slug: club.slug, userId: m.id })" method="post" as="button" class="px-3 py-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 font-semibold text-xs rounded-xl border border-rose-500/20">
                                    ✕ Reject
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Members Roster -->
                <div class="rounded-2xl bg-slate-900/70 border border-slate-800 overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-950 border-b border-slate-800 text-slate-400 font-bold uppercase tracking-wider">
                            <tr>
                                <th class="p-4">Member Name</th>
                                <th class="p-4">Email</th>
                                <th class="p-4">Member #</th>
                                <th class="p-4">Role</th>
                                <th class="p-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <tr v-for="m in club.members.filter(m => m.status !== 'pending')" :key="m.id" class="hover:bg-slate-900/50">
                                <td class="p-4 font-bold text-white">{{ m.name }}</td>
                                <td class="p-4 text-slate-400">{{ m.email }}</td>
                                <td class="p-4 font-mono text-slate-400">{{ m.member_number }}</td>
                                <td class="p-4">
                                    <span class="px-2.5 py-0.5 rounded-full font-bold uppercase text-[10px] bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                        {{ m.role }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="px-2.5 py-0.5 rounded font-bold uppercase text-[10px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Active
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- Ticket Tier Selection & QR Pass RSVP Modal -->
        <div v-if="showRsvpModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-xl w-full p-6 space-y-6 shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-800 pb-4">
                    <div>
                        <h3 class="text-xl font-bold text-white">Ticket Selection & QR Pass Generator</h3>
                        <p class="text-xs text-slate-400">{{ selectedEvent?.title }}</p>
                    </div>
                    <button @click="showRsvpModal = false" class="text-slate-400 hover:text-white font-bold">✕</button>
                </div>

                <div class="space-y-4 text-sm">
                    <!-- Ticket Tier Radio Selection -->
                    <div v-if="selectedEvent?.ticket_tiers.length > 0">
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Select Ticket Tier</label>
                        <div class="space-y-2">
                            <div 
                                v-for="tier in selectedEvent.ticket_tiers" 
                                :key="tier.id"
                                @click="rsvpForm.selected_tier_id = tier.id"
                                :class="rsvpForm.selected_tier_id === tier.id ? 'bg-blue-600/20 border-blue-500 text-white' : 'bg-slate-950 border-slate-800 text-slate-400'"
                                class="p-3 rounded-xl border flex items-center justify-between cursor-pointer transition-all"
                            >
                                <div>
                                    <span class="font-bold">{{ tier.name }}</span>
                                    <span class="text-xs text-slate-400 block">Available: {{ tier.available }}</span>
                                </div>
                                <span class="font-bold text-emerald-400 text-base">{{ $cs }}{{ tier.price }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Promo Code Box -->
                    <div v-if="selectedEvent?.promos.length > 0">
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Promo Code (e.g. EARLYBIRD10)</label>
                        <div class="flex gap-2">
                            <input v-model="rsvpForm.promo_code" type="text" placeholder="EARLYBIRD10" class="flex-1 bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white font-mono uppercase" />
                            <button @click="applyPromo" class="py-2.5 px-4 rounded-xl bg-slate-800 text-white font-bold text-xs">Apply</button>
                        </div>
                    </div>

                    <!-- Dietary Requirements -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Dietary Requirements</label>
                        <input v-model="rsvpForm.dietary_requirements" type="text" placeholder="e.g. Vegetarian, Gluten-Free" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white" />
                    </div>

                    <!-- Total & Scannable QR Pass Preview -->
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 space-y-3">
                        <div class="flex items-center justify-between font-bold text-emerald-300">
                            <span>Total Due for Ticket:</span>
                            <span class="text-2xl">{{ $cs }}{{ calculateTotal(selectedEvent) }}</span>
                        </div>
                        <div class="text-center p-3 rounded-lg bg-slate-950 font-mono text-xs text-blue-400">
                            📱 Scannable QR Pass: <strong>TICKET-OUBC-{{ Math.floor(1000 + Math.random() * 9000) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button @click="showRsvpModal = false" class="w-full py-3 rounded-xl bg-slate-800 text-slate-300 font-bold text-sm">Cancel</button>
                    <button @click="showRsvpModal = false" class="w-full py-3 rounded-xl bg-gradient-to-r from-blue-600 to-blue-600 text-white font-bold text-sm">Checkout & Issue QR Pass</button>
                </div>
            </div>
        </div>
    </div>
</template>
