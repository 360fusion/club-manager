<script setup>
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { localDate } from '@/Utils/localDateTime';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

// The fixed_assets section of Index.vue, in its own file so the page stays readable. State only this section uses lives here.
const props = defineProps({
    canManageBilling: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    depreciationForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    fixedAssets: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openActivityLog: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    runDepreciation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const showAddAssetForm = ref(false);

const fixedAssetForm = useForm({
  name: '',
  category: 'Equipment',
  purchase_date: localDate(),
  purchase_cost: '',
  depreciation_method: 'straight_line',
  useful_life_years: 5,
  salvage_value: 0,
  notes: '',
});

const submitFixedAsset = () => {
  fixedAssetForm.post(route('admin.accounting.fixed_assets.store', props.club.slug), {
    preserveScroll: true,
    onSuccess: () => {
      fixedAssetForm.reset();
      showAddAssetForm.value = false;
    },
  });
};

const depreciationMethodOptions = [
  { value: 'straight_line', label: 'Straight line' },
  { value: 'reducing_balance', label: 'Reducing balance' },
  { value: 'none', label: 'No depreciation' },
];

const disposalForm = useForm({ disposal_date: localDate(), disposal_proceeds: 0 });

const disposingAssetId = ref(null);

const disposeAsset = (assetId) => {
  disposalForm.post(route('admin.accounting.fixed_assets.dispose', { clubSlug: props.club.slug, id: assetId }), {
    preserveScroll: true,
    onSuccess: () => { disposingAssetId.value = null; },
  });
};
</script>

<template>
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 dark:bg-slate-800/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-800">
        <div>
          <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Fixed asset register</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Cost, accumulated depreciation and net book value.</p>
        </div>
        <div v-if="canManageBilling" class="flex flex-wrap items-center gap-2">
          <Input v-model="depreciationForm.period" placeholder="Period e.g. 2026" aria-label="Depreciation period" class="w-36" />
          <Button variant="secondary" @click="runDepreciation">
            <template #icon><UiIcon name="chart" /></template>
            Run depreciation
          </Button>
          <Button @click="showAddAssetForm = !showAddAssetForm">
            <template #icon><UiIcon name="plus" /></template>
            Add asset
          </Button>
        </div>
      </div>

      <form v-if="canManageBilling && showAddAssetForm" @submit.prevent="submitFixedAsset" class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-3">
        <Input v-model="fixedAssetForm.name" label="Asset name" placeholder="e.g. racing eight" required class="sm:col-span-2" />
        <Input v-model="fixedAssetForm.category" label="Category" placeholder="Category" />
        <Input v-model="fixedAssetForm.purchase_date" type="date" label="Purchase date" required />
        <Input v-model="fixedAssetForm.purchase_cost" type="number" step="0.01" min="0" label="Purchase cost" placeholder="Purchase cost" required />
        <Select v-model="fixedAssetForm.depreciation_method" label="Depreciation method" :options="depreciationMethodOptions" />
        <Input v-model="fixedAssetForm.useful_life_years" type="number" min="1" label="Useful life (years)" placeholder="Useful life (years)" />
        <Input v-model="fixedAssetForm.salvage_value" type="number" step="0.01" min="0" label="Salvage value" placeholder="Salvage value" />
        <Button type="submit" :disabled="fixedAssetForm.processing" class="sm:col-span-3">
          Register asset
        </Button>
      </form>

      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="py-3 px-3 font-medium">Asset</th>
              <th scope="col" class="py-3 px-3 font-medium">Category</th>
              <th scope="col" class="py-3 px-3 font-medium">Purchased</th>
              <th scope="col" class="py-3 px-3 font-medium text-right">Cost</th>
              <th scope="col" class="py-3 px-3 font-medium text-right">Acc. depreciation</th>
              <th scope="col" class="py-3 px-3 font-medium text-right">Net book value</th>
              <th scope="col" class="py-3 px-3 font-medium text-center">Status</th>
              <th scope="col" class="py-3 px-3"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900">
            <template v-for="asset in fixedAssets" :key="asset.id">
              <tr>
                <td class="py-2 px-3 font-medium text-slate-900 dark:text-white">{{ asset.name }}</td>
                <td class="py-2 px-3">{{ asset.category }}</td>
                <td class="py-2 px-3 tabular-nums">{{ asset.purchase_date }}</td>
                <td class="py-2 px-3 text-right tabular-nums">{{ asset.formatted_purchase_cost }}</td>
                <td class="py-2 px-3 text-right tabular-nums">{{ asset.formatted_accumulated_depreciation }}</td>
                <td class="py-2 px-3 text-right tabular-nums font-semibold text-slate-900 dark:text-white">{{ asset.formatted_net_book_value }}</td>
                <td class="py-2 px-3 text-center">
                  <Badge v-if="asset.is_disposed">Disposed {{ asset.disposal_date }}</Badge>
                  <Badge v-else variant="success">Active</Badge>
                </td>
                <td class="py-2 px-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button v-if="canManageBilling && !asset.is_disposed" type="button" @click="disposingAssetId = disposingAssetId === asset.id ? null : asset.id" class="text-xs font-medium text-rose-700 dark:text-rose-400 hover:underline">
                      Dispose
                    </button>
                    <button
                      type="button"
                      @click="openActivityLog('fixed_asset', asset.id, `Asset: ${asset.name}`)"
                      title="View activity log"
                      aria-label="View activity log"
                      class="rounded-xl p-1.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                      <UiIcon name="clock" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="disposingAssetId === asset.id">
                <td colspan="8" class="py-3 px-3 bg-rose-50/50 dark:bg-rose-950/20">
                  <form @submit.prevent="disposeAsset(asset.id)" class="flex flex-wrap items-end gap-2">
                    <Input v-model="disposalForm.disposal_date" type="date" label="Disposal date" required />
                    <Input v-model="disposalForm.disposal_proceeds" type="number" step="0.01" min="0" label="Proceeds" />
                    <Button type="submit" variant="danger">Confirm disposal</Button>
                  </form>
                </td>
              </tr>
            </template>
            <tr v-if="!fixedAssets.length">
              <td colspan="8" class="py-4 px-3 text-center text-slate-500 dark:text-slate-400">No fixed assets registered yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
