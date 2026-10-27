<script setup>
import { Link } from '@inertiajs/vue3';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// A tab of the accounting screen, in its own file so the page stays readable.
const contactFilter = defineModel('contactFilter', { type: [String, Number, Boolean, Array, Object], default: undefined });

defineProps({
    canManageBilling: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    contacts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredContacts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    members: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <Card class="space-y-6">
      <div class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-center dark:border-slate-800">
        <div>
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Contacts and directory</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400">Manage billing contacts for individual persons, contractors, vendors, sponsors and club members.</p>
        </div>
        <Button v-if="canManageBilling" size="sm" class="shrink-0" :href="route('admin.accounting.contacts.create', club.slug)">
          <template #icon><UiIcon name="plus" /></template>
          Add contact
        </Button>
      </div>

      <!-- Filter chips -->
      <div class="flex items-center gap-2 overflow-x-auto border-b border-slate-200 pb-3 dark:border-slate-800">
        <button
          type="button"
          :aria-pressed="contactFilter === 'all'"
          :class="['cursor-pointer whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium transition-colors', contactFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700']"
          @click="contactFilter = 'all'"
        >
          All contacts ({{ filteredContacts.length }})
        </button>
        <button
          type="button"
          :aria-pressed="contactFilter === 'person'"
          :class="['inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium transition-colors', contactFilter === 'person' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700']"
          @click="contactFilter = 'person'"
        >
          <UiIcon name="user" />
          Persons and contractors
        </button>
        <button
          type="button"
          :aria-pressed="contactFilter === 'member'"
          :class="['inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium transition-colors', contactFilter === 'member' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700']"
          @click="contactFilter = 'member'"
        >
          <UiIcon name="users" />
          Club members
        </button>
      </div>

      <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800">
        <table class="w-full border-collapse text-left">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="px-4 py-3 font-medium">Name</th>
              <th scope="col" class="px-4 py-3 font-medium">Type</th>
              <th scope="col" class="px-4 py-3 font-medium">Role or category</th>
              <th scope="col" class="px-4 py-3 font-medium">Email and phone</th>
              <th scope="col" class="px-4 py-3 font-medium">Address and tax ID</th>
              <th scope="col" class="px-4 py-3 text-center font-medium">Quick action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-sm text-slate-700 dark:divide-slate-800 dark:text-slate-200">
            <tr v-if="!filteredContacts.length">
              <td colspan="6" class="py-8 text-center text-slate-500 dark:text-slate-400">No contacts match the selected filter. Use "Add contact" to add your first contact.</td>
            </tr>
            <tr v-for="c in filteredContacts" :key="c.id" class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/80">
              <td class="px-4 py-3">
                <div class="flex items-center gap-2.5">
                  <span class="inline-flex rounded-xl border border-slate-200 bg-slate-100 p-1.5 text-slate-500 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-400"><UiIcon name="user" /></span>
                  <div>
                    <Link
                      v-if="c.kind === 'contact'"
                      :href="route('admin.accounting.contacts.edit', [club.slug, c.contact_db_id])"
                      class="block cursor-pointer text-left font-medium text-slate-900 transition-colors hover:text-blue-700 hover:underline dark:text-white dark:hover:text-blue-300"
                      title="Edit the contact's details"
                    >
                      {{ c.name }}
                    </Link>
                    <Link
                      v-else-if="c.kind === 'member' && c.user_id"
                      :href="route('admin.accounting.contacts.member.edit', [club.slug, c.user_id])"
                      class="block cursor-pointer text-left font-medium text-slate-900 transition-colors hover:text-blue-700 hover:underline dark:text-white dark:hover:text-blue-300"
                      title="Edit the member's accounting details"
                    >
                      {{ c.name }}
                    </Link>
                    <span v-else class="block font-medium text-slate-900 dark:text-white">{{ c.name }}</span>
                    <span v-if="c.contact_person && c.contact_person !== c.name" class="block text-xs text-slate-500 dark:text-slate-400">
                      Alt: {{ c.contact_person }}
                    </span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <Badge variant="info">{{ c.kind === 'member' ? 'Member' : 'Person' }}</Badge>
              </td>
              <td class="px-4 py-3">
                <Badge>{{ c.role }}</Badge>
              </td>
              <td class="px-4 py-3">
                <span class="block font-mono text-slate-700 dark:text-slate-200">{{ c.email }}</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ c.phone }}</span>
              </td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                <span class="block text-xs">{{ c.address }}</span>
                <span v-if="c.tax_id && c.tax_id !== '—'" class="block font-mono text-xs text-slate-500 dark:text-slate-400">Tax ID: {{ c.tax_id }}</span>
              </td>
              <td class="px-4 py-3 text-center">
                <Button
                  v-if="c.kind === 'member'"
                  variant="secondary"
                  size="sm"
                  :href="route('admin.accounting.invoices.create', { clubSlug: club.slug, user: c.user_id })"
                >
                  Issue invoice
                </Button>
                <div v-else class="flex items-center justify-center gap-1.5">
                  <Button
                    variant="secondary"
                    size="sm"
                    :href="route('admin.accounting.contacts.edit', [club.slug, c.contact_db_id])"
                    title="Edit the contact"
                  >
                    Edit
                  </Button>
                  <Button
                    variant="secondary"
                    size="sm"
                    :href="route('admin.accounting.bills.create', { clubSlug: club.slug, vendor: c.name })"
                  >
                    Record bill
                  </Button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>
</template>
