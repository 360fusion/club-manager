<?php

use App\Domains\ClubAccounting\Http\Controllers\CommitteePackController;
use App\Domains\ClubAccounting\Livewire\Banking\BankAccountsIndex;
use App\Domains\ClubAccounting\Livewire\Banking\BankImportIndex;
use App\Domains\ClubAccounting\Livewire\Banking\BankReconciliationWorkspace;
use App\Domains\ClubAccounting\Livewire\Candidates\CandidatePipeline;
use App\Domains\ClubAccounting\Livewire\Committee\LiveMinuteTaker;
use App\Domains\ClubAccounting\Livewire\Committee\MeetingIndex;
use App\Domains\ClubAccounting\Livewire\Committee\MeetingWorkspace;
use App\Domains\ClubAccounting\Livewire\Members\MemberIndex;
use App\Domains\ClubAccounting\Livewire\Members\MemberProfile;
use App\Domains\ClubAccounting\Livewire\Subscriptions\SubscriptionIndex;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Http\Controllers\AccountingAdminController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorAuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CharityAdminController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubDirectoryController;
use App\Http\Controllers\ClubSettingsController;
use App\Http\Controllers\ClubShortLinkController;
use App\Http\Controllers\EventAdminController;
use App\Http\Controllers\GoCardlessWebhookController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LegacyClubUrlController;
use App\Http\Controllers\MediaAdminController;
use App\Http\Controllers\MeetingAdminController;
use App\Http\Controllers\MemberCalendarController;
use App\Http\Controllers\MemberHomeController;
use App\Http\Controllers\MemberImportExportController;
use App\Http\Controllers\MemberListController;
use App\Http\Controllers\MemberPortalController;
use App\Http\Controllers\MemberSubscriptionsController;
use App\Http\Controllers\NewsletterAdminController;
use App\Http\Controllers\NewsletterTypeAdminController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficerRosterAdminController;
use App\Http\Controllers\PageAdminController;
use App\Http\Controllers\PasswordlessRsvpController;
use App\Http\Controllers\PostAdminController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\QuickRsvpController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\UpdateAdminController;
use App\Http\Controllers\UserAdminController;
use App\Http\Controllers\VisitorRegistrationController;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Models\Club;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
// Unnamed: Fortify already registers a route named 'logout'. Two routes sharing a
// name is fatal to route:cache in production. Layouts post to the /logout URL
// directly, so this route needs no name of its own.
Route::match(['get', 'post'], '/logout', [LoginController::class, 'destroy']);

// Public Member Registration Routes
Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store']);

// Password Reset Routes
Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

// Member Email Invitation Setup Routes
Route::get('/{slug}/invite/{token}', [InvitationController::class, 'showForm'])->name('invitation.accept');
Route::post('/{slug}/invite/{token}', [InvitationController::class, 'accept'])->name('invitation.submit');

// Passwordless Summons Email RSVP Routes
Route::get('/summons/rsvp/{token}', [PasswordlessRsvpController::class, 'show'])->name('summons.rsvp.show');
Route::post('/summons/rsvp/{token}', [PasswordlessRsvpController::class, 'store'])->name('summons.rsvp.store');

// Multi-Tenant Public Admin & Workspace Landing Routes
Route::get('/', [ClubController::class, 'index'])->name('home');

// Legacy /clubs/... URLs, from before club slugs moved to the top level.
Route::any('/clubs/{legacySlug}/{path?}', LegacyClubUrlController::class)
    ->where('legacySlug', '[A-Za-z0-9_-]+')
    ->where('path', '.*');

Route::get('/site/oxford-boating', function () {
    $activeSlug = Club::first()?->slug ?? 'lodge-of-fraternity';

    return redirect('/site/'.$activeSlug, 301);
});

Route::get('/site/{clubSlug}/{pageSlug?}', [PublicSiteController::class, 'showPage'])->name('public.site');
Route::post('/site/{clubSlug}/contact-form', [PublicSiteController::class, 'submitContactForm'])->name('public.site.contact_form');

Route::get('/{slug}/overview', [ClubController::class, 'show'])->name('clubs.show');
Route::get('/{slug}/visitor-register', [VisitorRegistrationController::class, 'create'])->name('clubs.visitor.register');
Route::post('/{slug}/visitor-register', [VisitorRegistrationController::class, 'store'])->name('clubs.visitor.store');

// Protected Authenticated Routes
// Local-only preview of the shared UI primitives.
if (app()->isLocal()) {
    Route::get('/ui-kit', fn () => inertia('UiKit'))->name('ui_kit');
}

// GoCardless posts here unauthenticated; the request is authenticated by its
// HMAC signature instead. Must stay outside the auth group and exempt from CSRF.
Route::post('/webhooks/gocardless/{clubId}', [GoCardlessWebhookController::class, 'handle'])
    ->name('webhooks.gocardless');

