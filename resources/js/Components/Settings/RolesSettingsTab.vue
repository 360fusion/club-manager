<script setup>
// Roles tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
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
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">🛡️ Granular Admin Permission Matrix</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Define which administrative actions each club role is allowed to perform.</p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-[11px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                <th class="py-3 px-4">Administrative Action</th>
                <th v-for="r in visibleRoles" :key="r.code" class="py-3 px-3 text-center">
                  <span :class="['px-2 py-0.5 rounded text-[10px] font-extrabold border', r.badge]">
                    {{ r.name }}
                  </span>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
              <tr v-for="(perm, permKey) in form.permission_matrix" :key="permKey" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                <td class="py-3.5 px-4">
                  <div class="font-bold text-slate-900 dark:text-white">{{ perm.label }}</div>
                  <div class="text-[11px] text-slate-400">{{ perm.description }}</div>
                </td>

                <td v-for="r in visibleRoles" :key="r.code" class="py-3.5 px-3 text-center">
                  <input
                    type="checkbox"
                    :checked="perm.roles && perm.roles.includes(r.code)"
                    @change="togglePermissionRole(permKey, r.code)"
                    :disabled="r.code === 'owner'"
                    class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 cursor-pointer disabled:opacity-50"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Role Directory & Quick Assignment -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">👥 Member Administrative Role Directory</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Assign or change each member's admin permission level (e.g. who can edit the website or manage billing).</p>
          </div>
          <a
            :href="route('admin.officers.index', { clubSlug: club.slug })"
            class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline whitespace-nowrap"
          >
            Manage lodge offices →
          </a>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-slate-800">
          <div v-for="m in members" :key="m.id" class="py-3 flex items-center justify-between gap-4 text-xs">
            <div class="overflow-hidden">
              <div class="font-bold text-slate-900 dark:text-white truncate">{{ m.name }}</div>
              <div class="text-[11px] text-slate-400 truncate">{{ m.email }} • <span class="font-mono">{{ m.member_number }}</span></div>
            </div>

            <div class="flex items-center gap-2">
              <!-- Office is read-only here: it's owned by the annual officer roster, not this page. -->
              <span
                v-if="m.current_office_label"
                :class="['px-2.5 py-1 rounded-xl border text-[11px]', m.current_office_badge_class]"
                :title="`${m.current_office_label} — set from the officer roster, not editable here`"
              >
                {{ m.current_office_label }}
              </span>

              <select
                :value="m.role"
                @change="updateMemberRole(m.id, $event.target.value)"
                class="px-3 py-1.5 font-bold rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-xs cursor-pointer outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="member">Media Manager</option>
                <option value="coach">Secretary</option>
                <option value="treasurer">Treasurer</option>
                <option value="examiner">Examiner / Auditor</option>
                <option value="admin">Admin</option>
                <option v-if="isSuperAdmin || m.role === 'owner'" value="owner">Owner</option>
              </select>
            </div>
          </div>
        </div>
      </div>

    </div>
</template>
