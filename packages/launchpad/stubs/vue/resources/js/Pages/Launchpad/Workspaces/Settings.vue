<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';
import { teamUrl } from '../../../team';

const props = defineProps({
    workspace: { type: Object, required: true },
    isOwner: Boolean,
    editableRoles: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    permissionList: { type: Array, default: () => [] },
});

const form = useForm({ name: props.workspace.name, timezone: props.workspace.timezone });
const base = teamUrl(props.workspace.slug);

// what each role may do in this team: the owner ticks it here
const editing = ref(false);
const ticks = reactive(Object.fromEntries(props.roles.map((r) => [r.value, { ...r.permissions }])));
const saveRoles = () => router.put(`${base}/settings/roles`, {
    roles: Object.fromEntries(Object.entries(ticks).filter(([role]) => props.editableRoles.includes(role)).map(([role, keys]) => [role, Object.fromEntries(Object.entries(keys).filter(([, on]) => on).map(([key]) => [key, 1]))])),
}, { preserveScroll: true, onSuccess: () => (editing.value = false) });
const resetRoles = () => router.delete(`${base}/settings/roles`, { preserveScroll: true, onSuccess: () => (editing.value = false) });
</script>

<template>
    <Head title="Team settings" />
    <AccountShell>
        <div class="lp-card">
            <h1>Team settings</h1>
            <p><Link :href="`${base}/members`">Back to your team</Link></p>
            <form @submit.prevent="form.put(`${base}/settings`)">
                <FormField id="name" v-model="form.name" label="Name" :error="form.errors.name" />
                <FormField id="timezone" v-model="form.timezone" label="Time zone" :error="form.errors.timezone" />
                <button class="lp-btn" type="submit" :disabled="form.processing">Save</button>
            </form>
        </div>
        <div v-if="roles.length" class="lp-card">
            <div class="lp-card__head">
                <div>
                    <h2>What each role can do</h2>
                    <p class="lp-muted">Choose what a person gets from their role in this team. The owner can always do everything. You can still change what one person can do on their row of the team page.</p>
                </div>
                <div v-if="editableRoles.length && editing" class="lp-actions">
                    <button class="lp-btn lp-btn--quiet" type="button" @click="resetRoles">Reset defaults</button>
                    <button class="lp-btn lp-btn--quiet" type="button" @click="editing = false">Cancel</button>
                    <button class="lp-btn" type="button" @click="saveRoles">Save</button>
                </div>
                <div v-else-if="editableRoles.length" class="lp-actions">
                    <button class="lp-btn" type="button" @click="editing = true">Edit</button>
                </div>
            </div>
            <div class="lp-roles">
                <fieldset v-for="r in roles" :key="r.value" class="lp-role-card">
                    <legend class="lp-skip">{{ r.label }}</legend>
                    <h3>{{ r.label }}</h3>
                    <p class="lp-muted">{{ r.about }}</p>
                    <p class="lp-role-card__label">Permissions</p>
                    <label v-for="p in permissionList" :key="p.key" class="lp-check lp-check--tight" :title="p.about">
                        <input v-model="ticks[r.value][p.key]" type="checkbox" :disabled="!editing || !editableRoles.includes(r.value)">
                        <span>{{ p.label }}<br><span class="lp-muted lp-small">{{ p.about }}</span></span>
                    </label>
                </fieldset>
            </div>
            <div v-if="$page.props.errors.roles" class="lp-alert lp-alert--error" role="alert">{{ $page.props.errors.roles }}</div>
            <p v-if="!editableRoles.length" class="lp-muted">Only an owner or admin can change this.</p>
            <p v-else-if="!editableRoles.includes('admin')" class="lp-muted">Only the owner can change what an admin can do.</p>
        </div>
        <div v-if="isOwner && !workspace.personal" class="lp-card">
            <h2>Delete this team</h2>
            <p class="lp-muted">This removes the team and its members' access. You will be asked for your password.</p>
            <div v-if="$page.props.errors.workspace" class="lp-alert lp-alert--error" role="alert">{{ $page.props.errors.workspace }}</div>
            <button class="lp-btn lp-btn--danger" type="button" @click="router.delete(base)">Delete team</button>
        </div>
    </AccountShell>
</template>