Route::middleware(['auth'])->group(function () {
    // Profile & Password Management Routes
    Route::get('/members/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/members/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/members/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Multi-Tenant Admin & Workspace Routes
    Route::get('/admin/clubs', [ClubController::class, 'myClubs'])->name('admin.clubs.index');
    Route::get('/{clubSlug}/admin/settings', [ClubSettingsController::class, 'show'])->name('admin.settings.show');
    Route::put('/{clubSlug}/admin/settings', [ClubSettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('/{slug}/domain', [ClubController::class, 'updateDomain'])->name('clubs.domain.update');

    // Central Spatie Media Library Routes
    Route::get('/{clubSlug}/admin/media-manager', [MediaAdminController::class, 'page'])->name('admin.media.page');
    Route::get('/{clubSlug}/admin/media', [MediaAdminController::class, 'index'])->name('admin.media.index');
    Route::post('/{clubSlug}/admin/media', [MediaAdminController::class, 'store'])->name('admin.media.store');
    Route::put('/{clubSlug}/admin/media/{id}', [MediaAdminController::class, 'update'])->name('admin.media.update');
    Route::post('/{clubSlug}/admin/media/{id}/crop', [MediaAdminController::class, 'crop'])->name('admin.media.crop');
    Route::post('/{clubSlug}/admin/media/{id}/revert', [MediaAdminController::class, 'revert'])->name('admin.media.revert');
    Route::post('/{clubSlug}/admin/media/{id}/restore', [MediaAdminController::class, 'restore'])->name('admin.media.restore');
    Route::delete('/{clubSlug}/admin/media/{id}/force', [MediaAdminController::class, 'forceDelete'])->name('admin.media.force_delete');
    Route::get('/{clubSlug}/admin/media/{id}/usage', [MediaAdminController::class, 'usage'])->name('admin.media.usage');
    Route::post('/{clubSlug}/admin/media/bulk-delete', [MediaAdminController::class, 'bulkDelete'])->name('admin.media.bulk_delete');
    Route::post('/{clubSlug}/admin/media/bulk-restore', [MediaAdminController::class, 'bulkRestore'])->name('admin.media.bulk_restore');
    Route::post('/{clubSlug}/admin/media/bulk-force-delete', [MediaAdminController::class, 'bulkForceDelete'])->name('admin.media.bulk_force_delete');
    Route::post('/{clubSlug}/admin/media/bulk-move', [MediaAdminController::class, 'bulkMove'])->name('admin.media.bulk_move');
    Route::delete('/{clubSlug}/admin/media/{id}', [MediaAdminController::class, 'destroy'])->name('admin.media.destroy');

    // CSV Member Import & Export Routes
    Route::post('/{slug}/members/import', [MemberImportExportController::class, 'import'])->name('clubs.members.import');
    Route::get('/{slug}/members/export', [MemberImportExportController::class, 'export'])->name('clubs.members.export');

    // Executive Analytics Route
    Route::get('/{slug}/admin/analytics', [AnalyticsController::class, 'show'])->name('admin.analytics');

    // Invoice & Receipt Routes
    Route::get('/{slug}/invoices/{id}/download', [InvoiceController::class, 'download'])->name('invoices.download');

    // Admin Website Builder Routes
    Route::get('/{clubSlug}/admin/pages', [PageAdminController::class, 'index'])->name('admin.pages.index');
    Route::get('/{clubSlug}/admin/pages/create', [PageAdminController::class, 'edit'])->name('admin.pages.create');
    Route::get('/{clubSlug}/admin/pages/settings', [PageAdminController::class, 'settings'])->name('admin.pages.settings');
    Route::post('/{clubSlug}/admin/pages/settings', [PageAdminController::class, 'updateSettings'])->name('admin.pages.settings.update');
    Route::get('/{clubSlug}/admin/pages/themes', [PageAdminController::class, 'themes'])->name('admin.pages.themes');
    Route::post('/{clubSlug}/admin/pages/themes', [PageAdminController::class, 'updateTheme'])->name('admin.pages.themes.update');
    Route::get('/{clubSlug}/admin/pages/{id}/edit', [PageAdminController::class, 'edit'])->name('admin.pages.edit');
    Route::post('/{clubSlug}/admin/pages', [PageAdminController::class, 'store'])->name('admin.pages.store');
    Route::post('/{clubSlug}/admin/pages/reorder', [PageAdminController::class, 'reorder'])->name('admin.pages.reorder');
    Route::post('/{clubSlug}/admin/pages/{id}/toggle-publish', [PageAdminController::class, 'togglePublish'])->name('admin.pages.toggle_publish');
    Route::delete('/{clubSlug}/admin/pages/{id}', [PageAdminController::class, 'destroy'])->name('admin.pages.destroy');

    // Admin Event Management Routes
    Route::get('/{clubSlug}/admin/events', [EventAdminController::class, 'index'])->name('admin.events.index');
    Route::get('/{clubSlug}/admin/events/create', [EventAdminController::class, 'edit'])->name('admin.events.create');
    Route::get('/{clubSlug}/admin/events/{id}/edit', [EventAdminController::class, 'edit'])->name('admin.events.edit');
    Route::get('/{clubSlug}/admin/events/{id}/subscribers', [EventAdminController::class, 'subscribers'])->name('admin.events.subscribers');
    Route::post('/{clubSlug}/admin/events/{id}/subscribers/{userId}/payment-status', [EventAdminController::class, 'updateSubscriberPaymentStatus'])->name('admin.events.subscribers.payment_status');
    Route::post('/{clubSlug}/admin/events', [EventAdminController::class, 'store'])->name('admin.events.store');
    Route::delete('/{clubSlug}/admin/events/{id}', [EventAdminController::class, 'destroy'])->name('admin.events.destroy');

    // Admin Meeting & Summons Management Routes
    Route::get('/{clubSlug}/admin/meetings', [MeetingAdminController::class, 'index'])->name('admin.meetings.index');
    Route::get('/{clubSlug}/admin/meetings/create', [MeetingAdminController::class, 'create'])->name('admin.meetings.create');
    Route::get('/{clubSlug}/admin/meetings/{id}/edit', [MeetingAdminController::class, 'edit'])->name('admin.meetings.edit');
    Route::get('/{clubSlug}/admin/meetings/settings-json', [MeetingAdminController::class, 'settingsJson'])->name('admin.meetings.settings_json');
    Route::post('/{clubSlug}/admin/meetings', [MeetingAdminController::class, 'store'])->name('admin.meetings.store');
    Route::get('/{clubSlug}/admin/meetings/{id}', [MeetingAdminController::class, 'show'])->name('admin.meetings.show');
    Route::post('/{clubSlug}/admin/meetings/{id}/rsvp', [MeetingAdminController::class, 'updateRsvp'])->name('admin.meetings.rsvp.update');
    Route::post('/{clubSlug}/admin/meetings/{id}/rsvp-payment-status', [MeetingAdminController::class, 'updatePaymentStatus'])->name('admin.meetings.rsvp.payment_status');
    Route::get('/{clubSlug}/admin/meetings/{id}/financial-return', [MeetingAdminController::class, 'financialReturn'])->name('admin.meetings.financial_return.show');
    Route::post('/{clubSlug}/admin/meetings/{id}/financial-return', [MeetingAdminController::class, 'storeFinancialReturn'])->name('admin.meetings.financial_return.store');
    Route::get('/{clubSlug}/admin/meetings/{id}/pdf', [MeetingAdminController::class, 'pdf'])->name('admin.meetings.pdf');
    Route::post('/{clubSlug}/admin/meetings/generate-season', [MeetingAdminController::class, 'generateSeason'])->name('admin.meetings.generate_season');
    Route::post('/{clubSlug}/admin/meetings/{id}/publish', [MeetingAdminController::class, 'publishSummons'])->name('admin.meetings.publish');
    Route::post('/{clubSlug}/admin/meetings/{id}/duplicate', [MeetingAdminController::class, 'duplicate'])->name('admin.meetings.duplicate');
    Route::get('/{clubSlug}/admin/meetings/{id}/officer-election', [MeetingAdminController::class, 'officerElectionData'])->name('admin.meetings.officer_election.data');
    Route::post('/{clubSlug}/admin/meetings/{id}/officer-election', [MeetingAdminController::class, 'storeOfficerElection'])->name('admin.meetings.officer_election.store');
    Route::post('/{clubSlug}/admin/meetings/{id}/officer-election/confirm', [MeetingAdminController::class, 'confirmOfficerElection'])->name('admin.meetings.officer_election.confirm');
    Route::get('/{clubSlug}/admin/officers', [OfficerRosterAdminController::class, 'index'])->name('admin.officers.index');
    Route::post('/{clubSlug}/admin/officers', [OfficerRosterAdminController::class, 'store'])->name('admin.officers.store');
    Route::post('/{clubSlug}/admin/officers/quick-member', [OfficerRosterAdminController::class, 'storeQuickMember'])->name('admin.officers.quick_member');
    Route::post('/{clubSlug}/admin/officers/{id}/status', [OfficerRosterAdminController::class, 'updateStatus'])->name('admin.officers.status');
    Route::post('/{clubSlug}/admin/officers/{id}/install', [OfficerRosterAdminController::class, 'install'])->name('admin.officers.install');
    Route::delete('/{clubSlug}/admin/meetings/{id}', [MeetingAdminController::class, 'destroy'])->name('admin.meetings.destroy');

    // Admin Blog & News Posts Routes
    Route::get('/{clubSlug}/admin/posts', [PostAdminController::class, 'index'])->name('admin.posts.index');
    Route::get('/{clubSlug}/admin/posts/create', [PostAdminController::class, 'edit'])->name('admin.posts.create');
    Route::get('/{clubSlug}/admin/posts/{id}', [PostAdminController::class, 'show'])->name('admin.posts.show');
    Route::get('/{clubSlug}/admin/posts/{id}/edit', [PostAdminController::class, 'edit'])->name('admin.posts.edit');
    Route::post('/{clubSlug}/admin/posts', [PostAdminController::class, 'store'])->name('admin.posts.store');
    Route::delete('/{clubSlug}/admin/posts/{id}', [PostAdminController::class, 'destroy'])->name('admin.posts.destroy');

    // Admin Newsletter Broadcast & Channels Routes
    Route::get('/{clubSlug}/admin/newsletters', [NewsletterAdminController::class, 'index'])->name('admin.newsletters.index');
    Route::get('/{clubSlug}/admin/newsletters/create', [NewsletterAdminController::class, 'edit'])->name('admin.newsletters.create');
    Route::get('/{clubSlug}/admin/newsletters/types', [NewsletterTypeAdminController::class, 'index'])->name('admin.newsletters.types');
    Route::get('/{clubSlug}/admin/newsletters/types/create', [NewsletterTypeAdminController::class, 'edit'])->name('admin.newsletters.types.create');
    Route::get('/{clubSlug}/admin/newsletters/types/{id}/edit', [NewsletterTypeAdminController::class, 'edit'])->name('admin.newsletters.types.edit');
    Route::post('/{clubSlug}/admin/newsletters/types', [NewsletterTypeAdminController::class, 'store'])->name('admin.newsletters.types.store');
    Route::delete('/{clubSlug}/admin/newsletters/types/{id}', [NewsletterTypeAdminController::class, 'destroy'])->name('admin.newsletters.types.destroy');
    Route::get('/{clubSlug}/admin/newsletters/subscribers', [NewsletterTypeAdminController::class, 'subscribers'])->name('admin.newsletters.subscribers');
    Route::post('/{clubSlug}/admin/newsletters/subscribers/{id}/status', [NewsletterTypeAdminController::class, 'updateSubscriberStatus'])->name('admin.newsletters.subscribers.status');
    Route::get('/{clubSlug}/admin/newsletters/{id}/edit', [NewsletterAdminController::class, 'edit'])->name('admin.newsletters.edit');
    Route::post('/{clubSlug}/admin/newsletters', [NewsletterAdminController::class, 'store'])->name('admin.newsletters.store');
    Route::post('/{clubSlug}/admin/newsletters/{id}/send', [NewsletterAdminController::class, 'send'])->name('admin.newsletters.send');
    Route::delete('/{clubSlug}/admin/newsletters/{id}', [NewsletterAdminController::class, 'destroy'])->name('admin.newsletters.destroy');

    // Admin Updates & Weekly Digest Routes
    Route::get('/{clubSlug}/admin/updates', [UpdateAdminController::class, 'index'])->name('admin.updates.index');
    Route::post('/{clubSlug}/admin/updates', [UpdateAdminController::class, 'store'])->name('admin.updates.store');
    Route::post('/{clubSlug}/admin/updates/{id}/status', [UpdateAdminController::class, 'updateStatus'])->name('admin.updates.status.update');
    Route::delete('/{clubSlug}/admin/updates/{id}', [UpdateAdminController::class, 'destroy'])->name('admin.updates.destroy');
    Route::post('/{clubSlug}/admin/updates/dispatch-digest', [UpdateAdminController::class, 'triggerDigest'])->name('admin.updates.dispatch_digest');

    // National Directory & Member Subscriptions Hub Routes
    Route::get('/members/directory', [ClubDirectoryController::class, 'index'])->name('directory.index');
    Route::post('/members/directory/{clubSlug}/subscribe/{typeId}', [ClubDirectoryController::class, 'subscribe'])->name('directory.subscribe');
    Route::get('/members/subscriptions', [MemberSubscriptionsController::class, 'index'])->name('portal.subscriptions');
    Route::post('/members/subscriptions/{clubSlug}/{typeId}/toggle', [MemberSubscriptionsController::class, 'toggle'])->name('portal.subscriptions.toggle');

    // Admin Subscriptions Plans Routes
    Route::get('/{clubSlug}/admin/subscriptions', function ($clubSlug) {
        return redirect()->route('admin.club_acc.subscriptions.index', ['clubSlug' => $clubSlug]);
    })->name('admin.memberships.index');

    // Admin Members Routes
    Route::get('/{clubSlug}/admin/users', [UserAdminController::class, 'index'])->name('admin.users.index');
    Route::get('/{clubSlug}/admin/users/{userId}', [UserAdminController::class, 'show'])->name('admin.users.show');
    Route::post('/{clubSlug}/admin/users', [UserAdminController::class, 'storeMember'])->name('admin.users.store');
    Route::post('/{clubSlug}/admin/users/{userId}/invite', [UserAdminController::class, 'sendInvite'])->name('admin.users.invite');
    Route::post('/{clubSlug}/admin/users/{userId}/revoke-invite', [UserAdminController::class, 'revokeInvite'])->name('admin.users.revoke_invite');
    Route::post('/{clubSlug}/admin/users/{userId}/status', [UserAdminController::class, 'updateStatus'])->name('admin.users.status.update');
    Route::post('/{clubSlug}/admin/users/{userId}/role', [UserAdminController::class, 'updateRole'])->name('admin.users.role.update');
    Route::post('/{clubSlug}/admin/users/{userId}/rank', [UserAdminController::class, 'updateRank'])->name('admin.users.rank.update');
    Route::post('/{clubSlug}/admin/users/{userId}/committee-role', [UserAdminController::class, 'updateCommitteeRole'])->name('admin.users.committee_role.update');
    Route::delete('/{clubSlug}/admin/users/{userId}', [UserAdminController::class, 'removeMember'])->name('admin.users.destroy');
    Route::delete('/{clubSlug}/admin/users/{userId}/force', [UserAdminController::class, 'forceDeleteMember'])->name('admin.users.force_delete');

    // Billing & Subscription Management Routes
    Route::get('/{clubSlug}/admin/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/{clubSlug}/admin/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
    Route::post('/{clubSlug}/admin/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('/{clubSlug}/admin/billing/provider', [BillingController::class, 'updateProvider'])->name('billing.provider.update');

    // Admin Accounting & ERP Routes
    Route::post('/{clubSlug}/admin/accounting/accounts', [AccountingAdminController::class, 'storeAccount'])->name('admin.accounting.accounts.store');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/store', [AccountingAdminController::class, 'storeBankAccount'])->name('admin.accounting.bank_accounts.store');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/paypal/connect', [AccountingAdminController::class, 'connectPayPal'])->name('admin.accounting.bank_accounts.paypal.connect');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/paypal/test', [AccountingAdminController::class, 'testPayPalConnection'])->name('admin.accounting.bank_accounts.paypal.test');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/paypal/sync', [AccountingAdminController::class, 'syncPayPalTransactions'])->name('admin.accounting.bank_accounts.paypal.sync');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/stripe/connect', [AccountingAdminController::class, 'connectStripe'])->name('admin.accounting.bank_accounts.stripe.connect');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/stripe/sync', [AccountingAdminController::class, 'syncStripeTransactions'])->name('admin.accounting.bank_accounts.stripe.sync');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/sumup/connect', [AccountingAdminController::class, 'connectSumUp'])->name('admin.accounting.bank_accounts.sumup.connect');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/sumup/sync', [AccountingAdminController::class, 'syncSumUpTransactions'])->name('admin.accounting.bank_accounts.sumup.sync');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/gocardless/connect', [AccountingAdminController::class, 'connectGoCardless'])->name('admin.accounting.bank_accounts.gocardless.connect');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/gocardless/sync', [AccountingAdminController::class, 'syncGoCardlessTransactions'])->name('admin.accounting.bank_accounts.gocardless.sync');
    Route::post('/{clubSlug}/admin/accounting/giftaid/reconcile-auto', [AccountingAdminController::class, 'autoReconcileGiftAid'])->name('admin.accounting.giftaid.reconcile_auto');
    Route::get('/{clubSlug}/admin/accounting/giftaid/export-schedule', [AccountingAdminController::class, 'exportGiftAidSchedule'])->name('admin.accounting.giftaid.export_schedule');
    Route::get('/{clubSlug}/admin/accounting/giftaid/reconciled-donations', [AccountingAdminController::class, 'filterReconciledDonations'])->name('admin.accounting.giftaid.reconciled_donations');
    Route::get('/{clubSlug}/admin/accounting/giftaid/transactions', [CharityAdminController::class, 'giftAidTransactionsPage'])->name('admin.accounting.giftaid.transactions_page');
    Route::get('/{clubSlug}/admin/charity/gift-aid-transactions', [CharityAdminController::class, 'giftAidTransactionsPage'])->name('admin.charity.giftaid.transactions_page');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/toggle', [AccountingAdminController::class, 'toggleBankAccount'])->name('admin.accounting.bank_accounts.toggle');
    Route::post('/{clubSlug}/admin/accounting/opening-balance', [AccountingAdminController::class, 'storeOpeningBalance'])->name('admin.accounting.opening_balance.store');
    Route::get('/{clubSlug}/admin/accounting/journal-entries/create', [AccountingAdminController::class, 'createJournal'])->name('admin.accounting.journal.create');
    Route::post('/{clubSlug}/admin/accounting/journal-entries', [AccountingAdminController::class, 'storeJournalEntry'])->name('admin.accounting.journal.store');
    Route::get('/{clubSlug}/admin/accounting/journal-entries/{id}/edit', [AccountingAdminController::class, 'editJournalEntry'])->name('admin.accounting.journal.edit');
    Route::put('/{clubSlug}/admin/accounting/journal-entries/{id}', [AccountingAdminController::class, 'updateJournalEntry'])->name('admin.accounting.journal.update');
    Route::get('/{clubSlug}/admin/accounting/invoices/create', [AccountingAdminController::class, 'createInvoice'])->name('admin.accounting.invoices.create');
    Route::get('/{clubSlug}/admin/accounting/invoices/{id}/edit', [AccountingAdminController::class, 'editInvoice'])->name('admin.accounting.invoices.edit');
    Route::post('/{clubSlug}/admin/accounting/invoices', [AccountingAdminController::class, 'storeInvoice'])->name('admin.accounting.invoices.store');
    Route::put('/{clubSlug}/admin/accounting/invoices/{id}', [AccountingAdminController::class, 'updateInvoice'])->name('admin.accounting.invoices.update');
    Route::delete('/{clubSlug}/admin/accounting/invoices/{id}', [AccountingAdminController::class, 'destroyInvoice'])->name('admin.accounting.invoices.destroy');
    Route::post('/{clubSlug}/admin/accounting/invoices/{id}/publish', [AccountingAdminController::class, 'publishInvoice'])->name('admin.accounting.invoices.publish');
    Route::post('/{clubSlug}/admin/accounting/invoices/{id}/pay', [AccountingAdminController::class, 'markInvoicePaid'])->name('admin.accounting.invoices.pay');
    Route::delete('/{clubSlug}/admin/accounting/invoices/{id}/attachment', [AccountingAdminController::class, 'deleteInvoiceAttachment'])->name('admin.accounting.invoices.attachment.destroy');
    Route::get('/{clubSlug}/admin/accounting/bills/create', [AccountingAdminController::class, 'createBill'])->name('admin.accounting.bills.create');
    Route::post('/{clubSlug}/admin/accounting/bills', [AccountingAdminController::class, 'storeBill'])->name('admin.accounting.bills.store');
    Route::get('/{clubSlug}/admin/accounting/bills/{id}/edit', [AccountingAdminController::class, 'editBill'])->name('admin.accounting.bills.edit');
    Route::put('/{clubSlug}/admin/accounting/bills/{id}', [AccountingAdminController::class, 'updateBill'])->name('admin.accounting.bills.update');
    Route::post('/{clubSlug}/admin/accounting/bills/{id}/publish', [AccountingAdminController::class, 'publishBill'])->name('admin.accounting.bills.publish');
    Route::post('/{clubSlug}/admin/accounting/bills/{id}/pay', [AccountingAdminController::class, 'markBillPaid'])->name('admin.accounting.bills.pay');
    Route::delete('/{clubSlug}/admin/accounting/bills/{id}/attachment', [AccountingAdminController::class, 'deleteBillAttachment'])->name('admin.accounting.bills.attachment.destroy');
    Route::delete('/{clubSlug}/admin/accounting/bills/{id}', [AccountingAdminController::class, 'destroyBill'])->name('admin.accounting.bills.destroy');
    Route::get('/{clubSlug}/admin/accounting/contacts/create', [AccountingAdminController::class, 'createContact'])->name('admin.accounting.contacts.create');
    Route::get('/{clubSlug}/admin/accounting/contacts/member/{userId}/edit', [AccountingAdminController::class, 'editMemberContact'])->name('admin.accounting.contacts.member.edit');
    Route::get('/{clubSlug}/admin/accounting/contacts/{id}/edit', [AccountingAdminController::class, 'editContact'])->name('admin.accounting.contacts.edit');
    Route::post('/{clubSlug}/admin/accounting/contacts', [AccountingAdminController::class, 'storeContact'])->name('admin.accounting.contacts.store');
    Route::put('/{clubSlug}/admin/accounting/contacts/{id}', [AccountingAdminController::class, 'updateContact'])->name('admin.accounting.contacts.update');
    Route::delete('/{clubSlug}/admin/accounting/contacts/{id}', [AccountingAdminController::class, 'destroyContact'])->name('admin.accounting.contacts.destroy');
    Route::post('/{clubSlug}/admin/accounting/reconcile', [AccountingAdminController::class, 'reconcileBankTransaction'])->name('admin.accounting.reconcile');
    Route::post('/{clubSlug}/admin/accounting/ignore-transaction', [AccountingAdminController::class, 'ignoreBankTransaction'])->name('admin.accounting.ignore_transaction');
    Route::post('/{clubSlug}/admin/accounting/statement-lines/delete', [AccountingAdminController::class, 'deleteBankStatementLines'])->name('admin.accounting.statement_lines.delete');
    Route::post('/{clubSlug}/admin/accounting/statement-lines/restore', [AccountingAdminController::class, 'restoreBankStatementLines'])->name('admin.accounting.statement_lines.restore');
    Route::post('/{clubSlug}/admin/accounting/account-transactions/remove-and-redo', [AccountingAdminController::class, 'removeAndRedoAccountTransactions'])->name('admin.accounting.account_transactions.remove_redo');
    Route::post('/{clubSlug}/admin/accounting/import-statement', [AccountingAdminController::class, 'importBankStatement'])->name('admin.accounting.import_statement');
    Route::get('/{clubSlug}/admin/accounting/{tab?}/{report?}', [AccountingAdminController::class, 'index'])->where('tab', '^(?!invoices|bills|reconcile|ignore-transaction|statement-lines|account-transactions|import-statement|accounts|opening-balance|journal-entries).*$')->name('admin.accounting.index');

    // Lodge Committee & Board Governance Routes (Livewire Domain)
    Route::get('/{clubSlug}/admin/committee', MeetingIndex::class)->name('admin.committee.index');
    Route::get('/{clubSlug}/admin/committee/{meetingId}', MeetingWorkspace::class)->name('admin.committee.workspace');
    Route::get('/{clubSlug}/admin/committee/{meetingId}/minutes', LiveMinuteTaker::class)->name('admin.committee.minutes');
    Route::get('/{clubSlug}/admin/committee/{meetingId}/pack-pdf', [CommitteePackController::class, 'pdf'])->name('admin.committee.pack.pdf');
    Route::get('/committee/meetings/{meetingId}/pack-pdf', [CommitteePackController::class, 'pdf'])->name('committee.pack.pdf');
    Route::get('/committee/meetings/{meetingId}/minutes', function ($meetingId) {
        $meeting = ClubCommitteeMeeting::with('club')->findOrFail($meetingId);

        return redirect()->route('admin.committee.minutes', [
            'clubSlug' => $meeting->club->slug,
            'meetingId' => $meeting->id,
        ]);
    })->name('committee.minutes');
    Route::get('/committee/meetings/{meetingId}', function ($meetingId) {
        $meeting = ClubCommitteeMeeting::with('club')->findOrFail($meetingId);

        return redirect()->route('admin.committee.workspace', [
            'clubSlug' => $meeting->club->slug,
            'meetingId' => $meeting->id,
        ]);
    })->name('committee.workspace');

    // Core Member Management, Dues & Banking Domain Routes
    Route::get('/{clubSlug}/admin/members', MemberIndex::class)->name('admin.club_acc.members.index');
    Route::get('/{clubSlug}/admin/members/{memberId}', MemberProfile::class)->name('admin.club_acc.members.show');
    Route::get('/{clubSlug}/admin/candidates', CandidatePipeline::class)->name('admin.club_acc.candidates.index');
    Route::get('/{clubSlug}/admin/dues-subscriptions', SubscriptionIndex::class)->name('admin.club_acc.subscriptions.index');
    Route::get('/{clubSlug}/admin/bank-accounts', BankAccountsIndex::class)->name('admin.club_acc.bank_accounts.index');
    Route::get('/{clubSlug}/admin/bank-imports', BankImportIndex::class)->name('admin.club_acc.bank_imports.index');
    Route::get('/{clubSlug}/admin/bank-reconciliation', function ($clubSlug) {
        return redirect()->route('admin.accounting.index', ['clubSlug' => $clubSlug, 'tab' => 'reconciliation']);
    })->name('admin.club_acc.bank_reconciliation.index');
    Route::get('/{clubSlug}/admin/reconciliation-workspace', BankReconciliationWorkspace::class)->name('banking.bank-reconciliation-workspace');
    Route::get('/{clubSlug}/admin/charity', [CharityAdminController::class, 'index'])->name('admin.club_acc.charity.index');
    Route::get('/{clubSlug}/admin/charity/festival', [CharityAdminController::class, 'festivalPage'])->name('admin.club_acc.charity.festival');
    Route::post('/{clubSlug}/admin/charity/collections', [CharityAdminController::class, 'storeCollection'])->name('admin.charity.collections.store');
    Route::post('/{clubSlug}/admin/charity/grants', [CharityAdminController::class, 'storeGrant'])->name('admin.charity.grants.store');
    Route::post('/{clubSlug}/admin/charity/grants/{grantId}/status', [CharityAdminController::class, 'updateGrantStatus'])->name('admin.charity.grants.update_status');
    Route::post('/{clubSlug}/admin/charity/festival/target', [CharityAdminController::class, 'updateFestivalTarget'])->name('admin.charity.festival.target.update');
    Route::post('/{clubSlug}/admin/charity/festival/giving/{memberId}', [CharityAdminController::class, 'updateMemberGiving'])->name('admin.charity.festival.giving.update');

    Route::post('/{clubSlug}/admin/billing/business-account', [BillingController::class, 'updateBusinessAccount'])->name('billing.business.update');
    Route::post('/{clubSlug}/admin/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/{clubSlug}/admin/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');

    // 2FA Profile Settings Routes
    Route::get('/members/security', [TwoFactorAuthController::class, 'show'])->name('admin.profile.two-factor');
    Route::post('/members/security/enable', [TwoFactorAuthController::class, 'enable'])->name('admin.two-factor.enable');
    Route::post('/members/security/confirm', [TwoFactorAuthController::class, 'confirm'])->name('admin.two-factor.confirm');
    Route::delete('/members/security/disable', [TwoFactorAuthController::class, 'disable'])->name('admin.two-factor.disable');
    Route::post('/members/security/recovery-codes', [TwoFactorAuthController::class, 'generateRecoveryCodes'])->name('admin.two-factor.recovery-codes');

    // Member Portal & Self-Service Routes
    // Members area: everything across all of a member's clubs first, then one club.
    Route::get('/members/dashboard', MemberHomeController::class)->name('members.dashboard');
    Route::get('/members/calendar', [MemberCalendarController::class, 'show'])->name('members.calendar');
    Route::post('/members/calendar/link', [MemberCalendarController::class, 'regenerate'])->name('members.calendar.regenerate');
    Route::get('/members/events', [MemberListController::class, 'events'])->name('members.events');
    Route::get('/members/news', [MemberListController::class, 'news'])->name('members.news');
    Route::get('/members/meetings', [MemberListController::class, 'meetings'])->name('members.meetings');
    Route::get('/members/dues', [MemberListController::class, 'dues'])->name('members.dues');
    Route::get('/members/notifications', [NotificationController::class, 'index'])->name('members.notifications');
    Route::post('/members/notifications/read-all', [NotificationController::class, 'readAll'])->name('members.notifications.read_all');
    Route::get('/members/notifications/{id}/open', [NotificationController::class, 'open'])->name('members.notifications.open');

    Route::get('/members/{slug}', [MemberPortalController::class, 'show'])->name('member.dashboard');
    Route::get('/members/{slug}/calendar', [MemberCalendarController::class, 'show'])->name('member.calendar');
    Route::get('/members/{slug}/news', [MemberListController::class, 'news'])->name('member.news');
    Route::get('/members/{slug}/meetings', [MemberListController::class, 'meetings'])->name('member.meetings');
    Route::post('/members/{slug}/meetings/{id}/quick-rsvp', [QuickRsvpController::class, 'meeting'])->name('member.meetings.quick_rsvp');
    Route::post('/members/{slug}/events/{id}/quick-rsvp', [QuickRsvpController::class, 'event'])->name('member.events.quick_rsvp');
    Route::get('/members/{slug}/events', [MemberPortalController::class, 'events'])->name('member.events');
    Route::get('/members/{slug}/news/{id}', [MemberPortalController::class, 'showPost'])->name('member.posts.show');
    Route::get('/members/{slug}/dues', [MemberPortalController::class, 'dues'])->name('member.dues');
    Route::get('/members/{slug}/profile', [MemberPortalController::class, 'profile'])->name('member.profile');
    Route::post('/members/{slug}/profile', [MemberPortalController::class, 'updateProfile'])->name('member.profile.update');
    Route::post('/members/{slug}/events/{id}/rsvp', [MemberPortalController::class, 'updateRsvp'])->name('member.rsvp');
    Route::get('/members/{slug}/meetings/{id}/summons', [MemberPortalController::class, 'summons'])->name('member.meetings.summons');
    Route::post('/members/{slug}/meetings/{id}/rsvp', [MemberPortalController::class, 'updateMeetingRsvp'])->name('member.meetings.rsvp');
    Route::get('/members/{slug}/meetings/{id}/pdf', [MemberPortalController::class, 'downloadMeetingPdf'])->name('member.meetings.pdf');

    // Invite-Only Member Approval & Rejection Routes
    Route::post('/{slug}/members/{userId}/approve', [ClubController::class, 'approveMember'])->name('clubs.members.approve');
    Route::post('/{slug}/members/{userId}/reject', [ClubController::class, 'rejectMember'])->name('clubs.members.reject');

    // Admin Attendance Check-In Routes
    Route::get('/{clubSlug}/admin/events/{id}/checkin', [AttendanceController::class, 'show'])->name('admin.events.checkin');
    Route::post('/{clubSlug}/admin/events/{id}/checkin', [AttendanceController::class, 'checkIn'])->name('admin.events.checkin.store');
});

