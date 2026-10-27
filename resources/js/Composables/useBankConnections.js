import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { currencyCode } from '@/Utils/currency';
import { localDate } from '@/Utils/localDateTime';

/**
 * Connecting bank accounts and payment gateways (PayPal, Stripe, SumUp, GoCardless) on the accounting screen: the
 * dialogs' open state, their forms and submit actions, the "this looks like a different account type" check, and the
 * sync dialog that pulls a date range of transactions from a connected gateway.
 */
export function useBankConnections(club) {
    const showBankAccountModal = ref(false);
    const showPayPalModal = ref(false);
    const showPayPalSecret = ref(false);
    const testConnectionStatus = ref(null);

    const bankAccountForm = useForm({
      bank_name: '',
      account_name: '',
      account_type: 'current',
      account_number: '',
      sort_code: '',
      currency: currencyCode(),
      opening_balance: 0,
    });

    const payPalForm = useForm({
      account_name: 'Lodge PayPal Operating Account',
      paypal_client_id: '',
      paypal_client_secret: '',
      paypal_environment: 'live',
      currency: currencyCode(),
      opening_balance: 0,
    });

    const showAccountTypeWarning = ref(false);
    const accountTypeWarningBypassed = ref(false);

    const detectedMismatch = computed(() => {
      const name = `${bankAccountForm.bank_name || ''} ${bankAccountForm.account_name || ''}`.toLowerCase();
      const currentType = bankAccountForm.account_type;

      if (!name.trim()) return null;

      // Payment Gateways (Stripe, PayPal, GoCardless, etc.)
      if (/stripe|paypal|gocardless|checkout|klarna|adyen/.test(name)) {
        if (currentType !== 'payment_gateway') {
          return {
            suggestedType: 'payment_gateway',
            suggestedLabel: 'Payment Gateway (Stripe/PayPal)',
            reason: 'Selecting Payment Gateway enables fee tracking (Code 6000) and settlement reconciliation.',
          };
        }
      }

      // Merchant Readers (SumUp, Zettle, Square, Lopay, etc.)
      if (/sumup|zettle|square|lopay|clover|dopos/.test(name)) {
        if (currentType !== 'merchant') {
          return {
            suggestedType: 'merchant',
            suggestedLabel: 'Card Reader / Merchant (SumUp)',
            reason: 'Selecting Card Reader / Merchant optimizes daily merchant payout batch reconciliation.',
          };
        }
      }

      // Cash / Till / Petty Cash
      if (/cash|till|float|petty|drawer|box/.test(name)) {
        if (currentType !== 'cash') {
          return {
            suggestedType: 'cash',
            suggestedLabel: 'Petty Cash / Cash Register',
            reason: 'Selecting Petty Cash ignores sort codes and enables till count float reconciliations.',
          };
        }
      }

      // High Street Banks (Barclays, Lloyds, HSBC, NatWest, Santander, Starling, Revolut, Metro, etc.)
      if (/barclays|lloyds|hsbc|natwest|santander|starling|revolut|metro|nationwide|tsb|halifax|coutts/.test(name)) {
        if (['payment_gateway', 'merchant', 'cash'].includes(currentType)) {
          return {
            suggestedType: 'current',
            suggestedLabel: 'High Street Current Account',
            reason: 'High street banks are typically categorized under High Street Current Account.',
          };
        }
      }

      return null;
    });

    const submitBankAccount = () => {
      if (detectedMismatch.value && !accountTypeWarningBypassed.value) {
        showAccountTypeWarning.value = true;
        return;
      }

      bankAccountForm.post(route('admin.accounting.bank_accounts.store', club.slug), {
        preserveScroll: true,
        onSuccess: () => {
          showBankAccountModal.value = false;
          showAccountTypeWarning.value = false;
          accountTypeWarningBypassed.value = false;
          bankAccountForm.reset();
        },
      });
    };

    const applySuggestedTypeAndSubmit = () => {
      if (detectedMismatch.value) {
        bankAccountForm.account_type = detectedMismatch.value.suggestedType;
      }
      accountTypeWarningBypassed.value = true;
      submitBankAccount();
    };

    const proceedWithCurrentTypeAnyway = () => {
      accountTypeWarningBypassed.value = true;
      submitBankAccount();
    };

    const submitPayPalAccount = () => {
      payPalForm.post(route('admin.accounting.bank_accounts.paypal.connect', club.slug), {
        preserveScroll: true,
        onSuccess: () => {
          showPayPalModal.value = false;
          payPalForm.reset();
          testConnectionStatus.value = null;
        },
      });
    };

    const showStripeModal = ref(false);
    const showStripeSecret = ref(false);
    const stripeForm = useForm({
      account_name: 'Lodge Stripe Account',
      stripe_secret_key: '',
      currency: currencyCode(),
      opening_balance: 0,
    });

    const submitStripeAccount = () => {
      stripeForm.post(route('admin.accounting.bank_accounts.stripe.connect', club.slug), {
        preserveScroll: true,
        onSuccess: () => {
          showStripeModal.value = false;
          stripeForm.reset();
        },
      });
    };

    const showSumUpModal = ref(false);
    const showSumUpKey = ref(false);
    const sumUpForm = useForm({
      account_name: 'Lodge SumUp Merchant Account',
      sumup_api_key: '',
      currency: currencyCode(),
      opening_balance: 0,
    });

    const submitSumUpAccount = () => {
      sumUpForm.post(route('admin.accounting.bank_accounts.sumup.connect', club.slug), {
        preserveScroll: true,
        onSuccess: () => {
          showSumUpModal.value = false;
          sumUpForm.reset();
        },
      });
    };

    const showGoCardlessModal = ref(false);
    const showGoCardlessToken = ref(false);
    const showGoCardlessSecret = ref(false);
    const goCardlessForm = useForm({
      account_name: 'Lodge GoCardless Direct Debit Account',
      gocardless_access_token: '',
      gocardless_environment: 'sandbox',
      gocardless_webhook_secret: '',
      currency: currencyCode(),
      opening_balance: 0,
    });

    const submitGoCardlessAccount = () => {
      goCardlessForm.post(route('admin.accounting.bank_accounts.gocardless.connect', club.slug), {
        preserveScroll: true,
        onSuccess: () => {
          showGoCardlessModal.value = false;
          goCardlessForm.reset();
        },
      });
    };

    const showGatewaySyncModal = ref(false);
    const activeGatewaySyncAccId = ref(null);
    const gatewaySyncType = ref('paypal'); // 'paypal', 'stripe', 'sumup'
    const gatewaySyncForm = useForm({
      start_date: '',
      end_date: '',
    });

    const openGatewaySyncModal = (accId, type = 'paypal') => {
      activeGatewaySyncAccId.value = accId;
      gatewaySyncType.value = type;
      const thirtyDaysAgo = new Date();
      thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
      gatewaySyncForm.start_date = localDate(thirtyDaysAgo);
      gatewaySyncForm.end_date = localDate();
      showGatewaySyncModal.value = true;
    };

    const submitGatewaySyncModal = () => {
      if (!activeGatewaySyncAccId.value) return;

      let routeName = 'admin.accounting.bank_accounts.paypal.sync';
      if (gatewaySyncType.value === 'stripe') {
        routeName = 'admin.accounting.bank_accounts.stripe.sync';
      } else if (gatewaySyncType.value === 'sumup') {
        routeName = 'admin.accounting.bank_accounts.sumup.sync';
      } else if (gatewaySyncType.value === 'gocardless') {
        routeName = 'admin.accounting.bank_accounts.gocardless.sync';
      }

      gatewaySyncForm.post(route(routeName, { clubSlug: club.slug, id: activeGatewaySyncAccId.value }), {
        preserveScroll: true,
        onSuccess: () => {
          showGatewaySyncModal.value = false;
        },
      });
    };

    const syncPayPalNow = (accId) => {
      openGatewaySyncModal(accId, 'paypal');
    };

    const syncStripeNow = (accId) => {
      openGatewaySyncModal(accId, 'stripe');
    };

    const syncSumUpNow = (accId) => {
      openGatewaySyncModal(accId, 'sumup');
    };

    const syncGoCardlessNow = (accId) => {
      openGatewaySyncModal(accId, 'gocardless');
    };

    const toggleBankAccountActive = (accId) => {
      router.post(route('admin.accounting.bank_accounts.toggle', { clubSlug: club.slug, id: accId }), {}, {
        preserveScroll: true,
      });
    };

    return { showBankAccountModal, showPayPalModal, showPayPalSecret, bankAccountForm, payPalForm, showAccountTypeWarning, detectedMismatch, submitBankAccount, applySuggestedTypeAndSubmit, proceedWithCurrentTypeAnyway, submitPayPalAccount, showStripeModal, showStripeSecret, stripeForm, submitStripeAccount, showSumUpModal, showSumUpKey, sumUpForm, submitSumUpAccount, showGoCardlessModal, showGoCardlessToken, showGoCardlessSecret, goCardlessForm, submitGoCardlessAccount, showGatewaySyncModal, gatewaySyncType, gatewaySyncForm, submitGatewaySyncModal, syncPayPalNow, syncStripeNow, syncSumUpNow, syncGoCardlessNow, toggleBankAccountActive };
}
