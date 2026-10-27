import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

/**
 * The accounting screen's settings and year-end forms: VAT, financial year end, charity commission, independent
 * examiner, approval threshold, depreciation run, budget, closing a year and the auditors' sign-off, plus the recurring
 * bill schedule actions. `props` are the page's props, which these forms start from and re-read as the server refreshes them.
 */
export function useAccountingSettingsForms(props) {
    const vatSettingsForm = useForm({
      enabled: props.vatSettings.enabled,
      scheme: props.vatSettings.scheme,
      vat_number: props.vatSettings.vat_number || '',
      flat_rate_percent: props.vatSettings.flat_rate_percent,
      default_rate: props.vatSettings.default_rate,
      registered_from: props.vatSettings.registered_from,
    });

    const submitVatSettings = () => {
      vatSettingsForm.post(route('admin.accounting.vat_settings.update', props.club.slug), {
        preserveScroll: true,
      });
    };

    // Financial Year End Form
    const financialYearEndForm = useForm({
      financial_year_end_month: props.settings.financial_year_end_month || 4,
    });

    const submitFinancialYearEnd = () => {
      financialYearEndForm.post(route('admin.accounting.financial_year_end.update', props.club.slug), {
        preserveScroll: true,
      });
    };

    // Charity Commission Form
    const charityCommissionForm = useForm({
      registered: props.charityCommission.registered,
      charity_number: props.charityCommission.charity_number || '',
    });

    const submitCharityCommission = () => {
      charityCommissionForm.post(route('admin.accounting.charity_commission.update', props.club.slug), {
        preserveScroll: true,
      });
    };

    // Independent Examiner's Report
    const examinerForm = useForm({ financial_year: props.independentExaminer.financial_year, examiner_user_id: '', notes: '' });

    const submitExaminerRequest = () => {
      examinerForm.financial_year = props.independentExaminer.financial_year;
      examinerForm.post(route('admin.accounting.independent_examiner.request', props.club.slug), { preserveScroll: true });
    };

    const resendExaminerSignature = () => {
      router.post(route('admin.accounting.independent_examiner.resend', props.club.slug), { financial_year: props.independentExaminer.financial_year }, { preserveScroll: true });
    };

    const changeExaminerYear = (year) => {
      router.get(route('admin.accounting.index', { clubSlug: props.club.slug, tab: 'reporting', report: 'independent_examiner' }), { treasurer_report_year: year }, { preserveState: true, preserveScroll: true });
    };

    // Approval Threshold Form
    const approvalThresholdForm = useForm({
      enabled: props.approvalThreshold.enabled,
      amount: props.approvalThreshold.amount,
    });

    const submitApprovalThreshold = () => {
      approvalThresholdForm.post(route('admin.accounting.approval_threshold.update', props.club.slug), {
        preserveScroll: true,
      });
    };

    // Fixed Asset Register

    const depreciationForm = useForm({ period: String(new Date().getFullYear()) });
    const runDepreciation = () => {
      if (!confirm(`Run depreciation for period ${depreciationForm.period} across all active assets?`)) return;
      depreciationForm.post(route('admin.accounting.fixed_assets.run_depreciation', props.club.slug), { preserveScroll: true });
    };

    // Budget vs Actual
    const budgetForm = useForm({
      financial_year: props.budgetVsActual.financial_year,
      lines: {},
    });
    watch(() => props.budgetVsActual, (data) => {
      budgetForm.financial_year = data.financial_year;
      const lines = {};
      (data.rows || []).forEach(r => { lines[r.account_id] = r.budgeted; });
      budgetForm.lines = lines;
    }, { immediate: true });

    const budgetableAccounts = computed(() => (props.accounts || []).filter(a => a.type === 'revenue' || a.type === 'expense'));

    const changeBudgetYear = (year) => {
      router.get(route('admin.accounting.index', { clubSlug: props.club.slug, tab: 'reporting', report: 'budget_vs_actual' }), { budget_year: year }, { preserveState: true, preserveScroll: true });
    };

    const submitBudget = () => {
      const lines = Object.entries(budgetForm.lines).map(([account_id, budgeted_amount]) => ({ account_id: Number(account_id), budgeted_amount: Number(budgeted_amount) || 0 }));
      budgetForm.transform(() => ({ financial_year: budgetForm.financial_year, lines })).post(route('admin.accounting.budget.update', props.club.slug), {
        preserveScroll: true,
      });
    };

    // Annual Treasurer's Report — financial year close & auditor sign-off
    const changeTreasurerReportYear = (year) => {
      router.get(route('admin.accounting.index', { clubSlug: props.club.slug, tab: 'reporting', report: 'treasurer_report' }), { treasurer_report_year: year }, { preserveState: true, preserveScroll: true });
    };

    const closeYearForm = useForm({ financial_year: props.annualTreasurerReport.financial_year, notes: '' });
    const submitCloseYear = () => {
      if (!confirm(`Close the books for ${closeYearForm.financial_year}? New entries dated in this year will be blocked.`)) return;
      closeYearForm.financial_year = props.annualTreasurerReport.financial_year;
      closeYearForm.post(route('admin.accounting.financial_year.close', props.club.slug), { preserveScroll: true });
    };

    const signOffForm = useForm({ financial_year: props.annualTreasurerReport.financial_year, auditor_one_user_id: '', auditor_two_user_id: '', notes: '' });
    const showAuditConfirmModal = ref(false);

    // A slot with an active (pending or signed) request is locked: picking someone new here does nothing until
    // that request is cancelled, so the picker itself is replaced with a plain status line instead of a live select.
    const ACTIVE_SIGNATURE_STATUSES = ['pending', 'signed'];
    const auditorOneSignature = computed(() => props.annualTreasurerReport.audit_signatures?.year_audit_auditor_one ?? null);
    const auditorTwoSignature = computed(() => props.annualTreasurerReport.audit_signatures?.year_audit_auditor_two ?? null);
    const auditorOneLocked = computed(() => ACTIVE_SIGNATURE_STATUSES.includes(auditorOneSignature.value?.status));
    const auditorTwoLocked = computed(() => ACTIVE_SIGNATURE_STATUSES.includes(auditorTwoSignature.value?.status));
    const bothAuditorsLocked = computed(() => auditorOneLocked.value && auditorTwoLocked.value);

    watch([auditorOneSignature, auditorTwoSignature], () => {
      signOffForm.auditor_one_user_id = auditorOneLocked.value ? String(auditorOneSignature.value.signer_id) : signOffForm.auditor_one_user_id;
      signOffForm.auditor_two_user_id = auditorTwoLocked.value ? String(auditorTwoSignature.value.signer_id) : signOffForm.auditor_two_user_id;
    }, { immediate: true });

    const auditRecipients = computed(() => [
      !auditorOneLocked.value ? signOffForm.auditor_one_user_id : null,
      !auditorTwoLocked.value ? signOffForm.auditor_two_user_id : null,
    ].map((id) => props.members.find((m) => m.id === Number(id))).filter(Boolean));

    const submitSignOff = () => {
      showAuditConfirmModal.value = true;
    };
    const confirmSignOff = () => {
      signOffForm.financial_year = props.annualTreasurerReport.financial_year;
      signOffForm.post(route('admin.accounting.financial_year.audit', props.club.slug), {
        preserveScroll: true,
        onSuccess: () => {
          signOffForm.reset('notes');
          showAuditConfirmModal.value = false;
        },
      });
    };

    const resendAuditSignature = (purpose) => {
      router.post(route('admin.accounting.financial_year.audit.resend', { clubSlug: props.club.slug, purpose }), { financial_year: props.annualTreasurerReport.financial_year }, { preserveScroll: true });
    };

    const cancelAuditSignature = (purpose) => {
      if (!confirm('Cancel this signature request?')) return;
      router.post(route('admin.accounting.financial_year.audit.cancel', { clubSlug: props.club.slug, purpose }), { financial_year: props.annualTreasurerReport.financial_year }, { preserveScroll: true });
    };

    // Recurring Vendor Bills

    const toggleRecurringBill = (id) => {
      router.post(route('admin.accounting.recurring_bills.toggle', { clubSlug: props.club.slug, id }), {}, { preserveScroll: true });
    };
    const deleteRecurringBill = (id) => {
      if (!confirm('Remove this recurring bill schedule? Bills already generated from it are not affected.')) return;
      router.delete(route('admin.accounting.recurring_bills.destroy', { clubSlug: props.club.slug, id }), { preserveScroll: true });
    };

    return { vatSettingsForm, submitVatSettings, financialYearEndForm, submitFinancialYearEnd, charityCommissionForm, submitCharityCommission, examinerForm, submitExaminerRequest, resendExaminerSignature, changeExaminerYear, approvalThresholdForm, submitApprovalThreshold, depreciationForm, runDepreciation, budgetForm, budgetableAccounts, changeBudgetYear, submitBudget, changeTreasurerReportYear, closeYearForm, submitCloseYear, signOffForm, showAuditConfirmModal, auditorOneSignature, auditorTwoSignature, auditorOneLocked, auditorTwoLocked, bothAuditorsLocked, auditRecipients, submitSignOff, confirmSignOff, resendAuditSignature, cancelAuditSignature, toggleRecurringBill, deleteRecurringBill };
}
