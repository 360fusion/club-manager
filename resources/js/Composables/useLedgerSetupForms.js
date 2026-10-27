import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { localDate } from '@/Utils/localDateTime';

/**
 * The small posting forms on the accounting screen: opening balances, adding a ledger account and importing a bank
 * statement. `props` are the page's props; `showAccountModal` and `showImportModal` are the page's own refs, because
 * other buttons on the page open those two dialogs.
 */
export function useLedgerSetupForms(props, { showAccountModal, showImportModal }) {
    const importForm = useForm({
      statement_file: null,
    });

    const submitStatementImport = () => {
      importForm.post(route('admin.accounting.import_statement', props.club.slug), {
        preserveScroll: true,
        onSuccess: () => {
          importForm.reset('statement_file');
          showImportModal.value = false;
        },
      });
    };

    const showOpeningBalanceModal = ref(false);

    const openingBalanceForm = useForm({
      account_id: '',
      opening_balance: '',
      as_of_date: localDate(),
    });

    const openOpeningBalanceModal = (accId = '') => {
      openingBalanceForm.reset();
      openingBalanceForm.account_id = accId || (props.accounts[0]?.id || '');
      openingBalanceForm.as_of_date = localDate();
      showOpeningBalanceModal.value = true;
    };

    const submitOpeningBalance = () => {
      openingBalanceForm.post(route('admin.accounting.opening_balance.store', props.club.slug), {
        onSuccess: () => {
          showOpeningBalanceModal.value = false;
          openingBalanceForm.reset();
        },
      });
    };

    const accountForm = useForm({
      code: '',
      name: '',
      type: 'asset',
      opening_balance: '',
      as_of_date: localDate(),
    });

    const submitAccount = () => {
      accountForm.post(route('admin.accounting.accounts.store', props.club.slug), {
        onSuccess: () => {
          showAccountModal.value = false;
          accountForm.reset();
        },
      });
    };

    return { showOpeningBalanceModal, openingBalanceForm, openOpeningBalanceModal, submitOpeningBalance, accountForm, submitAccount, importForm, submitStatementImport };
}
