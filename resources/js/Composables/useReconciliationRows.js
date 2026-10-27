/**
 * Reconciling a bank line from the reconciliation screen: the per-row working state (which tab is open, who, what,
 * why), the "who" suggestions from the contacts, the quick reconcile from a row, and the manual match dialog.
 * `props` are the page's props; the rest are the page's own refs and form, which these actions read and set.
 * `contacts` is a function returning the contacts on offer (declared further down the page).
 */
export function useReconciliationRows(props, { rowStates, selectedTx, reconcileForm, showManualModal, manualMatchType, manualTargetId, manualNominalCode, contacts }) {
    const openManualMatchModal = () => {
      if (!selectedTx.value) return;
      manualMatchType.value = selectedTx.value.amount > 0 ? 'member_subscription' : 'supplier_bill';
      manualTargetId.value = '';
      manualNominalCode.value = '4000';
      showManualModal.value = true;
    };

    const submitReconcile = (matchType, targetId, nominalCode = 'GENERAL') => {
      if (!selectedTx.value) return;
      reconcileForm.transaction_id = selectedTx.value.id;
      reconcileForm.match_type = matchType;
      reconcileForm.target_id = targetId;
      reconcileForm.nominal_code = nominalCode;
      reconcileForm.post(route('admin.accounting.reconcile', props.club.slug), {
        preserveScroll: true,
        onSuccess: () => {
          showManualModal.value = false;
          const remaining = props.reconciliation?.unmatched_transactions?.filter(t => t.id !== selectedTx.value.id);
          selectedTx.value = remaining && remaining.length > 0 ? remaining[0] : null;
        },
      });
    };

    const getContactSuggestions = (query) => {
      const q = (query || '').trim().toLowerCase();
      if (q.length < 2) return [];
  
      const matches = (contacts() || []).filter(c =>
        c.name?.toLowerCase().includes(q) ||
        (c.email && c.email !== '—' && c.email.toLowerCase().includes(q))
      );

      const seen = new Set();
      const unique = [];
      for (const c of matches) {
        const key = c.name?.trim().toLowerCase();
        if (key && !seen.has(key)) {
          seen.add(key);
          unique.push(c);
        }
      }

      return unique.slice(0, 8);
    };

    const getRowState = (txId, tx) => {
      if (!rowStates.value[txId]) {
        const hasMatches = tx?.suggested_matches && tx.suggested_matches.length > 0;
    
        let autoWho = tx?.contact_name || '';
        if (!autoWho && tx?.raw_description && contacts()?.length > 0) {
          const descLower = tx.raw_description.toLowerCase();
          const match = contacts().find(c => c.name && c.name.length > 2 && descLower.includes(c.name.toLowerCase()));
          if (match) {
            autoWho = match.name;
          }
        }

        rowStates.value[txId] = {
          tab: hasMatches ? 'Match' : 'Create',
          who: autoWho,
          showWhoDropdown: false,
          what: '',
          showWhatError: false,
          why: tx?.raw_description || '',
          category: 'General',
          vat: 'No VAT',
          transfer_account: '',
          discuss_note: '',
        };
      }
      return rowStates.value[txId];
    };

    // A template cannot call setTimeout, so the blur handlers use this: close the dropdown a moment later, so a click on one of its options still lands.
    const closeWhoDropdownSoon = (tx) => setTimeout(() => { getRowState(tx.id, tx).showWhoDropdown = false; }, 200);

    const setRowTab = (txId, tabName) => {
      if (!rowStates.value[txId]) {
        getRowState(txId, null);
      }
      rowStates.value[txId].tab = tabName;
    };

    const openManualMatchModalForTx = (tx) => {
      selectedTx.value = tx;
      openManualMatchModal();
    };

    const submitRowReconcile = (tx) => {
      selectedTx.value = tx;
      const state = getRowState(tx.id, tx);
      state.showWhatError = false;

      if (state.tab === 'Create') {
        if (!state.what) {
          state.showWhatError = true;
          return;
        }
        reconcileForm.transaction_id = tx.id;
        reconcileForm.match_type = 'ledger_account';
        reconcileForm.target_id = 0;
        reconcileForm.nominal_code = state.what;
      } else if (state.tab === 'Match') {
        if (tx.suggested_matches && tx.suggested_matches.length > 0) {
          const match = tx.suggested_matches[0];
          reconcileForm.transaction_id = tx.id;
          reconcileForm.match_type = match.match_type;
          reconcileForm.target_id = match.target_id;
          reconcileForm.nominal_code = match.nominal_code || 'GENERAL';
        } else {
          if (!state.what) {
            state.showWhatError = true;
            return;
          }
          reconcileForm.transaction_id = tx.id;
          reconcileForm.match_type = 'ledger_account';
          reconcileForm.target_id = 0;
          reconcileForm.nominal_code = state.what;
        }
      } else if (state.tab === 'Transfer') {
        reconcileForm.transaction_id = tx.id;
        reconcileForm.match_type = 'ledger_account';
        reconcileForm.target_id = 0;
        reconcileForm.nominal_code = '1200';
      } else if (state.tab === 'Discuss') {
        return;
      }

      reconcileForm.post(route('admin.accounting.reconcile', props.club.slug), {
        preserveScroll: true,
      });
    };

    return { submitReconcile, getContactSuggestions, getRowState, closeWhoDropdownSoon, setRowTab, openManualMatchModalForTx, submitRowReconcile };
}
