import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * What the accounting screen does to one invoice or bill, or to a remembered bank match: issue, approve, mark paid,
 * open the edit page, forget a match rule, and the activity log pop-out. `club` is the page's club, whose slug the routes need.
 */
export function useLedgerDocumentActions(club) {
    const publishInvoice = (id) => {
      if (confirm('Are you sure you want to issue/publish this invoice? It will post to Accounts Receivable.')) {
        router.post(route('admin.accounting.invoices.publish', { clubSlug: club.slug, id }));
      }
    };

    const publishBill = (id) => {
      if (confirm('Are you sure you want to approve and post this vendor bill to Accounts Payable?')) {
        router.post(route('admin.accounting.bills.publish', { clubSlug: club.slug, id }));
      }
    };

    const openEditInvoiceModal = (inv) => {
      if (!inv) return;
      router.visit(route('admin.accounting.invoices.edit', { clubSlug: club.slug, id: inv.id }));
    };

    const openEditBillModal = (bill) => {
      if (!bill) return;
      router.visit(route('admin.accounting.bills.edit', { clubSlug: club.slug, id: bill.id }));
    };

    const activityModal = ref({
      show: false,
      label: '',
      loading: false,
      entries: [],
    });

    const openActivityLog = async (entityType, entityId, label) => {
      activityModal.value = { show: true, label, loading: true, entries: [] };
      try {
        const res = await fetch(route('admin.accounting.activity_log', { clubSlug: club.slug, entityType, entityId }));
        if (res.ok) {
          const data = await res.json();
          activityModal.value.entries = data.entries || [];
        }
      } finally {
        activityModal.value.loading = false;
      }
    };

    // Marking paid asks which bank or cash account the money went through and on what day, so the ledger matches the bank.
    const payDialog = ref({ show: false, kind: 'invoice', id: null, paid_on: '', bank_account_id: null, processing: false });
    const today = () => new Date().toISOString().slice(0, 10);
    const openPayDialog = (kind, id) => {
      payDialog.value = { show: true, kind, id, paid_on: today(), bank_account_id: null, processing: false };
    };
    const markInvoicePaid = (id) => openPayDialog('invoice', id);
    const markBillPaid = (id) => openPayDialog('bill', id);
    const submitPayDialog = () => {
      const { kind, id, paid_on, bank_account_id } = payDialog.value;
      payDialog.value.processing = true;
      router.post(route(kind === 'bill' ? 'admin.accounting.bills.pay' : 'admin.accounting.invoices.pay', { clubSlug: club.slug, id }), { paid_on, bank_account_id }, {
        preserveScroll: true,
        onSuccess: () => { payDialog.value.show = false; },
        onFinish: () => { payDialog.value.processing = false; },
      });
    };

    const forgetMatchRule = (id) => {
      if (confirm('Forget this remembered match? That payee will be matched from scratch next time.')) {
        router.delete(route('admin.accounting.match_rules.destroy', { clubSlug: club.slug, id }), { preserveScroll: true });
      }
    };

    const approveInvoice = (id) => {
      if (confirm('Approve this invoice? It will be posted to Accounts Receivable.')) {
        router.post(route('admin.accounting.invoices.approve', { clubSlug: club.slug, id }));
      }
    };

    const approveBill = (id) => {
      if (confirm('Approve this vendor bill? It will be posted to Accounts Payable.')) {
        router.post(route('admin.accounting.bills.approve', { clubSlug: club.slug, id }));
      }
    };

    return { publishInvoice, publishBill, openEditInvoiceModal, openEditBillModal, activityModal, openActivityLog, markInvoicePaid, markBillPaid, payDialog, submitPayDialog, forgetMatchRule, approveInvoice, approveBill };
}
