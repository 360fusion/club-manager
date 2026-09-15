<?php

use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorAuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\EventAdminController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MemberImportExportController;
use App\Http\Controllers\MemberPortalController;
use App\Http\Controllers\MembershipAdminController;
use App\Http\Controllers\ClubDirectoryController;
use App\Http\Controllers\MemberSubscriptionsController;
use App\Http\Controllers\NewsletterAdminController;
use App\Http\Controllers\NewsletterTypeAdminController;
use App\Http\Controllers\PageAdminController;
use App\Http\Controllers\PostAdminController;
use App\Http\Controllers\UserAdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Public Member Registration Routes
Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store']);

// Password Reset Routes
Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

// Member Email Invitation Setup Routes
Route::get('/clubs/{slug}/invite/{token}', [\App\Http\Controllers\Auth\InvitationController::class, 'showForm'])->name('invitation.accept');
Route::post('/clubs/{slug}/invite/{token}', [\App\Http\Controllers\Auth\InvitationController::class, 'accept'])->name('invitation.submit');

// Passwordless Summons Email RSVP Routes
Route::get('/summons/rsvp/{token}', [\App\Http\Controllers\PasswordlessRsvpController::class, 'show'])->name('summons.rsvp.show');
Route::post('/summons/rsvp/{token}', [\App\Http\Controllers\PasswordlessRsvpController::class, 'store'])->name('summons.rsvp.store');

// Multi-Tenant Public Admin & Workspace Landing Routes
Route::get('/', [ClubController::class, 'index'])->name('home');

// Legacy Redirects for old oxford-boating URLs
Route::get('/clubs/oxford-boating/{path?}', function ($path = null) {
    return redirect('/clubs/lodge-of-fraternity' . ($path ? '/' . $path : ''), 301);
})->where('path', '.*');

Route::get('/site/oxford-boating', function () {
    return redirect('/site/lodge-of-fraternity', 301);
});

Route::get('/clubs/{slug}', [ClubController::class, 'show'])->name('clubs.show');
Route::get('/clubs/{slug}/visitor-register', [\App\Http\Controllers\VisitorRegistrationController::class, 'create'])->name('clubs.visitor.register');
Route::post('/clubs/{slug}/visitor-register', [\App\Http\Controllers\VisitorRegistrationController::class, 'store'])->name('clubs.visitor.store');

