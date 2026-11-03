<script setup>
import { useForm, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';

defineProps({
  tokens: { type: Array, default: () => [] },
  twoFactorOn: { type: Boolean, default: false },
  expiryDays: { type: Array, required: true },
  newToken: { type: String, default: null },
});

const form = useForm({ name: '', days: 90 });

const create = () => form.post(route('account.tokens.store'), { preserveScroll: true, onSuccess: () => form.reset('name') });
const revoke = (token) => {
  if (window.confirm(`Revoke "${token.name}"? Anything using it stops working straight away.`)) {
    router.delete(route('account.tokens.destroy', token.id), { preserveScroll: true });
  }
};
</script>

<template>
  <MembersLayout title="API tokens" active-tab="">
    <div class="max-w-3xl space-y-6">
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">API tokens</h1>

      <Alert v-if="newToken" variant="success">
        <p class="font-semibold">Copy your new token now. It is shown only once.</p>
        <code class="mt-2 block break-all rounded-xl bg-white/70 p-2 text-xs dark:bg-slate-900/60">{{ newToken }}</code>
      </Alert>

      <Card class="space-y-3">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Make a token</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300">A token lets an app act as you with the access shown below. Treat it like a password.</p>
        <Alert v-if="twoFactorOn" variant="warning">Your account uses two-factor sign-in, so tokens cannot be made: a token would skip the second step.</Alert>
        <form v-else class="flex flex-wrap items-end gap-3" @submit.prevent="create">
          <Input v-model="form.name" label="Name" placeholder="For example: my phone" required :error="form.errors.name" />
          <label class="text-sm text-slate-700 dark:text-slate-200">
            <span class="mb-1 block font-medium">Expires after</span>
            <select v-model="form.days" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
              <option v-for="d in expiryDays" :key="d" :value="d">{{ d }} days</option>
            </select>
          </label>
          <Button type="submit" :disabled="form.processing">Make token</Button>
        </form>
      </Card>

      <Card padding="none" class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
            <tr><th class="p-3">Name</th><th class="p-3">Can do</th><th class="p-3">Last used</th><th class="p-3">Expires</th><th class="p-3"><span class="sr-only">Revoke</span></th></tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            <tr v-for="t in tokens" :key="t.id">
              <td class="p-3 font-medium text-slate-900 dark:text-white">{{ t.name }}<div class="font-normal text-slate-500">Made {{ t.created }}</div></td>
              <td class="p-3"><Badge v-for="a in t.abilities" :key="a">{{ a === 'profile:read' ? 'Read your name and email' : a }}</Badge></td>
              <td class="p-3 text-slate-600 dark:text-slate-300">{{ t.last_used || 'Never' }}</td>
              <td class="p-3 text-slate-600 dark:text-slate-300">{{ t.expires || 'Never' }}</td>
              <td class="p-3 text-right"><Button variant="secondary" size="sm" @click="revoke(t)">Revoke</Button></td>
            </tr>
            <tr v-if="!tokens.length"><td colspan="5" class="p-8 text-center text-slate-500 dark:text-slate-400">You have no tokens.</td></tr>
          </tbody>
        </table>
      </Card>
    </div>
  </MembersLayout>
</template>
