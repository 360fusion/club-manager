<script setup>
import { useForm, usePage } from '@inertiajs/vue3';

// <ReportButton type="post" :id="post.id" />: the type is a key of config launchpad.moderation.types
const props = defineProps({ type: { type: String, required: true }, id: { type: Number, required: true } });

const page = usePage();
const form = useForm({ type: props.type, id: props.id, reason: page.props.reportReasons?.[0]?.key ?? '', details: '' });
const send = () => form.post('/reports', { preserveScroll: true, onSuccess: () => form.reset('details') });
const field = `lp-report-${props.type}-${props.id}`;
</script>

<template>
    <details v-if="page.props.auth && page.props.reportReasons?.length" class="lp-report">
        <summary>{{ $t('Report') }}</summary>
        <form @submit.prevent="send">
            <div class="lp-field">
                <label :for="`${field}-reason`">{{ $t('What is wrong with it?') }}</label>
                <select :id="`${field}-reason`" v-model="form.reason">
                    <option v-for="r in page.props.reportReasons" :key="r.key" :value="r.key">{{ r.label }}</option>
                </select>
            </div>
            <div class="lp-field">
                <label :for="`${field}-details`">{{ $t('Anything else we should know (optional)') }}</label>
                <textarea :id="`${field}-details`" v-model="form.details" maxlength="2000" rows="3"></textarea>
            </div>
            <button class="lp-btn lp-btn--quiet" type="submit" :disabled="form.processing">{{ $t('Send report') }}</button>
        </form>
    </details>
</template>
