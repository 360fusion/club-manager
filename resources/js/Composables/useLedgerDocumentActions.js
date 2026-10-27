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

    const markInvoicePaid = (id) => {
      if (confirm('Mark this invoice as paid? This will automatically deposit funds to Operating Bank Account.')) {
        router.post(route('admin.accounting.invoices.pay', { clubSlug: club.slug, id }));
      }
    };

    const markBillPaid = (id) => {
      if (confirm('Mark this vendor bill as paid? This will automatically clear Accounts Payable.')) {
        router.post(route('admin.accounting.bills.pay', { clubSlug: club.slug, id }));
      }
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

    return { publishInvoice, publishBill, openEditInvoiceModal, openEditBillModal, activityModal, openActivityLog, markInvoicePaid, markBillPaid, forgetMatchRule, approveInvoice, approveBill };
}
