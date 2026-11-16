<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';
import { teamUrl } from '../../../team';

const props = defineProps({
    workspace: { type: Object, required: true },
    role: String,
    canManageMembers: Boolean,
    canManageWorkspace: Boolean,
    isOwner: Boolean,
    members: { type: Array, required: true },
    invitations: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    assignable: { type: Array, default: () => [] },
    permissionList: { type: Array, default: () => [] },
    roleMatrix: { type: Array, default: () => [] },
    editableRoles: { type: Array, default: () => [] },
});

const base = teamUrl(props.workspace.slug);
const invite = useForm({ email: '', role: props.assignable[props.assignable.length - 1]?.value ?? 'member', permissions: {} });

const changeRole = (member, role) => router.put(`${base}/members/${member.id}`, { role });
const remove = (member) => router.delete(`${base}/members/${member.id}`);
const withdraw = (invitation) => router.delete(`${base}/invitations/${invitation.id}`);
const leave = () => router.post(`${base}/leave`);

// a person can only hand out the permissions they hold themselves: their own row says which
const mine = computed(() => props.members.find((m) => m.email === usePage().props.auth?.user.email)?.permissions ?? {});
const ticks = reactive(Object.fromEntries(props.members.map((m) => [m.id, { ...(m.permissions ?? {}) }])));
const savePermissions = (member) => router.put(`${base}/members/${member.id}/permissions`, {
    permissions: Object.fromEntries(Object.entries(ticks[member.id]).filter(([, on]) => on).map(([key]) => [key, 1])),
}, { preserveScroll: true });
const roleColumns = ['owner', 'admin', 'manager', 'member'];
const editingRoles = ref(false);
// what each role may do in this team: ticked here by whoever may change it
const roleTicks = reactive(Object.fromEntries(roleColumns.map((r) => [r, Object.fromEntries(props.roleMatrix.filter((row) => row.key).map((row) => [row.key, !!row.roles[r]]))])));
const saveRoleTable = () => router.put(`${base}/settings/roles`, {
    roles: Object.fromEntries(props.editableRoles.map((r) => [r, Object.fromEntries(Object.entries(roleTicks[r]).filter(([, on]) => on).map(([key]) => [key, 1]))])),
}, { preserveScroll: true, onSuccess: () => (editingRoles.value = false) });
const resetRoleTable = () => router.delete(`${base}/settings/roles`, { preserveScroll: true, onSuccess: () => (editingRoles.value = false) });
const granted = (member) => props.permissionList.filter((p) => member.permissions?.[p.key]).map((p) => p.label).join(', ') || 'None';
</script>

