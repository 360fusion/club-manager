<script setup>
import { useClipboard } from '@/Composables/useClipboard';
// Step-by-step help for connecting a custom domain: exactly which DNS record to add at the domain's provider, worked
// out from the domain typed in the box (a "www" or other subdomain gets a CNAME; a bare domain gets a warning and an
// A record). The parent saves the domain and runs the DNS check; this only explains and shows the status.
import { computed } from 'vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
    domain: { type: String, default: '' },
    status: { type: String, default: null },
    verifiedAt: { type: String, default: null },
    instructions: { type: Object, default: () => ({}) },
    // The TXT record that proves the lodge owns the domain ({ host, value }); only sent once the domain is saved.
    ownership: { type: Object, default: null },
    checking: { type: Boolean, default: false },
    // The company that runs the saved domain's DNS ({ name, url, help, steps }), when the server recognised it.
    provider: { type: Object, default: null },
    savedDomain: { type: String, default: '' },
});

defineEmits(['check']);

// Registries where the domain itself has two parts (example.co.uk), so "www.example.co.uk" is a subdomain of it.
const TWO_PART_SUFFIXES = ['co.uk', 'org.uk', 'ac.uk', 'gov.uk', 'me.uk', 'ltd.uk', 'plc.uk', 'sch.uk', 'com.au', 'net.au', 'org.au', 'co.nz', 'org.nz', 'co.za', 'com.br'];

const parts = computed(() => {
    const labels = props.domain.trim().toLowerCase().split('.').filter(Boolean);
    const suffixLength = TWO_PART_SUFFIXES.includes(labels.slice(-2).join('.')) ? 2 : 1;
    const registrable = labels.slice(-(suffixLength + 1)).join('.');
    const sub = labels.slice(0, Math.max(0, labels.length - suffixLength - 1)).join('.');

    return { registrable, sub, bare: sub === '' };
});

const target = computed(() => props.instructions?.target || '');
const ips = computed(() => props.instructions?.ips || []);
const active = computed(() => props.status === 'active');
// The provider was looked up for the saved domain, so it only applies while the box still holds that domain.
const knownProvider = computed(() => props.provider && props.domain.trim().toLowerCase() === props.savedDomain ? props.provider : null);
const unsaved = computed(() => props.domain.trim().toLowerCase() !== props.savedDomain);
// Shown until the domain is Active, and only for the saved domain (the value belongs to it).
const showOwnership = computed(() => !!props.ownership && !active.value && !unsaved.value);
// Providers differ on whether the Name box takes the whole address or only the part before the domain.
const ownershipName = computed(() => (parts.value.sub ? `_clubmanager-verify.${parts.value.sub}` : '_clubmanager-verify'));

// Where the browser refuses, the value is still on screen to select.
const { copied, copy } = useClipboard({ resetAfter: 1500 });

const CELL = 'px-3 py-2 font-mono text-[11px] font-semibold text-slate-900 dark:text-slate-100';
const COPY = 'ml-1 cursor-pointer rounded-xl border border-slate-300 bg-white px-1.5 py-0.5 font-sans text-[10px] font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
const TABLE_BOX = 'overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900';
const TABLE_HEAD = 'text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400';
const TABLE_ROW = 'border-t border-slate-200 dark:border-slate-800';
const NOTE = 'text-slate-500 dark:text-slate-400';
</script>

