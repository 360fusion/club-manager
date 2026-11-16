<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import { locale } from '../../../i18n';
import { Head, router, useForm } from '@inertiajs/vue3';
import { teamUrl } from '../../../team';

const props = defineProps({
    workspace: { type: Object, required: true },
    files: { type: Array, default: () => [] },
    summary: { type: String, default: '' },
    accept: { type: String, default: '' },
});

const base = teamUrl(props.workspace.slug);
const form = useForm({ file: null });
const when = (value) => new Date(value).toLocaleDateString(locale());
const remove = (file) => router.delete(`${base}/files/${file.id}`);
</script>

<template>
    <Head :title="$t(':team files', { team: workspace.name })" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t(':team: Files', { team: workspace.name }) }}</h1>
            <p class="lp-muted">{{ summary }}. {{ $t('Anyone in the team can add a file and download any of them.') }}</p>
            <p v-if="!files.length">{{ $t('No files yet.') }}</p>
            <div v-else class="lp-table-wrap">
                <table>
                    <thead><tr><th scope="col">{{ $t('Name') }}</th><th scope="col">{{ $t('Size') }}</th><th scope="col">{{ $t('Added by') }}</th><th scope="col">{{ $t('When') }}</th><th scope="col"><span class="lp-skip">{{ $t('Actions') }}</span></th></tr></thead>
                    <tbody>
                        <tr v-for="file in files" :key="file.id">
                            <td><a :href="`${base}/files/${file.id}`">{{ file.name }}</a></td>
                            <td>{{ file.size }}</td>
                            <td>{{ file.by ?? $t('Someone who has left') }}</td>
                            <td>{{ when(file.at) }}</td>
                            <td><button v-if="file.canDelete" class="lp-btn lp-btn--quiet" type="button" @click="remove(file)">{{ $t('Delete') }}</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="lp-card">
            <h2>{{ $t('Add a file') }}</h2>
            <form @submit.prevent="form.post(`${base}/files`, { onSuccess: () => form.reset('file') })">
                <div class="lp-field">
                    <label for="file">{{ $t('File') }}</label>
                    <input id="file" type="file" :accept="accept" required @input="form.file = $event.target.files[0]">
                    <p v-if="form.errors.file" class="lp-error">{{ form.errors.file }}</p>
                </div>
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Upload') }}</button>
            </form>
        </div>
    </AccountShell>
</template>