// Protected Authenticated Routes
Route::middleware(['auth'])->group(function () {
    // Profile & Password Management Routes
    Route::get('/admin/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/admin/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/admin/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Multi-Tenant Admin & Workspace Routes
    Route::get('/admin/clubs', [ClubController::class, 'myClubs'])->name('admin.clubs.index');
    Route::get('/clubs/{clubSlug}/admin/settings', [\App\Http\Controllers\ClubSettingsController::class, 'show'])->name('admin.settings.show');
    Route::put('/clubs/{clubSlug}/admin/settings', [\App\Http\Controllers\ClubSettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('/clubs/{slug}/domain', [ClubController::class, 'updateDomain'])->name('clubs.domain.update');

    // Central Spatie Media Library Routes
    Route::get('/clubs/{clubSlug}/admin/media-manager', [\App\Http\Controllers\MediaAdminController::class, 'page'])->name('admin.media.page');
    Route::get('/clubs/{clubSlug}/admin/media', [\App\Http\Controllers\MediaAdminController::class, 'index'])->name('admin.media.index');
    Route::post('/clubs/{clubSlug}/admin/media', [\App\Http\Controllers\MediaAdminController::class, 'store'])->name('admin.media.store');
    Route::put('/clubs/{clubSlug}/admin/media/{id}', [\App\Http\Controllers\MediaAdminController::class, 'update'])->name('admin.media.update');
    Route::post('/clubs/{clubSlug}/admin/media/{id}/crop', [\App\Http\Controllers\MediaAdminController::class, 'crop'])->name('admin.media.crop');
    Route::post('/clubs/{clubSlug}/admin/media/{id}/revert', [\App\Http\Controllers\MediaAdminController::class, 'revert'])->name('admin.media.revert');
    Route::post('/clubs/{clubSlug}/admin/media/{id}/restore', [\App\Http\Controllers\MediaAdminController::class, 'restore'])->name('admin.media.restore');
    Route::delete('/clubs/{clubSlug}/admin/media/{id}/force', [\App\Http\Controllers\MediaAdminController::class, 'forceDelete'])->name('admin.media.force_delete');
    Route::get('/clubs/{clubSlug}/admin/media/{id}/usage', [\App\Http\Controllers\MediaAdminController::class, 'usage'])->name('admin.media.usage');
    Route::post('/clubs/{clubSlug}/admin/media/bulk-delete', [\App\Http\Controllers\MediaAdminController::class, 'bulkDelete'])->name('admin.media.bulk_delete');
    Route::post('/clubs/{clubSlug}/admin/media/bulk-restore', [\App\Http\Controllers\MediaAdminController::class, 'bulkRestore'])->name('admin.media.bulk_restore');
    Route::post('/clubs/{clubSlug}/admin/media/bulk-force-delete', [\App\Http\Controllers\MediaAdminController::class, 'bulkForceDelete'])->name('admin.media.bulk_force_delete');
    Route::post('/clubs/{clubSlug}/admin/media/bulk-move', [\App\Http\Controllers\MediaAdminController::class, 'bulkMove'])->name('admin.media.bulk_move');
    Route::delete('/clubs/{clubSlug}/admin/media/{id}', [\App\Http\Controllers\MediaAdminController::class, 'destroy'])->name('admin.media.destroy');

    // CSV Member Import & Export Routes
    Route::post('/clubs/{slug}/members/import', [MemberImportExportController::class, 'import'])->name('clubs.members.import');
    Route::get('/clubs/{slug}/members/export', [MemberImportExportController::class, 'export'])->name('clubs.members.export');

    // Executive Analytics Route
    Route::get('/clubs/{slug}/admin/analytics', [AnalyticsController::class, 'show'])->name('admin.analytics');

    // Invoice & Receipt Routes
    Route::get('/clubs/{slug}/invoices/{id}/download', [InvoiceController::class, 'download'])->name('invoices.download');

    // Admin Website Builder Routes
    Route::get('/clubs/{clubSlug}/admin/pages', [PageAdminController::class, 'index'])->name('admin.pages.index');
    Route::get('/clubs/{clubSlug}/admin/pages/create', [PageAdminController::class, 'edit'])->name('admin.pages.create');
    Route::get('/clubs/{clubSlug}/admin/pages/{id}/edit', [PageAdminController::class, 'edit'])->name('admin.pages.edit');
    Route::post('/clubs/{clubSlug}/admin/pages', [PageAdminController::class, 'store'])->name('admin.pages.store');

    // Admin Event Management Routes
    Route::get('/clubs/{clubSlug}/admin/events', [EventAdminController::class, 'index'])->name('admin.events.index');
    Route::get('/clubs/{clubSlug}/admin/events/create', [EventAdminController::class, 'edit'])->name('admin.events.create');
    Route::get('/clubs/{clubSlug}/admin/events/{id}/edit', [EventAdminController::class, 'edit'])->name('admin.events.edit');
    Route::get('/clubs/{clubSlug}/admin/events/{id}/subscribers', [EventAdminController::class, 'subscribers'])->name('admin.events.subscribers');
    Route::post('/clubs/{clubSlug}/admin/events/{id}/subscribers/{userId}/payment-status', [EventAdminController::class, 'updateSubscriberPaymentStatus'])->name('admin.events.subscribers.payment_status');
    Route::post('/clubs/{clubSlug}/admin/events', [EventAdminController::class, 'store'])->name('admin.events.store');
    Route::delete('/clubs/{clubSlug}/admin/events/{id}', [EventAdminController::class, 'destroy'])->name('admin.events.destroy');

    // Admin Meeting & Summons Management Routes
    Route::get('/clubs/{clubSlug}/admin/meetings', [\App\Http\Controllers\MeetingAdminController::class, 'index'])->name('admin.meetings.index');
    Route::get('/clubs/{clubSlug}/admin/meetings/create', [\App\Http\Controllers\MeetingAdminController::class, 'create'])->name('admin.meetings.create');
    Route::get('/clubs/{clubSlug}/admin/meetings/{id}/edit', [\App\Http\Controllers\MeetingAdminController::class, 'edit'])->name('admin.meetings.edit');
    Route::post('/clubs/{clubSlug}/admin/meetings', [\App\Http\Controllers\MeetingAdminController::class, 'store'])->name('admin.meetings.store');
    Route::get('/clubs/{clubSlug}/admin/meetings/{id}', [\App\Http\Controllers\MeetingAdminController::class, 'show'])->name('admin.meetings.show');
    Route::post('/clubs/{clubSlug}/admin/meetings/{id}/rsvp', [\App\Http\Controllers\MeetingAdminController::class, 'updateRsvp'])->name('admin.meetings.rsvp.update');
    Route::post('/clubs/{clubSlug}/admin/meetings/{id}/rsvp-payment-status', [\App\Http\Controllers\MeetingAdminController::class, 'updatePaymentStatus'])->name('admin.meetings.rsvp.payment_status');
    Route::get('/clubs/{clubSlug}/admin/meetings/{id}/financial-return', [\App\Http\Controllers\MeetingAdminController::class, 'financialReturn'])->name('admin.meetings.financial_return.show');
    Route::post('/clubs/{clubSlug}/admin/meetings/{id}/financial-return', [\App\Http\Controllers\MeetingAdminController::class, 'storeFinancialReturn'])->name('admin.meetings.financial_return.store');
    Route::get('/clubs/{clubSlug}/admin/meetings/{id}/pdf', [\App\Http\Controllers\MeetingAdminController::class, 'pdf'])->name('admin.meetings.pdf');
    Route::post('/clubs/{clubSlug}/admin/meetings/generate-season', [\App\Http\Controllers\MeetingAdminController::class, 'generateSeason'])->name('admin.meetings.generate_season');
    Route::post('/clubs/{clubSlug}/admin/meetings/{id}/publish', [\App\Http\Controllers\MeetingAdminController::class, 'publishSummons'])->name('admin.meetings.publish');
    Route::post('/clubs/{clubSlug}/admin/meetings/{id}/duplicate', [\App\Http\Controllers\MeetingAdminController::class, 'duplicate'])->name('admin.meetings.duplicate');
    Route::delete('/clubs/{clubSlug}/admin/meetings/{id}', [\App\Http\Controllers\MeetingAdminController::class, 'destroy'])->name('admin.meetings.destroy');

    // Admin Blog & News Posts Routes
    Route::get('/clubs/{clubSlug}/admin/posts', [PostAdminController::class, 'index'])->name('admin.posts.index');
    Route::get('/clubs/{clubSlug}/admin/posts/create', [PostAdminController::class, 'edit'])->name('admin.posts.create');
    Route::get('/clubs/{clubSlug}/admin/posts/{id}/edit', [PostAdminController::class, 'edit'])->name('admin.posts.edit');
    Route::post('/clubs/{clubSlug}/admin/posts', [PostAdminController::class, 'store'])->name('admin.posts.store');
    Route::delete('/clubs/{clubSlug}/admin/posts/{id}', [PostAdminController::class, 'destroy'])->name('admin.posts.destroy');

    // Admin Newsletter Broadcast & Channels Routes
    Route::get('/clubs/{clubSlug}/admin/newsletters', [NewsletterAdminController::class, 'index'])->name('admin.newsletters.index');
    Route::get('/clubs/{clubSlug}/admin/newsletters/create', [NewsletterAdminController::class, 'edit'])->name('admin.newsletters.create');
    Route::get('/clubs/{clubSlug}/admin/newsletters/types', [NewsletterTypeAdminController::class, 'index'])->name('admin.newsletters.types');
    Route::get('/clubs/{clubSlug}/admin/newsletters/types/create', [NewsletterTypeAdminController::class, 'edit'])->name('admin.newsletters.types.create');
    Route::get('/clubs/{clubSlug}/admin/newsletters/types/{id}/edit', [NewsletterTypeAdminController::class, 'edit'])->name('admin.newsletters.types.edit');
    Route::post('/clubs/{clubSlug}/admin/newsletters/types', [NewsletterTypeAdminController::class, 'store'])->name('admin.newsletters.types.store');
    Route::delete('/clubs/{clubSlug}/admin/newsletters/types/{id}', [NewsletterTypeAdminController::class, 'destroy'])->name('admin.newsletters.types.destroy');
    Route::get('/clubs/{clubSlug}/admin/newsletters/subscribers', [NewsletterTypeAdminController::class, 'subscribers'])->name('admin.newsletters.subscribers');
    Route::post('/clubs/{clubSlug}/admin/newsletters/subscribers/{id}/status', [NewsletterTypeAdminController::class, 'updateSubscriberStatus'])->name('admin.newsletters.subscribers.status');
    Route::get('/clubs/{clubSlug}/admin/newsletters/{id}/edit', [NewsletterAdminController::class, 'edit'])->name('admin.newsletters.edit');
    Route::post('/clubs/{clubSlug}/admin/newsletters', [NewsletterAdminController::class, 'store'])->name('admin.newsletters.store');
    Route::post('/clubs/{clubSlug}/admin/newsletters/{id}/send', [NewsletterAdminController::class, 'send'])->name('admin.newsletters.send');
    Route::delete('/clubs/{clubSlug}/admin/newsletters/{id}', [NewsletterAdminController::class, 'destroy'])->name('admin.newsletters.destroy');

    // National Directory & Member Subscriptions Hub Routes
    Route::get('/directory', [ClubDirectoryController::class, 'index'])->name('directory.index');
    Route::post('/directory/clubs/{clubSlug}/subscribe/{typeId}', [ClubDirectoryController::class, 'subscribe'])->name('directory.subscribe');
    Route::get('/portal/subscriptions', [MemberSubscriptionsController::class, 'index'])->name('portal.subscriptions');
    Route::post('/portal/subscriptions/{clubSlug}/{typeId}/toggle', [MemberSubscriptionsController::class, 'toggle'])->name('portal.subscriptions.toggle');

    // Admin Subscriptions Plans Routes
    Route::get('/clubs/{clubSlug}/admin/subscriptions', [MembershipAdminController::class, 'index'])->name('admin.memberships.index');
    Route::get('/clubs/{clubSlug}/admin/subscriptions/create', [MembershipAdminController::class, 'edit'])->name('admin.memberships.create');
    Route::get('/clubs/{clubSlug}/admin/subscriptions/{id}/edit', [MembershipAdminController::class, 'edit'])->name('admin.memberships.edit');
    Route::post('/clubs/{clubSlug}/admin/subscriptions', [MembershipAdminController::class, 'store'])->name('admin.memberships.store');
    Route::delete('/clubs/{clubSlug}/admin/subscriptions/{id}', [MembershipAdminController::class, 'destroy'])->name('admin.memberships.destroy');

    // Admin Members Routes
    Route::get('/clubs/{clubSlug}/admin/users', [UserAdminController::class, 'index'])->name('admin.users.index');
    Route::get('/clubs/{clubSlug}/admin/users/{userId}', [UserAdminController::class, 'show'])->name('admin.users.show');
    Route::post('/clubs/{clubSlug}/admin/users', [UserAdminController::class, 'storeMember'])->name('admin.users.store');
    Route::post('/clubs/{clubSlug}/admin/users/{userId}/invite', [UserAdminController::class, 'sendInvite'])->name('admin.users.invite');
    Route::post('/clubs/{clubSlug}/admin/users/{userId}/revoke-invite', [UserAdminController::class, 'revokeInvite'])->name('admin.users.revoke_invite');
    Route::post('/clubs/{clubSlug}/admin/users/{userId}/status', [UserAdminController::class, 'updateStatus'])->name('admin.users.status.update');
    Route::post('/clubs/{clubSlug}/admin/users/{userId}/role', [UserAdminController::class, 'updateRole'])->name('admin.users.role.update');
    Route::post('/clubs/{clubSlug}/admin/users/{userId}/rank', [UserAdminController::class, 'updateRank'])->name('admin.users.rank.update');
    Route::post('/clubs/{clubSlug}/admin/users/{userId}/committee-role', [UserAdminController::class, 'updateCommitteeRole'])->name('admin.users.committee_role.update');
    Route::delete('/clubs/{clubSlug}/admin/users/{userId}', [UserAdminController::class, 'removeMember'])->name('admin.users.destroy');
    Route::delete('/clubs/{clubSlug}/admin/users/{userId}/force', [UserAdminController::class, 'forceDeleteMember'])->name('admin.users.force_delete');

    // Billing & Subscription Management Routes
    Route::get('/clubs/{clubSlug}/admin/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/clubs/{clubSlug}/admin/billing/provider', [BillingController::class, 'updateProvider'])->name('billing.provider.update');

    // Admin Accounting & ERP Routes (Liberu Accounting Integration)
    Route::get('/clubs/{clubSlug}/admin/accounting', [\App\Http\Controllers\AccountingAdminController::class, 'index'])->name('admin.accounting.index');
    Route::post('/clubs/{clubSlug}/admin/accounting/accounts', [\App\Http\Controllers\AccountingAdminController::class, 'storeAccount'])->name('admin.accounting.accounts.store');
    Route::post('/clubs/{clubSlug}/admin/accounting/opening-balance', [\App\Http\Controllers\AccountingAdminController::class, 'storeOpeningBalance'])->name('admin.accounting.opening_balance.store');
    Route::post('/clubs/{clubSlug}/admin/accounting/journal-entries', [\App\Http\Controllers\AccountingAdminController::class, 'storeJournalEntry'])->name('admin.accounting.journal.store');
    Route::get('/clubs/{clubSlug}/admin/accounting/invoices/create', [\App\Http\Controllers\AccountingAdminController::class, 'createInvoice'])->name('admin.accounting.invoices.create');
    Route::post('/clubs/{clubSlug}/admin/accounting/invoices', [\App\Http\Controllers\AccountingAdminController::class, 'storeInvoice'])->name('admin.accounting.invoices.store');
    Route::post('/clubs/{clubSlug}/admin/accounting/invoices/{id}/pay', [\App\Http\Controllers\AccountingAdminController::class, 'markInvoicePaid'])->name('admin.accounting.invoices.pay');
    Route::delete('/clubs/{clubSlug}/admin/accounting/invoices/{id}/attachment', [\App\Http\Controllers\AccountingAdminController::class, 'deleteInvoiceAttachment'])->name('admin.accounting.invoices.attachment.destroy');
    Route::post('/clubs/{clubSlug}/admin/accounting/bills', [\App\Http\Controllers\AccountingAdminController::class, 'storeBill'])->name('admin.accounting.bills.store');
    Route::post('/clubs/{clubSlug}/admin/accounting/bills/{id}/pay', [\App\Http\Controllers\AccountingAdminController::class, 'markBillPaid'])->name('admin.accounting.bills.pay');
    Route::delete('/clubs/{clubSlug}/admin/accounting/bills/{id}/attachment', [\App\Http\Controllers\AccountingAdminController::class, 'deleteBillAttachment'])->name('admin.accounting.bills.attachment.destroy');
    Route::delete('/clubs/{clubSlug}/admin/accounting/bills/{id}', [\App\Http\Controllers\AccountingAdminController::class, 'destroyBill'])->name('admin.accounting.bills.destroy');
    Route::get('/clubs/{clubSlug}/admin/accounting/contacts/create', [\App\Http\Controllers\AccountingAdminController::class, 'createContact'])->name('admin.accounting.contacts.create');
    Route::get('/clubs/{clubSlug}/admin/accounting/contacts/member/{userId}/edit', [\App\Http\Controllers\AccountingAdminController::class, 'editMemberContact'])->name('admin.accounting.contacts.member.edit');
    Route::get('/clubs/{clubSlug}/admin/accounting/contacts/{id}/edit', [\App\Http\Controllers\AccountingAdminController::class, 'editContact'])->name('admin.accounting.contacts.edit');
    Route::post('/clubs/{clubSlug}/admin/accounting/contacts', [\App\Http\Controllers\AccountingAdminController::class, 'storeContact'])->name('admin.accounting.contacts.store');
    Route::put('/clubs/{clubSlug}/admin/accounting/contacts/{id}', [\App\Http\Controllers\AccountingAdminController::class, 'updateContact'])->name('admin.accounting.contacts.update');
    Route::delete('/clubs/{clubSlug}/admin/accounting/contacts/{id}', [\App\Http\Controllers\AccountingAdminController::class, 'destroyContact'])->name('admin.accounting.contacts.destroy');

    // Lodge Committee & Board Governance Routes (Livewire Domain)
    Route::get('/clubs/{clubSlug}/admin/committee', \App\Domains\ClubAccounting\Livewire\Committee\MeetingIndex::class)->name('admin.committee.index');
    Route::get('/clubs/{clubSlug}/admin/committee/{meetingId}', \App\Domains\ClubAccounting\Livewire\Committee\MeetingWorkspace::class)->name('admin.committee.workspace');
    Route::get('/clubs/{clubSlug}/admin/committee/{meetingId}/minutes', \App\Domains\ClubAccounting\Livewire\Committee\LiveMinuteTaker::class)->name('admin.committee.minutes');
    Route::get('/clubs/{clubSlug}/admin/committee/{meetingId}/pack-pdf', [\App\Domains\ClubAccounting\Http\Controllers\CommitteePackController::class, 'pdf'])->name('admin.committee.pack.pdf');
    Route::get('/committee/meetings/{meetingId}/pack-pdf', [\App\Domains\ClubAccounting\Http\Controllers\CommitteePackController::class, 'pdf'])->name('committee.pack.pdf');
    Route::get('/committee/meetings/{meetingId}/minutes', function ($meetingId) {
        $meeting = \App\Domains\ClubAccounting\Models\ClubCommitteeMeeting::with('club')->findOrFail($meetingId);
        return redirect()->route('admin.committee.minutes', [
            'clubSlug' => $meeting->club->slug,
            'meetingId' => $meeting->id,
        ]);
    })->name('committee.minutes');
    Route::get('/committee/meetings/{meetingId}', function ($meetingId) {
        $meeting = \App\Domains\ClubAccounting\Models\ClubCommitteeMeeting::with('club')->findOrFail($meetingId);
        return redirect()->route('admin.committee.workspace', [
            'clubSlug' => $meeting->club->slug,
            'meetingId' => $meeting->id,
        ]);
    })->name('committee.workspace');

    // Core Member Management & Dues Domain Routes
    Route::get('/clubs/{clubSlug}/admin/members', \App\Domains\ClubAccounting\Livewire\Members\MemberIndex::class)->name('admin.club_acc.members.index');
    Route::get('/clubs/{clubSlug}/admin/members/{memberId}', \App\Domains\ClubAccounting\Livewire\Members\MemberProfile::class)->name('admin.club_acc.members.show');
    Route::get('/clubs/{clubSlug}/admin/candidates', \App\Domains\ClubAccounting\Livewire\Candidates\CandidatePipeline::class)->name('admin.club_acc.candidates.index');
    Route::get('/clubs/{clubSlug}/admin/dues-subscriptions', \App\Domains\ClubAccounting\Livewire\Subscriptions\SubscriptionIndex::class)->name('admin.club_acc.subscriptions.index');

    Route::post('/clubs/{clubSlug}/admin/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/clubs/{clubSlug}/admin/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');

    // 2FA Profile Settings Routes
    Route::get('/admin/profile/two-factor', [TwoFactorAuthController::class, 'show'])->name('admin.profile.two-factor');
    Route::post('/admin/profile/two-factor/enable', [TwoFactorAuthController::class, 'enable'])->name('admin.two-factor.enable');
    Route::post('/admin/profile/two-factor/confirm', [TwoFactorAuthController::class, 'confirm'])->name('admin.two-factor.confirm');
    Route::delete('/admin/profile/two-factor/disable', [TwoFactorAuthController::class, 'disable'])->name('admin.two-factor.disable');
    Route::post('/admin/profile/two-factor/recovery-codes', [TwoFactorAuthController::class, 'generateRecoveryCodes'])->name('admin.two-factor.recovery-codes');

    // Member Portal & Self-Service Routes
    Route::get('/clubs/{slug}/portal', [MemberPortalController::class, 'show'])->name('member.dashboard');
    Route::get('/clubs/{slug}/portal/clubs', [MemberPortalController::class, 'myClubs'])->name('member.clubs');
    Route::get('/clubs/{slug}/portal/events', [MemberPortalController::class, 'events'])->name('member.events');
    Route::get('/clubs/{slug}/portal/news/{id}', [MemberPortalController::class, 'showPost'])->name('member.posts.show');
    Route::get('/clubs/{slug}/portal/dues', [MemberPortalController::class, 'dues'])->name('member.dues');
    Route::get('/clubs/{slug}/portal/profile', [MemberPortalController::class, 'profile'])->name('member.profile');
    Route::post('/clubs/{slug}/portal/profile', [MemberPortalController::class, 'updateProfile'])->name('member.profile.update');
    Route::post('/clubs/{slug}/portal/events/{id}/rsvp', [MemberPortalController::class, 'updateRsvp'])->name('member.rsvp');
    Route::get('/clubs/{slug}/portal/meetings/{id}/summons', [MemberPortalController::class, 'summons'])->name('member.meetings.summons');
    Route::post('/clubs/{slug}/portal/meetings/{id}/rsvp', [MemberPortalController::class, 'updateMeetingRsvp'])->name('member.meetings.rsvp');
    Route::get('/clubs/{slug}/portal/meetings/{id}/pdf', [MemberPortalController::class, 'downloadMeetingPdf'])->name('member.meetings.pdf');

    // Invite-Only Member Approval & Rejection Routes
    Route::post('/clubs/{slug}/members/{userId}/approve', [ClubController::class, 'approveMember'])->name('clubs.members.approve');
    Route::post('/clubs/{slug}/members/{userId}/reject', [ClubController::class, 'rejectMember'])->name('clubs.members.reject');

    // Admin Attendance Check-In Routes
    Route::get('/clubs/{clubSlug}/admin/events/{id}/checkin', [AttendanceController::class, 'show'])->name('admin.events.checkin');
    Route::post('/clubs/{clubSlug}/admin/events/{id}/checkin', [AttendanceController::class, 'checkIn'])->name('admin.events.checkin.store');
});

// Sanctum API Token & Pennant Feature Routes
Route::prefix('api/v1')->group(function () {
    Route::post('/tokens/create', [ApiController::class, 'issueToken'])->name('api.tokens.create');
    Route::get('/clubs/{slug}/info', [ApiController::class, 'getClubInfo'])->name('api.clubs.info');

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});