// Sanctum API Token & Pennant Feature Routes
Route::prefix('api/v1')->group(function () {
    Route::post('/tokens/create', [ApiController::class, 'issueToken'])->name('api.tokens.create');
    Route::get('/clubs/{slug}/info', [ApiController::class, 'getClubInfo'])->name('api.clubs.info');

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});

// Superadmin Management Console Routes
Route::middleware(['auth', EnsureUserIsSuperAdmin::class])->group(function () {
    Route::get('/superadmin', [SuperAdminController::class, 'dashboard'])->name('superadmin.dashboard');
    Route::get('/superadmin/club-types', [SuperAdminController::class, 'clubTypesIndex'])->name('superadmin.club_types.index');
    Route::get('/superadmin/club-types/{id}', [SuperAdminController::class, 'showClubType'])->name('superadmin.club_types.show');
    Route::post('/superadmin/club-types', [SuperAdminController::class, 'storeClubType'])->name('superadmin.club_types.store');
    Route::put('/superadmin/club-types/{id}', [SuperAdminController::class, 'updateClubType'])->name('superadmin.club_types.update');
    Route::get('/superadmin/email-templates', [SuperAdminController::class, 'emailTemplatesIndex'])->name('superadmin.email_templates.index');
    Route::put('/superadmin/email-templates/{id}', [SuperAdminController::class, 'updateEmailTemplate'])->name('superadmin.email_templates.update');
    Route::get('/superadmin/grand-lodges', [SuperAdminController::class, 'grandLodgesIndex'])->name('superadmin.grand_lodges.index');
    Route::post('/superadmin/grand-lodges', [SuperAdminController::class, 'storeGrandLodge'])->name('superadmin.grand_lodges.store');
    Route::put('/superadmin/grand-lodges/{id}', [SuperAdminController::class, 'updateGrandLodge'])->name('superadmin.grand_lodges.update');
    Route::delete('/superadmin/grand-lodges/{id}', [SuperAdminController::class, 'destroyGrandLodge'])->name('superadmin.grand_lodges.destroy');
    Route::get('/superadmin/provinces', [SuperAdminController::class, 'provincesIndex'])->name('superadmin.provinces.index');
    Route::get('/superadmin/provinces/{id}', [SuperAdminController::class, 'showProvince'])->name('superadmin.provinces.show');
    Route::get('/superadmin/provinces/{id}/edit', [SuperAdminController::class, 'editProvince'])->name('superadmin.provinces.edit');
    Route::post('/superadmin/provinces', [SuperAdminController::class, 'storeProvince'])->name('superadmin.provinces.store');
    Route::put('/superadmin/provinces/{id}', [SuperAdminController::class, 'updateProvince'])->name('superadmin.provinces.update');
    Route::delete('/superadmin/provinces/{id}', [SuperAdminController::class, 'destroyProvince'])->name('superadmin.provinces.destroy');

    // Districts and Groups
    Route::get('/superadmin/districts', [SuperAdminController::class, 'districtsIndex'])->name('superadmin.districts.index');
    Route::get('/superadmin/districts/{id}', [SuperAdminController::class, 'showDistrict'])->name('superadmin.districts.show');
    Route::get('/superadmin/districts/{id}/edit', [SuperAdminController::class, 'editDistrict'])->name('superadmin.districts.edit');
    Route::post('/superadmin/districts', [SuperAdminController::class, 'storeDistrict'])->name('superadmin.districts.store');
    Route::put('/superadmin/districts/{id}', [SuperAdminController::class, 'updateDistrict'])->name('superadmin.districts.update');
    Route::delete('/superadmin/districts/{id}', [SuperAdminController::class, 'destroyDistrict'])->name('superadmin.districts.destroy');

    Route::get('/superadmin/australian-grand-lodges', [SuperAdminController::class, 'australianGrandLodges'])->name('superadmin.australian_grand_lodges');
    Route::get('/superadmin/us-grand-lodges', [SuperAdminController::class, 'usGrandLodges'])->name('superadmin.us_grand_lodges');
});

