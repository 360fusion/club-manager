<script setup>
// The Secretary passes on a sister or reciprocal lodge's summons or notice, or a Provincial or Grand Lodge
// communication, once; each member gets it as they chose (PassOnController, SendPassedOnItem).
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';

const props = defineProps({
    club: { type: Object, required: true },
    kinds: { type: Array, required: true },
    lodges: { type: Object, required: true },
    sent: { type: Array, required: true },
});

const form = useForm({
    kind: 'sister',
    type: 'summons',
    lodge_slug: '',
    title: '',
    body: '',
    attachment: null,
    meeting_date: '',
    meeting_time: '',
    venue: '',
    dress_code: '',
});

const forLodge = computed(() => ['sister', 'reciprocal'].includes(form.kind));
const isSummons = computed(() => forLodge.value && form.type === 'summons');
const lodgeOptions = computed(() => (props.lodges[form.kind] ?? []).map((lodge) => ({ value: lodge.slug, label: lodge.name })));
const chosenLodge = computed(() => (props.lodges[form.kind] ?? []).find((lodge) => lodge.slug === form.lodge_slug));

function chooseKind(kind) {
    form.kind = kind;
    form.lodge_slug = '';
    form.clearErrors();
}

function pickFile(event) {
    form.attachment = event.target.files[0] ?? null;
}

function send() {
    form.post(route('member.pass_on.store', { slug: props.club.slug }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <MembersLayout title="Pass on" :club="club" active-tab="lodges">
        <div class="mx-auto max-w-2xl space-y-5">
            <div>
                <h2 class="font-display text-2xl font-semibold text-slate-900 dark:text-white">Pass something on</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Members get it in the app or by email, as each of them chose. Nobody gets the same meeting or circular twice.</p>
                <Link :href="route('member.pass_on.lists', { slug: club.slug })" class="mt-2 inline-block text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Sister and reciprocal lodges</Link>
            </div>

            <form class="space-y-4" @submit.prevent="send">
                <div class="grid grid-cols-2 gap-2" role="group" aria-label="What is it?">
                    <button v-for="kind in kinds" :key="kind.key" type="button" :aria-pressed="form.kind === kind.key" :class="['min-h-14 rounded-xl border px-3 py-2 text-left text-sm font-medium', form.kind === kind.key ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200']" @click="chooseKind(kind.key)">{{ kind.label }}</button>
                </div>

                <Card class="space-y-4">
                    <template v-if="forLodge">
                        <p v-if="!lodgeOptions.length" class="text-sm text-slate-600 dark:text-slate-300">
                            No lodges on this list yet. <Link :href="route('member.pass_on.lists', { slug: club.slug })" class="font-medium text-blue-600 hover:underline dark:text-blue-400">Add some</Link>.
                        </p>
                        <Select v-else v-model="form.lodge_slug" label="Which lodge" :options="[{ value: '', label: 'Choose a lodge' }, ...lodgeOptions]" :error="form.errors.lodge_slug" required />

                        <div class="flex gap-2" role="group" aria-label="Summons or notice">
                            <button v-for="[value, label] in [['summons', 'A summons'], ['notice', 'A notice']]" :key="value" type="button" :aria-pressed="form.type === value" :class="['min-h-11 rounded-full border px-4 text-sm font-medium', form.type === value ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200']" @click="form.type = value">{{ label }}</button>
                        </div>

                        <Alert v-if="isSummons && chosenLodge?.publishes_own" variant="warning">
                            {{ chosenLodge.name }} publishes its summonses on Club Manager, so your members already get the date, time and venue. Attach the PDF only if you have the full summons.
                        </Alert>
                    </template>

                    <Input v-model="form.title" label="Title" :placeholder="isSummons ? 'Regular meeting' : 'What it is about'" :error="form.errors.title" required />

                    <div v-if="isSummons" class="grid grid-cols-2 gap-3">
                        <Input v-model="form.meeting_date" type="date" label="Meeting date" :error="form.errors.meeting_date" required />
                        <Input v-model="form.meeting_time" type="time" label="Time" :error="form.errors.meeting_time" />
                        <Input v-model="form.venue" label="Venue" class="col-span-2" :error="form.errors.venue" />
                        <Input v-model="form.dress_code" label="Dress" class="col-span-2" :error="form.errors.dress_code" />
                    </div>

                    <Textarea v-model="form.body" label="Text or a note" hint="Paste the text, or add a note to go with the PDF." :rows="5" :error="form.errors.body" />

                    <div>
                        <label for="pass-on-pdf" class="block text-sm font-medium text-slate-700 dark:text-slate-200">The PDF</label>
                        <input id="pass-on-pdf" type="file" accept="application/pdf,.pdf" class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:min-h-11 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:text-sm file:font-medium dark:text-slate-200 dark:file:bg-slate-800 dark:file:text-slate-100" @change="pickFile" />
                        <p v-if="form.errors.attachment" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ form.errors.attachment }}</p>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">{{ form.processing ? 'Sending' : 'Pass it on' }}</Button>
                </Card>
            </form>

            <Card padding="none" class="divide-y divide-slate-100 dark:divide-slate-800">
                <h3 class="px-4 py-3 font-medium text-slate-900 dark:text-white">Passed on lately</h3>
                <p v-if="!sent.length" class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">Nothing yet.</p>
                <div v-for="item in sent" :key="item.id" class="space-y-1 px-4 py-3 text-sm">
                    <div class="flex items-start justify-between gap-2">
                        <span class="min-w-0 font-medium text-slate-900 dark:text-white">{{ item.title }}</span>
                        <Badge :variant="item.sending ? 'info' : 'neutral'">{{ item.sending ? 'Sending' : item.sent_on }}</Badge>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ [item.lodge || item.kind, item.meeting_date].filter(Boolean).join(' · ') }}</p>
                    <p v-if="!item.sending" class="text-xs text-slate-500 dark:text-slate-400">To {{ item.recipients }}, {{ item.emails }} by email<template v-if="item.attachment_url"> · <a :href="item.attachment_url" target="_blank" rel="noopener" class="font-medium text-blue-600 hover:underline dark:text-blue-400">PDF</a></template></p>
                </div>
            </Card>
        </div>
    </MembersLayout>
</template>