<template>
    <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Connect {{ domain }}</h4>
            <Badge :variant="active ? 'success' : 'warning'">
                {{ active ? `Active${verifiedAt ? ' since ' + verifiedAt : ''}` : 'Waiting for the DNS record' }}
            </Badge>
        </div>

        <ol class="list-decimal space-y-4 pl-5 text-[11px] leading-relaxed">
            <li v-if="knownProvider" class="space-y-1">
                <div>The DNS for <span class="font-mono font-bold">{{ parts.registrable }}</span> is run by <strong>{{ knownProvider.name }}</strong>. <a :href="knownProvider.url" target="_blank" rel="noopener noreferrer" class="font-medium text-blue-600 underline dark:text-blue-400">Sign in to {{ knownProvider.name }}</a> and open its DNS settings:</div>
                <div>{{ knownProvider.steps }}</div>
                <a :href="knownProvider.help" target="_blank" rel="noopener noreferrer" class="inline-block font-medium text-blue-600 underline dark:text-blue-400">{{ knownProvider.name }}'s own guide to adding a record</a>
            </li>
            <li v-else>
                <strong>Save the domain</strong> above, then sign in to wherever you manage <span class="font-mono font-bold">{{ parts.registrable || 'your domain' }}</span> (the company you bought it from, such as GoDaddy, 123 Reg, Namecheap or Cloudflare) and open its <strong>DNS settings</strong>.<template v-if="unsaved"> Once it is saved we will try to work out which company that is and show its own steps.</template>
            </li>

            <li v-if="!parts.bare" class="space-y-2">
                <div><strong>Add this record</strong> (delete any other record with the same name first):</div>
                <div :class="TABLE_BOX">
                    <table class="w-full min-w-[26rem] text-left">
                        <thead :class="TABLE_HEAD"><tr><th class="px-3 py-1.5">Type</th><th class="px-3 py-1.5">Name (or Host)</th><th class="px-3 py-1.5">Points to (or Value)</th></tr></thead>
                        <tbody>
                            <tr :class="TABLE_ROW">
                                <td :class="CELL">CNAME</td>
                                <td :class="CELL">{{ parts.sub }} <button type="button" :class="COPY" @click="copy(parts.sub)">{{ copied === parts.sub ? 'Copied' : 'Copy' }}</button></td>
                                <td :class="CELL">{{ target }} <button type="button" :class="COPY" @click="copy(target)">{{ copied === target ? 'Copied' : 'Copy' }}</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p :class="NOTE">Some providers want only the first part (<span class="font-mono font-bold">{{ parts.sub }}</span>) in the Name box and others want the whole address (<span class="font-mono font-bold">{{ domain }}</span>). If one is refused, try the other.<template v-if="!knownProvider"> If you use Cloudflare, set the record to <strong>DNS only</strong> (grey cloud) so it can be checked.</template></p>
            </li>

            <li v-else class="space-y-2">
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-900 dark:border-amber-800/40 dark:bg-amber-950/30 dark:text-amber-200">
                    <strong>{{ domain }} has no “www” (or other name) in front of it.</strong> Most providers do not allow a CNAME record on a bare domain, so there are two ways to connect it:
                </div>
                <div><strong>Recommended:</strong> use <span class="font-mono font-bold">www.{{ domain }}</span> instead. Change the domain above and save, then follow the same steps with the name <span class="font-mono font-bold">www</span>. Your provider can usually forward the bare domain to the www address for you.</div>
                <div>
                    <strong>Or</strong> add an <strong>A record</strong> on the bare domain:
                    <div :class="[TABLE_BOX, 'mt-1.5']">
                        <table class="w-full min-w-[22rem] text-left">
                            <thead :class="TABLE_HEAD"><tr><th class="px-3 py-1.5">Type</th><th class="px-3 py-1.5">Name (or Host)</th><th class="px-3 py-1.5">Value</th></tr></thead>
                            <tbody>
                                <tr :class="TABLE_ROW">
                                    <td :class="CELL">A</td>
                                    <td :class="CELL">@</td>
                                    <td :class="CELL">
                                        <template v-if="ips.length"><span v-for="ip in ips" :key="ip" class="mr-2 inline-block">{{ ip }} <button type="button" :class="COPY" @click="copy(ip)">{{ copied === ip ? 'Copied' : 'Copy' }}</button></span></template>
                                        <span v-else class="font-sans font-normal">Ask your administrator for this server's address</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li v-if="showOwnership" class="space-y-2">
                <div><strong>Add a second record to prove the domain is yours.</strong> This stops anyone else claiming an address that already points here:</div>
                <div :class="TABLE_BOX">
                    <table class="w-full min-w-[26rem] text-left">
                        <thead :class="TABLE_HEAD"><tr><th class="px-3 py-1.5">Type</th><th class="px-3 py-1.5">Name (or Host)</th><th class="px-3 py-1.5">Value</th></tr></thead>
                        <tbody>
                            <tr :class="TABLE_ROW">
                                <td :class="CELL">TXT</td>
                                <td :class="CELL">{{ ownershipName }} <button type="button" :class="COPY" @click="copy(ownershipName)">{{ copied === ownershipName ? 'Copied' : 'Copy' }}</button></td>
                                <td :class="CELL">{{ ownership.value }} <button type="button" :class="COPY" @click="copy(ownership.value)">{{ copied === ownership.value ? 'Copied' : 'Copy' }}</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p :class="NOTE">Some providers want only the first part (<span class="font-mono font-bold">{{ ownershipName }}</span>) in the Name box and others want the whole address (<span class="font-mono font-bold">{{ ownership.host }}</span>). Paste the value exactly, without quotation marks unless your provider adds them itself.</p>
            </li>

            <li><strong>Save {{ showOwnership ? 'the records' : 'the record' }}, wait, then press Check now.</strong> A new record usually shows up within minutes but can take up to 48 hours. Until then this page says “Waiting for the DNS record”, which is normal; nothing is wrong.</li>
        </ol>

        <div class="flex flex-wrap items-center gap-3">
            <Button variant="secondary" size="sm" :loading="checking" @click="$emit('check')">
                <template v-if="!checking" #icon><UiIcon name="refresh" :size="14" /></template>
                {{ checking ? 'Checking…' : 'Check now' }}
            </Button>
            <span :class="['text-[11px]', NOTE]">Once it says “Active” your site opens on this domain (the secure certificate is set up on the first visit). Leave the record in place afterwards.</span>
        </div>
    </div>
</template>
