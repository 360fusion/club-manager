<script setup>
import { useForm, Head } from '@inertiajs/vue3'

const props = defineProps({
  club: Object,
})

const form = useForm({
  name: '',
  email: '',
  rank: 'WBro',
  home_club_name: '',
  home_club_number: '',
  phone: '',
  dietary_notes: '',
})

const submit = () => {
  form.post(route('clubs.visitor.store', { slug: props.club.slug }), {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head :title="`Visitor Sign-Up — ${club.name}`" />

  <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 selection:bg-indigo-500 selection:text-white">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 mb-4 shadow-xl">
        <span class="text-3xl">🏛️</span>
      </div>
      <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
        Visiting Brethren Sign-Up
      </h1>
      <p class="mt-2 text-sm text-slate-400 font-medium">
        Register as a Visitor for <span class="text-indigo-400 font-semibold">{{ club.name }}</span> to receive meeting summonses and RSVP invites.
      </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-xl px-4 sm:px-0">
      <div class="bg-slate-800/90 border border-slate-700/80 backdrop-blur-xl py-8 px-6 shadow-2xl rounded-3xl sm:px-10">
        
        <!-- Success Alert -->
        <div v-if="$page.props.flash?.success" class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-medium flex items-start gap-3">
          <span class="text-lg">✅</span>
          <div>
            <div class="font-bold mb-1">Registration Complete!</div>
            <div>{{ $page.props.flash.success }}</div>
          </div>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
          <!-- Rank / Title & Full Name -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Rank / Title</label>
              <input 
                v-model="form.rank"
                type="text"
                placeholder="e.g. WBro / Bro"
                class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Full Name *</label>
              <input 
                v-model="form.name"
                type="text"
                required
                placeholder="e.g. John David Smith"
                class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
              <div v-if="form.errors.name" class="text-rose-400 text-xs mt-1">{{ form.errors.name }}</div>
            </div>
          </div>

          <!-- Email Address -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Email Address *</label>
            <input 
              v-model="form.email"
              type="email"
              required
              placeholder="visitor@example.com"
              class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <p class="text-[11px] text-slate-400 mt-1">Summons announcements and passwordless RSVP links will be sent here.</p>
            <div v-if="form.errors.email" class="text-rose-400 text-xs mt-1">{{ form.errors.email }}</div>
          </div>

          <!-- Home Club / Lodge Details -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Home Lodge / Club Name *</label>
              <input 
                v-model="form.home_club_name"
                type="text"
                required
                placeholder="e.g. Apollo Lodge"
                class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
              <div v-if="form.errors.home_club_name" class="text-rose-400 text-xs mt-1">{{ form.errors.home_club_name }}</div>
            </div>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Lodge No.</label>
              <input 
                v-model="form.home_club_number"
                type="text"
                placeholder="e.g. 357"
                class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          <!-- Phone Number -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Phone Number (Optional)</label>
            <input 
              v-model="form.phone"
              type="tel"
              placeholder="07700 900123"
              class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <!-- Dietary Notes -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Default Dietary Requirements (Optional)</label>
            <textarea 
              v-model="form.dietary_notes"
              rows="2"
              placeholder="e.g. Vegetarian, Gluten-free"
              class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
            ></textarea>
          </div>

          <!-- Submit Button -->
          <div class="pt-2">
            <button 
              type="submit"
              :disabled="form.processing"
              class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center gap-2 cursor-pointer"
            >
              <span v-if="form.processing">Registering...</span>
              <span v-else>📜 Register as Visitor</span>
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
</template>
