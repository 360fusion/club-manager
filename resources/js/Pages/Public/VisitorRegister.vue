<script setup>
import { useForm, Head } from '@inertiajs/vue3'
import Alert from '@/Components/Ui/Alert.vue'
import Button from '@/Components/Ui/Button.vue'
import Input from '@/Components/Ui/Input.vue'
import Textarea from '@/Components/Ui/Textarea.vue'
import UiIcon from '@/Components/Ui/UiIcon.vue'

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
  <Head :title="`Visitor sign-up — ${club.name}`" />

  <!-- A standalone dark page: the dark class makes the kit's fields and alerts draw their dark look here. -->
  <div class="dark min-h-screen bg-slate-900 text-slate-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 selection:bg-blue-500 selection:text-white">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
      <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-blue-600/20 text-blue-400 border border-blue-500/30 mb-4 shadow-xl">
        <UiIcon name="building" :size="20" />
      </div>
      <h1 class="text-2xl sm:text-3xl font-semibold text-white tracking-tight">
        Visiting brethren sign-up
      </h1>
      <p class="mt-2 text-sm text-slate-400">
        Register as a visitor for <span class="text-blue-400 font-medium">{{ club.name }}</span> to receive meeting summonses and RSVP invites.
      </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-xl px-4 sm:px-0">
      <div class="bg-slate-800/90 border border-slate-700/80 backdrop-blur-xl py-8 px-6 shadow-2xl rounded-2xl sm:px-10">

        <Alert v-if="$page.props.flash?.success" variant="success" title="Registration complete" class="mb-6">
          {{ $page.props.flash.success }}
        </Alert>

        <form @submit.prevent="submit" class="space-y-5">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Input v-model="form.rank" label="Rank or title" placeholder="e.g. WBro / Bro" />
            <Input v-model="form.name" label="Full name" required placeholder="e.g. John David Smith" :error="form.errors.name" class="sm:col-span-2" />
          </div>

          <Input
            v-model="form.email"
            type="email"
            label="Email address"
            required
            placeholder="visitor@example.com"
            hint="Summons announcements and passwordless RSVP links will be sent here."
            :error="form.errors.email"
          />

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Input v-model="form.home_club_name" label="Home lodge or club name" required placeholder="e.g. Apollo Lodge" :error="form.errors.home_club_name" class="sm:col-span-2" />
            <Input v-model="form.home_club_number" label="Lodge no." placeholder="e.g. 357" />
          </div>

          <Input v-model="form.phone" type="tel" label="Phone number (optional)" placeholder="07700 900123" />

          <Textarea v-model="form.dietary_notes" :rows="2" label="Default dietary requirements (optional)" placeholder="e.g. Vegetarian, Gluten-free" />

          <div class="pt-2">
            <Button type="submit" size="lg" block :disabled="form.processing">
              <span v-if="form.processing">Registering...</span>
              <span v-else>Register as a visitor</span>
            </Button>
          </div>
        </form>

      </div>
    </div>
  </div>
</template>
