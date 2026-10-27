<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Modal from '@/Components/Ui/Modal.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
    let base;
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

const tabClass = (isActive) => [
    'inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold whitespace-nowrap transition-colors',
    isActive
        ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
        : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white',
];

// A link styled as the kit's secondary button, for the places Button cannot be: new-tab, download and POST links.
const secondaryLink = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
const primaryLink = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-transparent bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-500';
</script>

<template>
    <Head :title="`${club.name} - Workspace`" />

    <div class="min-h-screen bg-slate-100 text-slate-800 font-sans dark:bg-slate-950 dark:text-slate-200">
        <!-- Header -->
        <header class="sticky top-0 z-40 backdrop-blur-xl bg-white/80 border-b border-slate-200 dark:bg-slate-950/80 dark:border-slate-800">
            <div class="max-w-7xl mx-auto px-6 py-4 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" href="/">
                        <UiIcon name="arrow-left" /> Back to hub
                    </Button>
                    <div class="h-6 w-px bg-slate-200 dark:bg-slate-800"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 text-slate-500 flex items-center justify-center dark:bg-slate-900 dark:border-slate-800 dark:text-slate-400">
                            <UiIcon name="award" :size="20" />
                        </div>
                        <div>
                            <h1 class="font-semibold text-lg text-slate-900 leading-none dark:text-white">
                                {{ club.name }}
                            </h1>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ club.type_name }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="secondary" size="sm" :href="`/${club.slug}/admin/events`">
                        <UiIcon name="calendar" /> Events
                    </Button>
                    <Button variant="secondary" size="sm" :href="`/${club.slug}/admin/posts`">
                        <UiIcon name="document" /> Blog & news
                    </Button>
                    <Button variant="secondary" size="sm" :href="`/${club.slug}/admin/memberships`">
                        <UiIcon name="users" /> Memberships
                    </Button>
                    <Button variant="secondary" size="sm" :href="`/${club.slug}/admin/pages`">
                        <UiIcon name="palette" /> CMS builder
                    </Button>
                    <Button variant="secondary" size="sm" :href="`/${club.slug}/admin/analytics`">
                        <UiIcon name="chart" /> Analytics
                    </Button>
                    <a :href="`/site/${club.slug}`" target="_blank" :class="secondaryLink">
                        <UiIcon name="globe" /> Live site
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="max-w-7xl mx-auto px-6 py-8">
            <!-- Navigation Tabs -->
            <div class="mb-8 max-w-full overflow-x-auto">
                <div class="flex w-fit items-center gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
                    <button type="button" :class="tabClass(activeTab === 'events')" @click="activeTab = 'events'">
                        <UiIcon name="calendar" /> Events and Eventbrite ticketing ({{ club.events.length }})
                    </button>
                    <button type="button" :class="tabClass(activeTab === 'domain')" @click="activeTab = 'domain'">
                        <UiIcon name="globe" /> Custom domain setup
                    </button>
                    <button type="button" :class="tabClass(activeTab === 'members')" @click="activeTab = 'members'">
                        <UiIcon name="users" /> Member roster ({{ club.members.length }})
                    </button>
                </div>
            </div>

            <!-- Tab Content: Events with Ticket Tiers & Scannable QR Passes -->
            <div v-if="activeTab === 'events'" class="space-y-6">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Event ticketing and QR passes</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Multiple ticket types, capacity limits, promo codes, 3-course dining, and scannable digital QR passes.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card v-for="event in club.events" :key="event.id" class="flex flex-col justify-between space-y-6">
                        <div class="space-y-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <Badge v-if="event.ticket_tiers.length > 0" variant="info">{{ event.ticket_tiers.length }} ticket types</Badge>
                                    <Badge v-if="event.has_dining" variant="info">3-course dining</Badge>
                                </div>
                                <h3 class="text-xl font-semibold text-slate-900 dark:text-white">{{ event.title }}</h3>
                                <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="inline-flex items-center gap-1.5"><UiIcon name="building" /> {{ event.location }}</span>
                                    <span class="inline-flex items-center gap-1.5"><UiIcon name="clock" /> {{ event.starts_at }}</span>
                                </p>
                            </div>

                            <p class="text-sm text-slate-600 dark:text-slate-300">{{ event.description }}</p>

                            <!-- Eventbrite Ticket Tiers Preview -->
                            <div v-if="event.ticket_tiers.length > 0" class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2 dark:bg-slate-950 dark:border-slate-800">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider dark:text-slate-400">Ticket types and places</span>
                                <div class="space-y-1.5 text-xs">
                                    <div v-for="tier in event.ticket_tiers" :key="tier.id" class="p-2 rounded-xl bg-white border border-slate-200 flex items-center justify-between dark:bg-slate-900 dark:border-slate-800">
                                        <span class="font-medium text-slate-900 dark:text-white">{{ tier.name }}</span>
                                        <div class="flex items-center gap-3">
                                            <span class="text-slate-500 dark:text-slate-400">Available: <strong class="text-slate-900 font-mono dark:text-white">{{ tier.available }}</strong></span>
                                            <span class="font-semibold text-slate-900 dark:text-white">{{ $cs }}{{ tier.price }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendees & Digital QR Pass -->
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3 dark:bg-slate-950/60 dark:border-slate-800">
                                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Issued e-tickets ({{ event.attendees.length }})
                                </div>

                                <div v-for="att in event.attendees" :key="att.id" class="p-3 rounded-xl bg-white border border-slate-200 flex items-center justify-between text-xs dark:bg-slate-900 dark:border-slate-800">
                                    <div class="space-y-1">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ att.name }}</div>
                                        <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">QR pass: {{ att.ticket_qr_code }}</div>
                                    </div>
                                    <Badge variant="success">Paid {{ $cs }}{{ att.amount_paid }}</Badge>
                                </div>
                            </div>
                        </div>

                        <Button block @click="openRsvp(event)">
                            Select ticket type and generate QR pass
                        </Button>
                    </Card>
                </div>
            </div>

            <!-- Tab Content: Custom Domain Setup -->
            <div v-if="activeTab === 'domain'" class="space-y-6">
                <Card padding="lg" class="max-w-4xl">
                    <template #header>
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Custom domain</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Connect your club's domain name (e.g. <strong class="text-slate-700 dark:text-slate-300">{{ domainExample }}</strong>).</p>
                    </template>

                    <form @submit.prevent="saveDomain" class="space-y-4">
                        <Input v-model="domainForm.custom_domain" label="Domain name" :placeholder="domainExample" class="font-mono" />
                        <Button type="submit">Connect domain</Button>
                    </form>
                </Card>
            </div>

            <!-- Tab Content: Members -->
            <div v-if="activeTab === 'members'" class="space-y-6">
                <Card class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-slate-900 text-lg dark:text-white">Member roster and invite approvals</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Manage active club members and approve invite-only registration requests.</p>
                    </div>
                    <a :href="`/${club.slug}/members/export`" :class="secondaryLink">
                        <UiIcon name="download" /> Export roster CSV
                    </a>
                </Card>

                <!-- Pending Member Invites Banner -->
                <div v-if="club.members.filter(m => m.status === 'pending').length > 0" class="p-6 rounded-2xl bg-amber-50 border border-amber-200 space-y-4 dark:bg-amber-950/30 dark:border-amber-800/40">
                    <div class="flex items-center gap-2 text-amber-700 font-semibold text-xs uppercase tracking-wider dark:text-amber-300">
                        <UiIcon name="clock" /> Waiting for approval ({{ club.members.filter(m => m.status === 'pending').length }})
                    </div>

                    <div class="space-y-2">
                        <div v-for="m in club.members.filter(m => m.status === 'pending')" :key="m.id" class="p-4 rounded-xl bg-white border border-amber-200 flex flex-wrap items-center justify-between gap-3 dark:bg-slate-900 dark:border-amber-800/40">
                            <div>
                                <div class="font-medium text-slate-900 text-sm dark:text-white">{{ m.name }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ m.email }} · #{{ m.member_number }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link :href="route('clubs.members.approve', { slug: club.slug, userId: m.id })" method="post" as="button" :class="primaryLink">
                                    <UiIcon name="check" /> Approve member
                                </Link>
                                <Link :href="route('clubs.members.reject', { slug: club.slug, userId: m.id })" method="post" as="button" :class="secondaryLink">
                                    <UiIcon name="x" /> Reject
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Members Roster -->
                <Card padding="none" class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider dark:bg-slate-950 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="p-4">Member name</th>
                                <th class="p-4">Email</th>
                                <th class="p-4">Member #</th>
                                <th class="p-4">Role</th>
                                <th class="p-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <tr v-for="m in club.members.filter(m => m.status !== 'pending')" :key="m.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="p-4 font-medium text-slate-900 dark:text-white">{{ m.name }}</td>
                                <td class="p-4 text-slate-500 dark:text-slate-400">{{ m.email }}</td>
                                <td class="p-4 font-mono text-slate-500 dark:text-slate-400">{{ m.member_number }}</td>
                                <td class="p-4">
                                    <Badge variant="info" class="capitalize">{{ m.role }}</Badge>
                                </td>
                                <td class="p-4">
                                    <Badge variant="success">Active</Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Card>
            </div>
        </main>

        <!-- Ticket Tier Selection & QR Pass RSVP Modal -->
        <Modal :open="showRsvpModal" title="Choose a ticket" :subtitle="selectedEvent?.title ?? ''" size="lg" @close="showRsvpModal = false">
            <div class="space-y-4 text-sm">
                <!-- Ticket Tier Radio Selection -->
                <div v-if="selectedEvent?.ticket_tiers.length > 0">
                    <span class="block text-sm font-medium text-slate-700 mb-2 dark:text-slate-300">Ticket type</span>
                    <div class="space-y-2">
                        <div
                            v-for="tier in selectedEvent.ticket_tiers"
                            :key="tier.id"
                            @click="rsvpForm.selected_tier_id = tier.id"
                            :class="rsvpForm.selected_tier_id === tier.id ? 'bg-blue-50 border-blue-500 text-slate-900 dark:bg-blue-950/40 dark:text-white' : 'bg-white border-slate-200 text-slate-600 dark:bg-slate-950 dark:border-slate-800 dark:text-slate-400'"
                            class="p-3 rounded-xl border flex items-center justify-between cursor-pointer transition-colors"
                        >
                            <div>
                                <span class="font-medium">{{ tier.name }}</span>
                                <span class="text-xs text-slate-500 block dark:text-slate-400">Available: {{ tier.available }}</span>
                            </div>
                            <span class="font-semibold text-slate-900 text-base dark:text-white">{{ $cs }}{{ tier.price }}</span>
                        </div>
                    </div>
                </div>

                <!-- Promo Code Box -->
                <div v-if="selectedEvent?.promos.length > 0" class="flex items-end gap-2">
                    <Input v-model="rsvpForm.promo_code" label="Promo code" placeholder="EARLYBIRD10" class="flex-1 font-mono uppercase" />
                    <Button variant="secondary" @click="applyPromo">Apply</Button>
                </div>

                <!-- Dietary Requirements -->
                <Input v-model="rsvpForm.dietary_requirements" label="Dietary requirements" placeholder="e.g. vegetarian, gluten-free" />

                <!-- Total & Scannable QR Pass Preview -->
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3 dark:bg-slate-950 dark:border-slate-800">
                    <div class="flex items-center justify-between font-medium text-slate-700 dark:text-slate-300">
                        <span>Total due</span>
                        <span class="text-2xl font-semibold text-slate-900 dark:text-white">{{ $cs }}{{ calculateTotal(selectedEvent) }}</span>
                    </div>
                    <div class="text-center p-3 rounded-xl bg-white border border-slate-200 font-mono text-xs text-slate-600 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300">
                        QR pass: <strong>TICKET-OUBC-{{ Math.floor(1000 + Math.random() * 9000) }}</strong>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showRsvpModal = false">Cancel</Button>
                <Button @click="showRsvpModal = false">Check out and issue QR pass</Button>
            </template>
        </Modal>
    </div>
</template>
