<script setup>
import { useForm, Head } from '@inertiajs/vue3';

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

  <div class="min-h-screen bg-slate-950 flex flex-col justify-center items-center p-6 text-slate-100 font-sans">
    <div class="w-full max-w-md bg-slate-900/90 backdrop-blur-xl border border-slate-800 rounded-3xl p-8 shadow-2xl space-y-6">
      
      <!-- Club Header & Welcome Banner -->
      <div class="text-center space-y-3">
        <div v-if="club.logo_url" class="inline-block mb-1">
          <img :src="club.logo_url" :alt="club.name" class="h-14 max-w-44 object-contain mx-auto" />
        </div>
        <div v-else class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-2xl font-black mb-1">
          {{ club.name.substring(0, 2).toUpperCase() }}
        </div>

        <div>
          <span class="px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
            Official Member Invitation
          </span>
          <h1 class="text-2xl font-black text-white mt-2">
            Welcome to {{ club.name }}!
          </h1>
          <p v-if="club.tagline" class="text-xs text-slate-400 mt-0.5">
            {{ club.tagline }}
          </p>
        </div>
      </div>

      <!-- Invited User Context Box -->
      <div class="p-4 bg-slate-950/80 border border-slate-800 rounded-2xl space-y-1">
        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Invited Member</div>
        <div class="font-bold text-sm text-slate-100">{{ user.name }}</div>
        <div class="text-xs font-mono text-indigo-400">{{ user.email }}</div>
      </div>

      <!-- Errors -->
      <div v-if="Object.keys(form.errors).length" class="p-3.5 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-xs space-y-1">
        <div v-for="(error, key) in form.errors" :key="key">{{ error }}</div>
      </div>

      <!-- Password Creation Form -->
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">
            Choose Account Password
          </label>
          <input
            v-model="form.password"
            type="password"
            required
            autofocus
            placeholder="Min. 8 characters..."
            class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500 transition-colors"
          />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">
            Confirm Password
          </label>
          <input
            v-model="form.password_confirmation"
            type="password"
            required
            placeholder="Confirm password..."
            class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500 transition-colors"
          />
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-sm transition-all shadow-lg shadow-indigo-600/20 disabled:opacity-50 mt-2 cursor-pointer flex items-center justify-center gap-2"
        >
          <span>Activate Account & Enter Member Portal</span>
          <span>&rarr;</span>
        </button>
      </form>
    </div>
  </div>
</template>
