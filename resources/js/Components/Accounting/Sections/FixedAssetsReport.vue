<script setup>
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
          <h3 class="text-xl font-black text-slate-900 dark:text-white">Fixed Asset Register</h3>
          <p class="text-xs text-slate-600 dark:text-slate-300 font-semibold mt-0.5">Cost, accumulated depreciation and net book value.</p>
        </div>
        <div v-if="canManageBilling" class="flex items-center gap-2">
          <input v-model="depreciationForm.period" placeholder="Period e.g. 2026" class="w-28 px-3 py-2 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl" />
          <button type="button" @click="runDepreciation" class="px-3 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl transition-all cursor-pointer">
            📉 Run Depreciation
          </button>
          <button type="button" @click="showAddAssetForm = !showAddAssetForm" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl transition-all cursor-pointer">
            ➕ Add Asset
          </button>
        </div>
      </div>

      <form v-if="canManageBilling && showAddAssetForm" @submit.prevent="submitFixedAsset" class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <input v-model="fixedAssetForm.name" placeholder="Asset name (e.g. Racing Eight)" required class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl sm:col-span-2" />
        <input v-model="fixedAssetForm.category" placeholder="Category" class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl" />
        <input v-model="fixedAssetForm.purchase_date" type="date" required class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl" />
        <input v-model="fixedAssetForm.purchase_cost" type="number" step="0.01" min="0" placeholder="Purchase cost" required class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl" />
        <select v-model="fixedAssetForm.depreciation_method" class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl">
          <option value="straight_line">Straight Line</option>
          <option value="reducing_balance">Reducing Balance</option>
          <option value="none">No Depreciation</option>
        </select>
        <input v-model="fixedAssetForm.useful_life_years" type="number" min="1" placeholder="Useful life (years)" class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl" />
        <input v-model="fixedAssetForm.salvage_value" type="number" step="0.01" min="0" placeholder="Salvage value" class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl" />
        <button type="submit" :disabled="fixedAssetForm.processing" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl transition-all cursor-pointer disabled:opacity-50 sm:col-span-3">
          Register Asset
        </button>
      </form>

      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase text-[10px]">
            <tr>
              <th class="py-2 px-3">Asset</th>
              <th class="py-2 px-3">Category</th>
              <th class="py-2 px-3">Purchased</th>
              <th class="py-2 px-3 text-right">Cost</th>
              <th class="py-2 px-3 text-right">Acc. Depreciation</th>
              <th class="py-2 px-3 text-right">Net Book Value</th>
              <th class="py-2 px-3 text-center">Status</th>
              <th class="py-2 px-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-800 dark:text-slate-100 bg-white dark:bg-slate-900">
            <template v-for="asset in fixedAssets" :key="asset.id">
              <tr>
                <td class="py-2 px-3 font-bold text-slate-900 dark:text-white">{{ asset.name }}</td>
                <td class="py-2 px-3">{{ asset.category }}</td>
                <td class="py-2 px-3 font-mono">{{ asset.purchase_date }}</td>
                <td class="py-2 px-3 text-right font-mono">{{ asset.formatted_purchase_cost }}</td>
                <td class="py-2 px-3 text-right font-mono">{{ asset.formatted_accumulated_depreciation }}</td>
                <td class="py-2 px-3 text-right font-mono font-bold">{{ asset.formatted_net_book_value }}</td>
                <td class="py-2 px-3 text-center">
                  <span v-if="asset.is_disposed" class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 text-[10px] font-black uppercase">Disposed {{ asset.disposal_date }}</span>
                  <span v-else class="px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-[10px] font-black uppercase">Active</span>
                </td>
                <td class="py-2 px-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button v-if="canManageBilling && !asset.is_disposed" type="button" @click="disposingAssetId = disposingAssetId === asset.id ? null : asset.id" class="text-[10px] font-extrabold text-rose-700 dark:text-rose-300 hover:underline cursor-pointer">
                      Dispose
                    </button>
                    <button
                      type="button"
                      @click="openActivityLog('fixed_asset', asset.id, `Asset: ${asset.name}`)"
                      title="View Activity Log"
                      class="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 text-[10px] font-extrabold rounded-lg transition-all cursor-pointer"
                    >
                      🕘
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="disposingAssetId === asset.id">
                <td colspan="8" class="py-3 px-3 bg-rose-50/50 dark:bg-rose-950/20">
                  <form @submit.prevent="disposeAsset(asset.id)" class="flex flex-wrap items-end gap-2">
                    <div>
                      <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-300">Disposal date</label>
                      <input v-model="disposalForm.disposal_date" type="date" required class="px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg" />
                    </div>
                    <div>
                      <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-300">Proceeds</label>
                      <input v-model="disposalForm.disposal_proceeds" type="number" step="0.01" min="0" class="px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg" />
                    </div>
                    <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-extrabold rounded-lg cursor-pointer">Confirm Disposal</button>
                  </form>
                </td>
              </tr>
            </template>
            <tr v-if="!fixedAssets.length">
              <td colspan="8" class="py-4 px-3 text-center text-slate-400 italic">No fixed assets registered yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
