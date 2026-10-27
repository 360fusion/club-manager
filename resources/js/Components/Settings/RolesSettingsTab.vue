<script setup>
// Roles tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
import Badge from '@/Components/Ui/Badge.vue';
import Card from '@/Components/Ui/Card.vue';

defineProps({
    form: { type: Object, required: true },
    isSuperAdmin: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    togglePermissionRole: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    updateMemberRole: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    visibleRoles: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    club: { type: [Object, Array, Function, String, Number, Boolean], required: false },
    members: { type: [Object, Array, Function, String, Number, Boolean], required: false }
});
</script>

<template>
    <div class="space-y-6">

      <!-- Role Permission Matrix Grid -->
      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Admin permissions</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Define which administrative actions each club role is allowed to perform.</p>
        </template>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-xs font-medium text-slate-500 dark:text-slate-400">
                <th class="py-3 px-4">Administrative action</th>
                <th v-for="r in visibleRoles" :key="r.code" class="py-3 px-3 text-center">
                  <Badge>{{ r.name }}</Badge>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
              <tr v-for="(perm, permKey) in form.permission_matrix" :key="permKey" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                <td class="py-3.5 px-4">
                  <div class="font-medium text-slate-900 dark:text-white">{{ perm.label }}</div>
                  <div class="text-xs text-slate-500 dark:text-slate-400">{{ perm.description }}</div>
                </td>

                <td v-for="r in visibleRoles" :key="r.code" class="py-3.5 px-3 text-center">
                  <input
                    type="checkbox"
                    :checked="perm.roles && perm.roles.includes(r.code)"
                    @change="togglePermissionRole(permKey, r.code)"
                    :disabled="r.code === 'owner'"
                    :aria-label="`${r.name}: ${perm.label}`"
                    class="h-4 w-4 rounded accent-blue-600 cursor-pointer disabled:opacity-50"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- Role Directory & Quick Assignment -->
      <Card>
        <template #header>
          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
              <h2 class="text-base font-semibold text-slate-900 dark:text-white">Member admin roles</h2>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Assign or change each member's admin permission level (e.g. who can edit the website or manage billing).</p>
            </div>
            <a
              :href="route('admin.officers.index', { clubSlug: club.slug })"
              class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline whitespace-nowrap"
            >
              Manage lodge offices
            </a>
          </div>
        </template>

        <div class="divide-y divide-slate-100 dark:divide-slate-800">
          <div v-for="m in members" :key="m.id" class="py-3 flex items-center justify-between gap-4 text-sm">
            <div class="overflow-hidden">
              <div class="font-medium text-slate-900 dark:text-white truncate">{{ m.name }}</div>
              <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ m.email }} • <span class="font-mono">{{ m.member_number }}</span></div>
            </div>

            <div class="flex items-center gap-2">
              <!-- Office is read-only here: it's owned by the annual officer roster, not this page. -->
              <span
                v-if="m.current_office_label"
                :class="['px-2.5 py-0.5 rounded-full border text-[11px] font-medium', m.current_office_badge_class]"
                :title="`${m.current_office_label} — set from the officer roster, not editable here`"
              >
                {{ m.current_office_label }}
              </span>

              <select
                :value="m.role"
                @change="updateMemberRole(m.id, $event.target.value)"
                :aria-label="`Admin role for ${m.name}`"
                class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 transition-colors cursor-pointer focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
              >
                <option value="member">Media manager</option>
                <option value="coach">Secretary</option>
                <option value="treasurer">Treasurer</option>
                <option value="examiner">Examiner or auditor</option>
                <option value="admin">Admin</option>
                <option v-if="isSuperAdmin || m.role === 'owner'" value="owner">Owner</option>
              </select>
            </div>
          </div>
        </div>
      </Card>

    </div>
</template>
