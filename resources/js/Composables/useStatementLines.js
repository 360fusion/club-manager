import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * The bank statement lines list on the reconciliation screen: which lines are on show (statement lines or deleted ones,
 * for the chosen bank account), the selection, and deleting or restoring lines. `props` are the page's props,
 * `activeBankAccount` the chosen account and `activeOptionsTxId` the row whose options menu is open.
 */
export function useStatementLines(props, { activeBankAccount, activeOptionsTxId }) {
    const selectedStatementLineIds = ref([]);
    const statementLinesFilter = ref('statement_lines');

    const filteredStatementLines = computed(() => {
      let list = props.reconciliation?.statement_lines || [];
      if (activeBankAccount.value) {
        list = list.filter(l => !l.bank_account_id || l.bank_account_id === activeBankAccount.value.id);
      }
      if (statementLinesFilter.value === 'statement_lines') {
        return list.filter(l => l.status !== 'Deleted');
      } else if (statementLinesFilter.value === 'deleted') {
        return list.filter(l => l.status === 'Deleted');
      }
      return list;
    });

    const selectedStatementLines = computed(() => {
      const allLines = props.reconciliation?.statement_lines || [];
      return allLines.filter(l => selectedStatementLineIds.value.includes(l.id));
    });

    const deletableStatementLines = computed(() => {
      return selectedStatementLines.value.filter(l => l.status !== 'Deleted');
    });

    const restorableStatementLines = computed(() => {
      return selectedStatementLines.value.filter(l => l.status === 'Deleted');
    });

    const toggleStatementLinesSelectAll = () => {
      if (selectedStatementLineIds.value.length === filteredStatementLines.value.length) {
        selectedStatementLineIds.value = [];
      } else {
        selectedStatementLineIds.value = filteredStatementLines.value.map(l => l.id);
      }
    };

    const deleteSelectedStatementLines = () => {
      const ids = deletableStatementLines.value.map(l => l.id);
      if (ids.length === 0) return;
      router.post(route('admin.accounting.statement_lines.delete', props.club.slug), {
        transaction_ids: ids,
      }, {
        preserveScroll: true,
        onSuccess: () => {
          selectedStatementLineIds.value = [];
        }
      });
    };

    const restoreSelectedStatementLines = () => {
      const ids = restorableStatementLines.value.map(l => l.id);
      if (ids.length === 0) return;
      router.post(route('admin.accounting.statement_lines.restore', props.club.slug), {
        transaction_ids: ids,
      }, {
        preserveScroll: true,
        onSuccess: () => {
          selectedStatementLineIds.value = [];
        }
      });
    };

    const toggleOptionsDropdown = (txId) => {
      if (activeOptionsTxId.value === txId) {
        activeOptionsTxId.value = null;
      } else {
        activeOptionsTxId.value = txId;
      }
    };

    const deleteSingleStatementLine = (txId) => {
      activeOptionsTxId.value = null;
      if (!confirm('Are you sure you want to delete this statement line?')) return;
      router.post(route('admin.accounting.statement_lines.delete', props.club.slug), {
        transaction_ids: [txId],
      }, {
        preserveScroll: true,
      });
    };

    return { selectedStatementLineIds, statementLinesFilter, filteredStatementLines, deletableStatementLines, restorableStatementLines, toggleStatementLinesSelectAll, deleteSelectedStatementLines, restoreSelectedStatementLines, toggleOptionsDropdown, deleteSingleStatementLine };
}