// Dev Utility Helper Route
Route::get('/auth/make-me-superadmin', [SuperAdminController::class, 'makeMeSuperAdmin'])->middleware('auth')->name('auth.make_me_superadmin');

// Old member addresses, from before everything moved under /members.
Route::redirect('/members', '/members/dashboard', 301);
Route::redirect('/portal/subscriptions', '/members/subscriptions', 301);
Route::redirect('/admin/profile', '/members/profile', 301);
Route::redirect('/admin/profile/two-factor', '/members/security', 301);
Route::get('/directory', fn (Request $request) => redirect('/members/directory'.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301));
Route::redirect('/{slug}/events', '/members/{slug}/events', 301);
Route::redirect('/{slug}/dues', '/members/{slug}/dues', 301);
Route::redirect('/{slug}/profile', '/members/{slug}/profile', 301);
Route::redirect('/{slug}/clubs', '/members/dashboard', 301);
Route::redirect('/{slug}/news/{id}', '/members/{slug}/news/{id}', 301);
Route::redirect('/{slug}/meetings/{id}/summons', '/members/{slug}/meetings/{id}/summons', 301);
Route::redirect('/{slug}/meetings/{id}/pdf', '/members/{slug}/meetings/{id}/pdf', 301);

// The personal calendar feed is authenticated by the secret token in its URL.
Route::get('/calendar/{token}.ics', [MemberCalendarController::class, 'feed'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('members.calendar.feed');

// A club's short link: members go to their member area, everyone else to the public site.
// This is a single-segment route, so it has to stay last; the {slug} constraint keeps
// reserved words (login, members, site...) out of it.
Route::get('/{slug}', ClubShortLinkController::class)->name('clubs.short');
