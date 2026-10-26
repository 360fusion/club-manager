<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';

const props = defineProps({
  club: Object,
  types: Array,
});

const typeToDelete = ref(null);
const deleteType = () => {
  router.delete(route('admin.newsletters.types.destroy', { clubSlug: props.club.slug, id: typeToDelete.value.id }), {
    onFinish: () => { typeToDelete.value = null; },
  });
};
</script>

<template>
  <AdminLayout title="Newsletter channels and settings" :club="club" active-tab="newsletters">
    <Head title="Newsletter channels and settings" />

    <div class="space-y-6">
      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <Button variant="ghost" size="sm" :href="route('admin.newsletters.index', club.slug)">Back to newsletters</Button>
            <h2 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Newsletter channels and settings</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Manage the kinds of email you send (summonses, news, social bulletins) and whether brethren from other lodges can subscribe, with or without approval.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Button variant="secondary" size="sm" :href="route('admin.newsletters.subscribers', club.slug)">Manage subscribers</Button>
            <Button size="sm" :href="route('admin.newsletters.types.create', club.slug)">Create a channel</Button>
          </div>
        </div>
      </Card>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <Card v-for="type in types" :key="type.id" class="flex flex-col justify-between gap-4">
          <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-1">
              <Badge v-if="type.is_mandatory" variant="info">Mandatory notice</Badge>
              <Badge v-if="type.is_external_subscribable" variant="success">Visitors can subscribe</Badge>
              <Badge v-else>Members only</Badge>
            </div>

            <div>
              <h3 class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-white">
                <span :style="{ backgroundColor: type.color }" class="inline-block h-3 w-3 rounded-full" aria-hidden="true"></span>
                <span>{{ type.icon }} {{ type.name }}</span>
              </h3>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ type.description || 'No description.' }}</p>
            </div>

            <dl class="grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
              <div class="rounded-xl bg-slate-50 p-2 dark:bg-slate-800/50">
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Subscribers</dt>
                <dd class="text-base font-semibold text-slate-900 dark:text-white">{{ type.total_subscribers || 0 }}</dd>
              </div>
              <div class="rounded-xl bg-slate-50 p-2 dark:bg-slate-800/50">
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Visiting brethren</dt>
                <dd class="text-base font-semibold text-slate-900 dark:text-white">{{ type.external_subscribers || 0 }}</dd>
              </div>
            </dl>
          </div>

          <div class="flex items-center justify-between gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
            <Button variant="secondary" size="sm" :href="route('admin.newsletters.types.edit', { clubSlug: club.slug, id: type.id })">Configure channel</Button>
            <Button variant="ghost" size="sm" :aria-label="`Delete ${type.name}`" @click="typeToDelete = type">Delete</Button>
          </div>
        </Card>
      </div>
    </div>

    <ConfirmDialog :open="Boolean(typeToDelete)" title="Delete this channel?" confirm-label="Delete channel" @confirm="deleteType" @close="typeToDelete = null">
      The {{ typeToDelete?.name }} channel will be deleted. Its existing newsletters will stay.
    </ConfirmDialog>
  </AdminLayout>
</template>
