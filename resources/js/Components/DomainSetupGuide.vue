<script setup>
// Step-by-step help for connecting a custom domain: exactly which DNS record to add at the domain's provider, worked
// out from the domain typed in the box (a "www" or other subdomain gets a CNAME; a bare domain gets a warning and an
// A record). The parent saves the domain and runs the DNS check; this only explains and shows the status.
import { computed, ref } from 'vue';

const props = defineProps({
    domain: { type: String, default: '' },
    status: { type: String, default: null },
    verifiedAt: { type: String, default: null },
    instructions: { type: Object, default: () => ({}) },
    checking: { type: Boolean, default: false },
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

const copied = ref('');
const copy = async (value) => {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = value;
        setTimeout(() => { if (copied.value === value) copied.value = ''; }, 1500);
    } catch { /* clipboard blocked: the value is still on screen to select */ }
};

const CELL = 'px-3 py-2 font-mono text-[11px] font-bold text-slate-900 dark:text-slate-100';
</script>

<template>
    <div class="space-y-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h4 class="text-xs font-bold">Connect {{ domain }}</h4>
            <span :class="['rounded-full border px-2.5 py-0.5 text-[10px] font-extrabold', active ? 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300' : 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-800/60 dark:bg-amber-900/40 dark:text-amber-300']">
                {{ active ? `✅ Active${verifiedAt ? ' since ' + verifiedAt : ''}` : '⏳ Waiting for the DNS record' }}
            </span>
        </div>

        <ol class="list-decimal space-y-4 pl-5 text-[11px] leading-relaxed">
            <li>
                <strong>Save the domain</strong> above, then sign in to wherever you manage <span class="font-mono font-bold">{{ parts.registrable || 'your domain' }}</span> (the company you bought it from, such as GoDaddy, 123 Reg, Namecheap or Cloudflare) and open its <strong>DNS settings</strong>.
            </li>

            <li v-if="!parts.bare" class="space-y-2">
                <div><strong>Add this record</strong> (delete any other record with the same name first):</div>
                <div class="overflow-x-auto rounded-xl border border-amber-200 bg-white dark:border-amber-800/60 dark:bg-slate-900">
                    <table class="w-full min-w-[26rem] text-left">
                        <thead class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400"><tr><th class="px-3 py-1.5">Type</th><th class="px-3 py-1.5">Name (or Host)</th><th class="px-3 py-1.5">Points to (or Value)</th></tr></thead>
                        <tbody>
                            <tr class="border-t border-amber-100 dark:border-amber-900/50">
                                <td :class="CELL">CNAME</td>
                                <td :class="CELL">{{ parts.sub }} <button type="button" class="ml-1 cursor-pointer rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 hover:bg-amber-200 dark:bg-amber-900/60 dark:text-amber-200" @click="copy(parts.sub)">{{ copied === parts.sub ? 'Copied' : 'Copy' }}</button></td>
                                <td :class="CELL">{{ target }} <button type="button" class="ml-1 cursor-pointer rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 hover:bg-amber-200 dark:bg-amber-900/60 dark:text-amber-200" @click="copy(target)">{{ copied === target ? 'Copied' : 'Copy' }}</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-amber-800/90 dark:text-amber-200/80">Some providers want only the first part (<span class="font-mono font-bold">{{ parts.sub }}</span>) in the Name box and others want the whole address (<span class="font-mono font-bold">{{ domain }}</span>). If one is refused, try the other. If you use Cloudflare, set the record to <strong>DNS only</strong> (grey cloud) so it can be checked.</p>
            </li>

            <li v-else class="space-y-2">
                <div class="rounded-xl border border-amber-300 bg-amber-100/70 p-3 dark:border-amber-700/60 dark:bg-amber-900/30">
                    <strong>{{ domain }} has no “www” (or other name) in front of it.</strong> Most providers do not allow a CNAME record on a bare domain, so there are two ways to connect it:
                </div>
                <div><strong>Recommended:</strong> use <span class="font-mono font-bold">www.{{ domain }}</span> instead. Change the domain above and save, then follow the same steps with the name <span class="font-mono font-bold">www</span>. Your provider can usually forward the bare domain to the www address for you.</div>
                <div>
                    <strong>Or</strong> add an <strong>A record</strong> on the bare domain:
                    <div class="mt-1.5 overflow-x-auto rounded-xl border border-amber-200 bg-white dark:border-amber-800/60 dark:bg-slate-900">
                        <table class="w-full min-w-[22rem] text-left">
                            <thead class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400"><tr><th class="px-3 py-1.5">Type</th><th class="px-3 py-1.5">Name (or Host)</th><th class="px-3 py-1.5">Value</th></tr></thead>
                            <tbody>
                                <tr class="border-t border-amber-100 dark:border-amber-900/50">
                                    <td :class="CELL">A</td>
                                    <td :class="CELL">@</td>
                                    <td :class="CELL">
                                        <template v-if="ips.length"><span v-for="ip in ips" :key="ip" class="mr-2 inline-block">{{ ip }} <button type="button" class="ml-1 cursor-pointer rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 hover:bg-amber-200 dark:bg-amber-900/60 dark:text-amber-200" @click="copy(ip)">{{ copied === ip ? 'Copied' : 'Copy' }}</button></span></template>
                                        <span v-else class="font-sans font-normal">Ask your administrator for this server's address</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li><strong>Save the record, wait, then press Check Now.</strong> A new record usually shows up within minutes but can take up to 48 hours. Until then this page says “Waiting for the DNS record”, which is normal; nothing is wrong.</li>
        </ol>

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" :disabled="checking" class="cursor-pointer rounded-xl bg-amber-600 px-3.5 py-1.5 text-[11px] font-bold text-white shadow-sm transition-colors hover:bg-amber-700 disabled:cursor-wait disabled:opacity-60" @click="$emit('check')">
                {{ checking ? 'Checking…' : '🔄 Check Now' }}
            </button>
            <span class="text-[11px] text-amber-800/90 dark:text-amber-200/80">“Active” means we can see your DNS record. Leave the record in place afterwards.</span>
        </div>
    </div>
</template>
