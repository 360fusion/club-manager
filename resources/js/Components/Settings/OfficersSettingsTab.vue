<script setup>
// Officers tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
import Badge from '@/Components/Ui/Badge.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';

defineProps({
    form: { type: Object, required: true },
    club: { type: [Object, Array, Function, String, Number, Boolean], required: false }
});
</script>

<template>
    <div class="space-y-6">

      <!-- Provincial Executive Card -->
      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Provincial executive and rulers</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Configure annual Provincial Grand Lodge rulers and executive officers. These settings automatically populate new meeting summonses.</p>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Input v-model="form.provincial_name" placeholder="Provincial Grand Lodge of Durham" label="Provincial title or name" />
          <Input v-model="form.provincial_grand_master" placeholder="R WBro John David Watts" label="Provincial Grand Master" />
          <Input v-model="form.deputy_provincial_grand_master" placeholder="WBro Andrew Peter Faul Foster PSGD" label="Deputy Provincial Grand Master" />
          <Textarea v-model="form.assistant_provincial_grand_masters" :rows="4" placeholder="WBro Dr. Rakesh Bhalla PSGD..." label="Assistant Provincial Grand Masters (one per line)" />
        </div>
      </Card>

      <!-- Annual Officers Roster Managed Card -->
      <Card>
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Lodge governance</span>
              <Badge variant="info">Active domain</Badge>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Annual officer rosters and history</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">
              Lodge Officer rosters, multi-year installation dates, progressive ladder seats, and committee approvals are now managed centrally in the Member Directory.
            </p>
          </div>
          <a
            :href="route('admin.officers.index', { clubSlug: club.slug })"
            class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            Manage officers roster
          </a>
        </div>
      </Card>

    </div>
</template>
