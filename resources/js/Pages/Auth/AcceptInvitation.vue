<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';

const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
  token: {
    type: String,
    required: true,
  },
  user: {
    type: Object,
    required: true,
  },
  isExistingUser: {
    type: Boolean,
    default: false,
  },
});

const form = useForm({
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post(route('invitation.submit', { slug: props.club.slug, token: props.token }), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
};
</script>

<template>
  <Head :title="`Join ${club.name}`" />

  <!-- The card is always dark, so the kit components take their dark look inside it. -->
  <div class="dark min-h-screen bg-slate-950 flex flex-col justify-center items-center p-6 text-slate-100 font-sans">
    <div class="w-full max-w-md bg-slate-900/90 backdrop-blur-xl border border-slate-800 rounded-2xl p-8 shadow-2xl space-y-6">

      <!-- Club header and welcome -->
      <div class="text-center space-y-3">
        <div v-if="club.logo_url" class="inline-block mb-1">
          <img :src="club.logo_url" :alt="club.name" class="h-14 max-w-44 object-contain mx-auto" />
        </div>
        <div v-else class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 text-2xl font-semibold mb-1">
          {{ club.name.substring(0, 2).toUpperCase() }}
        </div>

        <div>
          <Badge variant="info">
            {{ isExistingUser ? 'Existing account access' : 'Official member invitation' }}
          </Badge>
          <h1 class="text-2xl font-semibold text-white mt-2">
            {{ isExistingUser ? `Welcome back, ${user.name}!` : `Welcome to ${club.name}!` }}
          </h1>
          <p v-if="club.tagline" class="text-xs text-slate-400 mt-0.5">
            {{ club.tagline }}
          </p>
        </div>
      </div>

      <!-- Invited user -->
      <div class="p-4 bg-slate-950/80 border border-slate-800 rounded-2xl space-y-1">
        <div class="text-[11px] font-medium uppercase tracking-wider text-slate-500">
          {{ isExistingUser ? 'Confirm access for' : 'Invited member' }}
        </div>
        <div class="font-semibold text-sm text-slate-100">{{ user.name }}</div>
        <div class="text-xs font-mono text-blue-400">{{ user.email }}</div>
        <p v-if="isExistingUser" class="text-[11px] text-slate-400 pt-1">
          You already have an active Club Manager account. Enter your password to log in and access <strong>{{ club.name }}</strong>.
        </p>
      </div>

      <!-- Errors -->
      <Alert v-if="Object.keys(form.errors).length" variant="danger">
        <div v-for="(error, key) in form.errors" :key="key">{{ error }}</div>
      </Alert>

      <!-- Password form -->
      <form @submit.prevent="submit" class="space-y-4">
        <Input
          v-model="form.password"
          :label="isExistingUser ? 'Your account password' : 'Choose a password'"
          type="password"
          required
          autofocus
          :placeholder="isExistingUser ? 'Your account password...' : 'Min. 8 characters...'"
        />

        <Input
          v-if="!isExistingUser"
          v-model="form.password_confirmation"
          label="Confirm password"
          type="password"
          required
          placeholder="Confirm password..."
        />

        <Button type="submit" size="lg" block :loading="form.processing" class="mt-2">
          {{ isExistingUser ? `Log in and access ${club.name}` : 'Activate account and enter member portal' }}
        </Button>
      </form>
    </div>
  </div>
</template>