<template>
    <Head title="Team" />
    <AccountShell>
        <div v-if="$page.props.errors.member" class="lp-alert lp-alert--error" role="alert">{{ $page.props.errors.member }}</div>
        <div class="lp-card">
            <h1>Your team</h1>
            <p class="lp-working" role="group" aria-label="Working in">
                <span class="lp-muted">Working in</span>
                <a v-for="t in teams" :key="t.slug" class="lp-pill" :href="teamUrl(t.slug, 'members')" :aria-current="t.current ? 'page' : null">{{ t.name }}</a>
                <Link href="/workspaces">All teams</Link>
                <Link v-if="canManageWorkspace" :href="`${base}/settings`">Team settings</Link>
            </p>
            <h2>About team members</h2>
            <p>Team members are people you invite to work in this team with their own login, so nobody shares a password. What they can do is up to you, and billing and deleting the team always stay with the owner.</p>
            <ol class="lp-steps">
                <li><span class="lp-step" aria-hidden="true">1</span><div><strong>Invite</strong><br>Type their email address below and choose what they may do.</div></li>
                <li><span class="lp-step" aria-hidden="true">2</span><div><strong>They accept</strong><br>They get an email with a link that works for 7 days. They sign in, or sign up with that address, and click Join.</div></li>
                <li><span class="lp-step" aria-hidden="true">3</span><div><strong>Manage</strong><br>Change what someone may do, or take their access away, whenever you like, in the list below.</div></li>
            </ol>
            <p class="lp-muted">{{ members.length }} {{ members.length === 1 ? 'person' : 'people' }} in this team<template v-if="invitations.length">, and {{ invitations.length }} {{ invitations.length === 1 ? 'invitation' : 'invitations' }} waiting</template>.</p>
        </div>
        <div v-if="canManageMembers" class="lp-card lp-card--accent">
            <h2>Invite a team member</h2>
            <form @submit.prevent="invite.post(`${base}/invitations`, { onSuccess: () => invite.reset('email', 'permissions') })">
                <FormField id="email" v-model="invite.email" label="Email address" type="email" :error="invite.errors.email" />
                <div class="lp-field">
                    <label for="invite-role">Role</label>
                    <select id="invite-role" v-model="invite.role">
                        <option v-for="option in assignable" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <p v-if="invite.errors.role" class="lp-error">{{ invite.errors.role }}</p>
                </div>
                <fieldset v-if="permissionList.length" class="lp-field lp-field--set">
                    <legend>They may also</legend>
                    <label v-for="p in permissionList" :key="p.key" class="lp-check lp-check--tight" :title="p.about">
                        <input v-model="invite.permissions[p.key]" type="checkbox" :disabled="!mine[p.key]">
                        <span>{{ p.label }} <span class="lp-muted">{{ p.about }}</span></span>
                    </label>
                    <p v-if="invite.errors.permissions" class="lp-error">{{ invite.errors.permissions }}</p>
                </fieldset>
                <button class="lp-btn" type="submit" :disabled="invite.processing">Send invitation</button>
            </form>
        </div>
        <div class="lp-card">
            <h2>People in this team</h2>
            <table>
                <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th v-if="permissionList.length" scope="col">Permissions</th><th scope="col"><span class="lp-skip">Actions</span></th></tr></thead>
                <tbody>
                    <tr v-for="member in members" :key="member.id">
                        <td>{{ member.name }}</td>
                        <td>{{ member.email }}</td>
                        <td>
                            <template v-if="member.canManage">
                                <label class="lp-skip" :for="`role-${member.id}`">Role for {{ member.name }}</label>
                                <select :id="`role-${member.id}`" :value="member.role" @change="changeRole(member, $event.target.value)">
                                    <option v-for="option in assignable" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </template>
                            <template v-else>{{ member.roleLabel }}</template>
                        </td>
                        <td v-if="permissionList.length">
                            <form v-if="member.canManage" class="lp-perms" @submit.prevent="savePermissions(member)">
                                <label v-for="p in permissionList" :key="p.key" class="lp-check lp-check--tight" :title="p.about">
                                    <input v-model="ticks[member.id][p.key]" type="checkbox" :disabled="!mine[p.key]">
                                    <span>{{ p.label }}</span>
                                </label>
                                <button class="lp-btn lp-btn--quiet" type="submit">Save</button>
                            </form>
                            <template v-else>{{ granted(member) }}</template>
                        </td>
                        <td><button v-if="member.canManage" class="lp-btn lp-btn--quiet" type="button" @click="remove(member)">Remove</button></td>
                    </tr>
                </tbody>
            </table>
            <template v-if="invitations.length">
                <h3>Waiting to accept</h3>
                <ul>
                    <li v-for="invitation in invitations" :key="invitation.id">
                        {{ invitation.email }} ({{ invitation.roleLabel }}<template v-if="invitation.permissions?.length">, {{ invitation.permissions.join(', ') }}</template>)
                        <button class="lp-btn lp-btn--quiet" type="button" @click="withdraw(invitation)">Withdraw</button>
                    </li>
                </ul>
            </template>
        </div>
        <div id="roles" class="lp-card">
            <div class="lp-card__head">
                <div>
                    <h2>What each role can do</h2>
                    <p class="lp-muted">This is what each role gets in this team. {{ editableRoles.length ? 'Press Edit to change it for everyone in a role.' : 'An owner or admin can change it.' }} {{ canManageMembers ? 'You can also change what one person can do, on their row above.' : '' }}</p>
                </div>
                <div v-if="editableRoles.length && editingRoles" class="lp-actions">
                    <button class="lp-btn lp-btn--quiet" type="button" @click="resetRoleTable">Reset to defaults</button>
                    <button class="lp-btn lp-btn--quiet" type="button" @click="editingRoles = false">Cancel</button>
                    <button class="lp-btn" type="button" @click="saveRoleTable">Save</button>
                </div>
                <div v-else-if="editableRoles.length" class="lp-actions">
                    <button class="lp-btn" type="button" @click="editingRoles = true">Edit</button>
                </div>
            </div>
            <div v-if="$page.props.errors.roles" class="lp-alert lp-alert--error" role="alert">{{ $page.props.errors.roles }}</div>
            <div class="lp-table-wrap">
                <table>
                    <thead><tr><th scope="col">Can</th><th v-for="r in roleColumns" :key="r" scope="col">{{ r.charAt(0).toUpperCase() + r.slice(1) }}</th></tr></thead>
                    <tbody>
                        <tr v-for="row in roleMatrix" :key="row.label">
                            <th scope="row">{{ row.label }}<br><span class="lp-muted lp-small">{{ row.about }}</span></th>
                            <td v-for="r in roleColumns" :key="r">
                                <label v-if="editingRoles && row.key && editableRoles.includes(r)"><input v-model="roleTicks[r][row.key]" type="checkbox"><span class="lp-skip">{{ row.label }} for {{ r }}</span></label>
                                <template v-else-if="row.roles[r]"><span aria-hidden="true">&#10003;</span><span class="lp-skip">Yes</span></template>
                                <template v-else><span class="lp-muted" aria-hidden="true">&ndash;</span><span class="lp-skip">No</span></template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <button v-if="!isOwner" class="lp-btn lp-btn--quiet" type="button" @click="leave">Leave this team</button>
    </AccountShell>
</template>
