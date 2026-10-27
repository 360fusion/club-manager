<?php

use App\Domains\ClubAccounting\Http\Controllers\CommitteePackController;
use App\Domains\ClubAccounting\Livewire\Banking\BankAccountsIndex;
use App\Domains\ClubAccounting\Livewire\Banking\BankImportIndex;
use App\Domains\ClubAccounting\Livewire\Banking\BankReconciliationWorkspace;
use App\Domains\ClubAccounting\Livewire\Candidates\CandidateDetail;
use App\Domains\ClubAccounting\Livewire\Candidates\CandidatePipeline;
use App\Domains\ClubAccounting\Livewire\Committee\LiveMinuteTaker;
use App\Domains\ClubAccounting\Livewire\Committee\MeetingIndex;
use App\Domains\ClubAccounting\Livewire\Committee\MeetingWorkspace;
use App\Domains\ClubAccounting\Livewire\Members\MemberFormPage;
use App\Domains\ClubAccounting\Livewire\Members\MemberImportPage;
use App\Domains\ClubAccounting\Livewire\Members\MemberIndex;
use App\Domains\ClubAccounting\Livewire\Members\MemberProfile;
use App\Domains\ClubAccounting\Livewire\Subscriptions\SubscriptionIndex;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Http\Controllers\AccountingAdminController;
use App\Http\Controllers\AccountingAssetController;
use App\Http\Controllers\AccountingBankController;
use App\Http\Controllers\AccountingBillController;
use App\Http\Controllers\AccountingContactController;
use App\Http\Controllers\AccountingExportController;
use App\Http\Controllers\AccountingGatewayController;
use App\Http\Controllers\AccountingInvoiceController;
use App\Http\Controllers\AccountingJournalController;
use App\Http\Controllers\AccountingSettingsController;
use App\Http\Controllers\AccountingYearEndController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\TwoFactorAuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CharityAdminController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubDirectoryController;
use App\Http\Controllers\ClubEmailTemplateController;
use App\Http\Controllers\ClubSettingsController;
use App\Http\Controllers\EventAdminController;
use App\Http\Controllers\EventGuestListController;
use App\Http\Controllers\EventOnlinePaymentController;
use App\Http\Controllers\EventOrganiserBookingController;
use App\Http\Controllers\EventPaymentController;
use App\Http\Controllers\EventQuoteController;
use App\Http\Controllers\EventRegistrationAdminController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LodgeClaimController;
use App\Http\Controllers\LodgeCorrectionController;
use App\Http\Controllers\LodgeFollowController;
use App\Http\Controllers\LodgeVisitorController;
use App\Http\Controllers\MediaAdminController;
use App\Http\Controllers\MediaBulkController;
use App\Http\Controllers\MediaFolderController;
use App\Http\Controllers\MediaTrashController;
use App\Http\Controllers\MediaVersionController;
use App\Http\Controllers\MeetingAdminController;
use App\Http\Controllers\MeetingBusinessController;
use App\Http\Controllers\MeetingDiningController;
use App\Http\Controllers\MeetingSummonsController;
use App\Http\Controllers\MemberCalendarController;
use App\Http\Controllers\MemberHomeController;
use App\Http\Controllers\MemberImportExportController;
use App\Http\Controllers\MemberListController;
use App\Http\Controllers\MemberLodgesController;
use App\Http\Controllers\MemberMeetingController;
use App\Http\Controllers\MemberPortalController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\MemberRsvpController;
use App\Http\Controllers\MemberSignatureController;
use App\Http\Controllers\MemberStatusAdminController;
use App\Http\Controllers\MemberSubscriptionsController;
use App\Http\Controllers\NewsletterAdminController;
use App\Http\Controllers\NewsletterTypeAdminController;
use App\Http\Controllers\NewsTagAdminController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficerRosterAdminController;
use App\Http\Controllers\PageAdminController;
use App\Http\Controllers\PageDraftController;
use App\Http\Controllers\PagePlacesController;
use App\Http\Controllers\PageRedirectController;
use App\Http\Controllers\PageSiteSettingsController;
use App\Http\Controllers\PageThemeController;
use App\Http\Controllers\PaymentOptionsController;
use App\Http\Controllers\PostAdminController;
use App\Http\Controllers\QuickRsvpController;
use App\Http\Controllers\SignatureAdminController;
use App\Http\Controllers\StripeConnectController;
use App\Http\Controllers\UpdateAdminController;
use App\Http\Controllers\UserAdminController;
use App\Http\Controllers\VisitorAccessAdminController;
use Illuminate\Support\Facades\Route;

// Signed-in routes: the member area (/members/...) and club administration (/{clubSlug}/admin/...).
Route::middleware(['auth'])->group(function () {
    // Where Stripe sends a lodge officer back after they approve connecting their Stripe account
    Route::get('/members/payments/stripe-callback', [StripeConnectController::class, 'callback'])->name('stripe.connect.callback');

    // Profile & Password Management Routes
    Route::get('/members/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/members/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/members/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Multi-Tenant Admin & Workspace Routes
    Route::get('/admin/clubs', [ClubController::class, 'myClubs'])->name('admin.clubs.index');
    Route::get('/{clubSlug}/admin/settings', [ClubSettingsController::class, 'show'])->name('admin.settings.show');
    Route::put('/{clubSlug}/admin/settings', [ClubSettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('/{clubSlug}/admin/settings/ranks/reset', [ClubSettingsController::class, 'resetRanks'])->name('admin.settings.ranks.reset');
    Route::post('/{clubSlug}/admin/settings/news-tags', [NewsTagAdminController::class, 'store'])->name('admin.settings.news_tags.store');
    Route::put('/{clubSlug}/admin/settings/news-tags/{id}', [NewsTagAdminController::class, 'update'])->name('admin.settings.news_tags.update');
    Route::delete('/{clubSlug}/admin/settings/news-tags/{id}', [NewsTagAdminController::class, 'destroy'])->name('admin.settings.news_tags.destroy');
    Route::post('/{slug}/domain', [ClubController::class, 'updateDomain'])->name('clubs.domain.update')->middleware('club.admin:manage_settings');

    // Central Spatie Media Library Routes
    Route::get('/{clubSlug}/admin/media-manager', [MediaAdminController::class, 'page'])->name('admin.media.page');
    Route::get('/{clubSlug}/admin/media', [MediaAdminController::class, 'index'])->name('admin.media.index');
    Route::post('/{clubSlug}/admin/media', [MediaAdminController::class, 'store'])->name('admin.media.store');
    Route::post('/{clubSlug}/admin/media/folders', [MediaFolderController::class, 'storeFolder'])->name('admin.media.folders.store');
    Route::put('/{clubSlug}/admin/media/folders/{folderId}', [MediaFolderController::class, 'updateFolder'])->name('admin.media.folders.update');
    Route::delete('/{clubSlug}/admin/media/folders/{folderId}', [MediaFolderController::class, 'destroyFolder'])->name('admin.media.folders.destroy');
    Route::put('/{clubSlug}/admin/media/{id}', [MediaAdminController::class, 'update'])->name('admin.media.update');
    Route::post('/{clubSlug}/admin/media/{id}/crop', [MediaAdminController::class, 'crop'])->name('admin.media.crop');
    Route::post('/{clubSlug}/admin/media/{id}/replace', [MediaAdminController::class, 'replaceFile'])->name('admin.media.replace');
    Route::get('/{clubSlug}/admin/media/{id}/versions', [MediaVersionController::class, 'versions'])->name('admin.media.versions.index');
    Route::post('/{clubSlug}/admin/media/{id}/versions/{versionId}/restore', [MediaVersionController::class, 'restoreVersion'])->name('admin.media.versions.restore');
    Route::get('/{clubSlug}/admin/media/{id}/versions/{versionId}/download', [MediaVersionController::class, 'downloadVersion'])->name('admin.media.versions.download');
    Route::post('/{clubSlug}/admin/media/{id}/restore', [MediaTrashController::class, 'restore'])->name('admin.media.restore');
    Route::delete('/{clubSlug}/admin/media/{id}/force', [MediaTrashController::class, 'forceDelete'])->name('admin.media.force_delete');
    Route::get('/{clubSlug}/admin/media/{id}/usage', [MediaBulkController::class, 'usage'])->name('admin.media.usage');
    Route::post('/{clubSlug}/admin/media/bulk-delete', [MediaBulkController::class, 'bulkDelete'])->name('admin.media.bulk_delete');
    Route::post('/{clubSlug}/admin/media/bulk-restore', [MediaTrashController::class, 'bulkRestore'])->name('admin.media.bulk_restore');
    Route::post('/{clubSlug}/admin/media/bulk-force-delete', [MediaTrashController::class, 'bulkForceDelete'])->name('admin.media.bulk_force_delete');
    Route::post('/{clubSlug}/admin/media/bulk-move', [MediaBulkController::class, 'bulkMove'])->name('admin.media.bulk_move');
    Route::delete('/{clubSlug}/admin/media/{id}', [MediaAdminController::class, 'destroy'])->name('admin.media.destroy');

    // CSV Member Import & Export Routes
    Route::get('/{slug}/members/export', [MemberImportExportController::class, 'export'])->name('clubs.members.export')->middleware('club.admin:manage_members');

    // Executive Analytics Route
    Route::get('/{slug}/admin/analytics', [AnalyticsController::class, 'show'])->name('admin.analytics');

    // Invoice & Receipt Routes
    Route::get('/{slug}/invoices/{id}/download', [InvoiceController::class, 'download'])->name('invoices.download');

    // Admin Website Builder Routes
    Route::get('/{clubSlug}/admin/pages', [PageAdminController::class, 'index'])->name('admin.pages.index');
    Route::get('/{clubSlug}/admin/pages/create', [PageAdminController::class, 'edit'])->name('admin.pages.create');
    Route::get('/{clubSlug}/admin/pages/settings', [PageSiteSettingsController::class, 'settings'])->name('admin.pages.settings');
    Route::post('/{clubSlug}/admin/pages/settings', [PageSiteSettingsController::class, 'updateSettings'])->name('admin.pages.settings.update');
    Route::get('/{clubSlug}/admin/pages/themes', [PageSiteSettingsController::class, 'themes'])->name('admin.pages.themes');
    Route::post('/{clubSlug}/admin/pages/themes', [PageThemeController::class, 'updateTheme'])->name('admin.pages.themes.update');
    Route::post('/{clubSlug}/admin/pages/colour-schemes', [PageThemeController::class, 'saveColourScheme'])->name('admin.pages.colour_schemes.save');
    Route::delete('/{clubSlug}/admin/pages/colour-schemes/{schemeId}', [PageThemeController::class, 'deleteColourScheme'])->name('admin.pages.colour_schemes.delete');
    Route::get('/{clubSlug}/admin/pages/places', [PagePlacesController::class, 'placeSuggestions'])->middleware('throttle:60,1')->name('admin.pages.places');
    Route::post('/{clubSlug}/admin/pages/geocode', [PagePlacesController::class, 'geocode'])->middleware('throttle:20,1')->name('admin.pages.geocode');
    Route::post('/{clubSlug}/admin/pages/downloads', [PagePlacesController::class, 'uploadDownload'])->middleware('throttle:60,1')->name('admin.pages.downloads.upload');
    Route::get('/{clubSlug}/admin/pages/redirects', [PageSiteSettingsController::class, 'redirects'])->name('admin.pages.redirects');
    Route::get('/{clubSlug}/admin/pages/overview', [PageSiteSettingsController::class, 'overview'])->name('admin.pages.overview');
    Route::post('/{clubSlug}/admin/pages/redirects', [PageRedirectController::class, 'storeRedirect'])->name('admin.pages.redirects.store');
    Route::delete('/{clubSlug}/admin/pages/redirects/{id}', [PageRedirectController::class, 'destroyRedirect'])->name('admin.pages.redirects.destroy');
    Route::post('/{clubSlug}/admin/pages/{id}/draft', [PageDraftController::class, 'saveDraft'])->name('admin.pages.draft.save');
    Route::delete('/{clubSlug}/admin/pages/{id}/draft', [PageDraftController::class, 'discardDraft'])->name('admin.pages.draft.discard');
    Route::post('/{clubSlug}/admin/pages/{id}/draft/publish', [PageDraftController::class, 'publishDraft'])->name('admin.pages.draft.publish');
    Route::post('/{clubSlug}/admin/pages/{id}/preview-link', [PageDraftController::class, 'previewLink'])->middleware('throttle:30,1')->name('admin.pages.preview_link');
    Route::get('/{clubSlug}/admin/pages/{id}/revisions', [PageDraftController::class, 'revisions'])->middleware('throttle:60,1')->name('admin.pages.revisions');
    Route::get('/{clubSlug}/admin/pages/{id}/revisions/{revisionId}', [PageDraftController::class, 'revision'])->middleware('throttle:60,1')->name('admin.pages.revision');
    Route::post('/{clubSlug}/admin/pages/{id}/copy-block', [PageDraftController::class, 'copyBlock'])->middleware('throttle:60,1')->name('admin.pages.copy_block');
    Route::get('/{clubSlug}/admin/pages/header-footer', [PageSiteSettingsController::class, 'headerFooter'])->name('admin.pages.header_footer');
    Route::post('/{clubSlug}/admin/pages/header-footer', [PageSiteSettingsController::class, 'updateHeaderFooter'])->name('admin.pages.header_footer.update');
    Route::get('/{clubSlug}/admin/pages/{id}/edit', [PageAdminController::class, 'edit'])->name('admin.pages.edit');
    Route::post('/{clubSlug}/admin/pages', [PageAdminController::class, 'store'])->name('admin.pages.store');
    Route::post('/{clubSlug}/admin/pages/reorder', [PageAdminController::class, 'reorder'])->name('admin.pages.reorder');
    Route::post('/{clubSlug}/admin/pages/{id}/toggle-publish', [PageAdminController::class, 'togglePublish'])->name('admin.pages.toggle_publish');
    Route::post('/{clubSlug}/admin/pages/{id}/duplicate', [PageAdminController::class, 'duplicate'])->name('admin.pages.duplicate');
    Route::post('/{clubSlug}/admin/pages/settings/verify-domain', [PageSiteSettingsController::class, 'verifyDomain'])->name('admin.pages.settings.verify_domain');
    Route::post('/{clubSlug}/admin/pages/trash/{id}/restore', [PageAdminController::class, 'restore'])->name('admin.pages.restore');
    Route::delete('/{clubSlug}/admin/pages/trash/{id}', [PageAdminController::class, 'forceDelete'])->name('admin.pages.force_delete');
    Route::delete('/{clubSlug}/admin/pages/{id}', [PageAdminController::class, 'destroy'])->name('admin.pages.destroy');

    // Admin Event Management Routes
    Route::get('/{clubSlug}/admin/events', [EventAdminController::class, 'index'])->name('admin.events.index');
    Route::get('/{clubSlug}/admin/events/create', [EventAdminController::class, 'edit'])->name('admin.events.create');
    Route::get('/{clubSlug}/admin/events/{id}/edit', [EventAdminController::class, 'edit'])->name('admin.events.edit');
    Route::get('/{clubSlug}/admin/events/{id}/subscribers', [EventRegistrationAdminController::class, 'subscribers'])->name('admin.events.subscribers');
    Route::get('/{clubSlug}/admin/events/{id}/registrations/{registrationId}/payment', [EventPaymentController::class, 'history'])->name('admin.events.payment.history')->middleware('club.admin:manage_billing');
    Route::post('/{clubSlug}/admin/events/{id}/registrations/{registrationId}/paid', [EventPaymentController::class, 'markPaid'])->name('admin.events.payment.paid')->middleware('club.admin:manage_billing');
    Route::post('/{clubSlug}/admin/events/{id}/registrations/{registrationId}/unpaid', [EventPaymentController::class, 'markUnpaid'])->name('admin.events.payment.unpaid')->middleware('club.admin:manage_billing');
    Route::post('/{clubSlug}/admin/events/{id}/registrations/{registrationId}/waive', [EventPaymentController::class, 'waive'])->name('admin.events.payment.waive')->middleware('club.admin:manage_billing');
    Route::post('/{clubSlug}/admin/events/{id}/registrations/{registrationId}/refund', [EventPaymentController::class, 'refund'])->name('admin.events.payment.refund')->middleware('club.admin:manage_billing');
    Route::post('/{clubSlug}/admin/events/{id}/bulk-paid', [EventPaymentController::class, 'bulkPaid'])->name('admin.events.payment.bulk_paid')->middleware('club.admin:manage_billing');
    Route::post('/{clubSlug}/admin/events', [EventAdminController::class, 'store'])->name('admin.events.store');
    Route::delete('/{clubSlug}/admin/events/{id}', [EventAdminController::class, 'destroy'])->name('admin.events.destroy');
    Route::get('/{clubSlug}/admin/events/{id}/member-search', [EventOrganiserBookingController::class, 'memberSearch'])->middleware('throttle:60,1')->name('admin.events.member_search');
    Route::post('/{clubSlug}/admin/events/{id}/quote', [EventQuoteController::class, 'organiser'])->middleware('throttle:120,1')->name('admin.events.quote');
    Route::post('/{clubSlug}/admin/events/{id}/registrations', [EventOrganiserBookingController::class, 'store'])->name('admin.events.registrations.store');
    Route::get('/{clubSlug}/admin/events/{id}/registrations/{registrationId}', [EventOrganiserBookingController::class, 'show'])->name('admin.events.registrations.show');
    Route::put('/{clubSlug}/admin/events/{id}/registrations/{registrationId}', [EventOrganiserBookingController::class, 'update'])->name('admin.events.registrations.update');
    Route::post('/{clubSlug}/admin/events/{id}/registrations/{registrationId}/resend', [EventOrganiserBookingController::class, 'resend'])->middleware('throttle:20,1')->name('admin.events.registrations.resend');
    Route::post('/{clubSlug}/admin/events/{id}/registrations/{registrationId}/cancel', [EventRegistrationAdminController::class, 'cancelRegistration'])->name('admin.events.registrations.cancel');
    Route::put('/{clubSlug}/admin/events/{id}/attendees/{attendeeId}/meal', [EventRegistrationAdminController::class, 'updateAttendeeMeal'])->name('admin.events.attendees.meal');
    Route::post('/{clubSlug}/admin/events/{id}/registrations/{registrationId}/promote', [EventRegistrationAdminController::class, 'promoteRegistration'])->name('admin.events.registrations.promote');
    Route::post('/{clubSlug}/admin/events/{id}/duplicate', [EventAdminController::class, 'duplicate'])->name('admin.events.duplicate');
    Route::post('/{clubSlug}/admin/events/{id}/cancel', [EventAdminController::class, 'cancel'])->name('admin.events.cancel');

    // Admin Meeting & Summons Management Routes
    Route::get('/{clubSlug}/admin/meetings', [MeetingAdminController::class, 'index'])->name('admin.meetings.index');
    Route::get('/{clubSlug}/admin/meetings/create', [MeetingAdminController::class, 'create'])->name('admin.meetings.create');
    Route::get('/{clubSlug}/admin/meetings/{id}/edit', [MeetingAdminController::class, 'edit'])->name('admin.meetings.edit');
    Route::get('/{clubSlug}/admin/meetings/settings-json', [MeetingAdminController::class, 'settingsJson'])->name('admin.meetings.settings_json');
    Route::post('/{clubSlug}/admin/meetings', [MeetingAdminController::class, 'store'])->name('admin.meetings.store');
    Route::get('/{clubSlug}/admin/meetings/{id}', [MeetingAdminController::class, 'show'])->name('admin.meetings.show');
    Route::post('/{clubSlug}/admin/meetings/{id}/rsvp', [MeetingDiningController::class, 'updateRsvp'])->name('admin.meetings.rsvp.update');
    Route::post('/{clubSlug}/admin/meetings/{id}/rsvp-payment-status', [MeetingDiningController::class, 'updatePaymentStatus'])->name('admin.meetings.rsvp.payment_status');
    Route::get('/{clubSlug}/admin/meetings/{id}/dining', [MeetingDiningController::class, 'dining'])->name('admin.meetings.dining');
    Route::post('/{clubSlug}/admin/meetings/{id}/dining/payment', [MeetingDiningController::class, 'recordDiningPayment'])->name('admin.meetings.dining.payment');
    Route::get('/{clubSlug}/admin/meetings/{id}/financial-return', [MeetingBusinessController::class, 'financialReturn'])->name('admin.meetings.financial_return.show');
    Route::post('/{clubSlug}/admin/meetings/{id}/financial-return', [MeetingBusinessController::class, 'storeFinancialReturn'])->name('admin.meetings.financial_return.store');
    Route::get('/{clubSlug}/admin/meetings/{id}/pdf', [MeetingSummonsController::class, 'pdf'])->name('admin.meetings.pdf');
    Route::post('/{clubSlug}/admin/meetings/generate-season', [MeetingSummonsController::class, 'generateSeason'])->name('admin.meetings.generate_season');
    Route::post('/{clubSlug}/admin/meetings/{id}/publish', [MeetingSummonsController::class, 'publishSummons'])->name('admin.meetings.publish');
    Route::post('/{clubSlug}/admin/meetings/{id}/duplicate', [MeetingAdminController::class, 'duplicate'])->name('admin.meetings.duplicate');
    Route::get('/{clubSlug}/admin/meetings/{id}/officer-election', [MeetingBusinessController::class, 'officerElectionData'])->name('admin.meetings.officer_election.data');
    Route::post('/{clubSlug}/admin/meetings/{id}/officer-election', [MeetingBusinessController::class, 'storeOfficerElection'])->name('admin.meetings.officer_election.store');
    Route::post('/{clubSlug}/admin/meetings/{id}/officer-election/confirm', [MeetingBusinessController::class, 'confirmOfficerElection'])->name('admin.meetings.officer_election.confirm');
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
    Route::post('/{clubSlug}/admin/newsletters/preview', [NewsletterAdminController::class, 'preview'])->middleware('throttle:60,1')->name('admin.newsletters.preview');
    Route::post('/{clubSlug}/admin/newsletters/test', [NewsletterAdminController::class, 'test'])->middleware('throttle:10,1')->name('admin.newsletters.test');
    Route::post('/{clubSlug}/admin/newsletters/{id}/retry', [NewsletterAdminController::class, 'retry'])->name('admin.newsletters.retry');
    Route::post('/{clubSlug}/admin/newsletters/{id}/duplicate', [NewsletterAdminController::class, 'duplicate'])->name('admin.newsletters.duplicate');
    Route::post('/{clubSlug}/admin/newsletters/{id}/send', [NewsletterAdminController::class, 'send'])->name('admin.newsletters.send');
    Route::delete('/{clubSlug}/admin/newsletters/{id}', [NewsletterAdminController::class, 'destroy'])->name('admin.newsletters.destroy');

    // Visitors who ask to receive the lodge's summonses (guarded by the meetings capability through the route map)
    Route::get('/{clubSlug}/admin/visitors', [VisitorAccessAdminController::class, 'index'])->name('admin.visitors.index');
    Route::post('/{clubSlug}/admin/visitors/{id}/approve', [VisitorAccessAdminController::class, 'approve'])->name('admin.visitors.approve');
    Route::post('/{clubSlug}/admin/visitors/{id}/decline', [VisitorAccessAdminController::class, 'decline'])->name('admin.visitors.decline');
    Route::post('/{clubSlug}/admin/visitors/{id}/revoke', [VisitorAccessAdminController::class, 'revoke'])->name('admin.visitors.revoke');

    // Admin Updates & Weekly Digest Routes
    Route::get('/{clubSlug}/admin/updates', [UpdateAdminController::class, 'index'])->name('admin.updates.index');
    Route::post('/{clubSlug}/admin/updates', [UpdateAdminController::class, 'store'])->name('admin.updates.store');
    Route::post('/{clubSlug}/admin/updates/{id}/status', [UpdateAdminController::class, 'updateStatus'])->name('admin.updates.status.update');
    Route::delete('/{clubSlug}/admin/updates/{id}', [UpdateAdminController::class, 'destroy'])->name('admin.updates.destroy');
    Route::post('/{clubSlug}/admin/updates/dispatch-digest', [UpdateAdminController::class, 'triggerDigest'])->name('admin.updates.dispatch_digest');

    // National Directory & Member Subscriptions Hub Routes
    Route::get('/members/lodges', [MemberLodgesController::class, 'index'])->name('members.lodges');
    Route::post('/lodges/{slug}/visitor-access', [LodgeVisitorController::class, 'store'])->middleware('throttle:10,1')->name('lodges.visitor_access');
    Route::delete('/lodges/{slug}/visitor-access', [LodgeVisitorController::class, 'destroy'])->middleware('throttle:20,1')->name('lodges.visitor_access.withdraw');
    Route::get('/lodges/{slug}/claim', [LodgeClaimController::class, 'create'])->name('lodges.claim');
    Route::post('/lodges/{slug}/claim', [LodgeClaimController::class, 'store'])->middleware('throttle:6,1')->name('lodges.claim.store');
    Route::post('/members/lodges/claims/{id}/respond', [LodgeClaimController::class, 'respond'])->middleware('throttle:20,1')->name('lodges.claim.respond');
    Route::delete('/members/lodges/claims/{id}', [LodgeClaimController::class, 'destroy'])->middleware('throttle:20,1')->name('lodges.claim.withdraw');
    Route::get('/lodges/{slug}/suggest-fix', [LodgeCorrectionController::class, 'create'])->name('lodges.correction');
    Route::post('/lodges/{slug}/suggest-fix', [LodgeCorrectionController::class, 'store'])->middleware('throttle:6,1')->name('lodges.correction.store');
    Route::post('/lodges/{slug}/follow', [LodgeFollowController::class, 'store'])->middleware('throttle:60,1')->name('lodges.follow');
    Route::patch('/lodges/{slug}/follow', [LodgeFollowController::class, 'update'])->middleware('throttle:60,1')->name('lodges.follow.update');
    Route::delete('/lodges/{slug}/follow', [LodgeFollowController::class, 'destroy'])->middleware('throttle:60,1')->name('lodges.unfollow');
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
    Route::post('/{clubSlug}/admin/users/{userId}/status', [MemberStatusAdminController::class, 'updateStatus'])->name('admin.users.status.update');
    Route::post('/{clubSlug}/admin/users/{userId}/role', [MemberStatusAdminController::class, 'updateRole'])->name('admin.users.role.update');
    Route::post('/{clubSlug}/admin/users/{userId}/rank', [MemberStatusAdminController::class, 'updateRank'])->name('admin.users.rank.update');
    Route::post('/{clubSlug}/admin/users/{userId}/committee-role', [MemberStatusAdminController::class, 'updateCommitteeRole'])->name('admin.users.committee_role.update');
    Route::delete('/{clubSlug}/admin/users/{userId}', [MemberStatusAdminController::class, 'removeMember'])->name('admin.users.destroy');
    Route::delete('/{clubSlug}/admin/users/{userId}/force', [MemberStatusAdminController::class, 'forceDeleteMember'])->name('admin.users.force_delete');

    // Billing & Subscription Management Routes
    Route::get('/{clubSlug}/admin/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/{clubSlug}/admin/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
    Route::post('/{clubSlug}/admin/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('/{clubSlug}/admin/billing/provider', [BillingController::class, 'updateProvider'])->name('billing.provider.update');

    // Admin Accounting & ERP Routes
    Route::post('/{clubSlug}/admin/accounting/accounts', [AccountingSettingsController::class, 'storeAccount'])->name('admin.accounting.accounts.store');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/store', [AccountingGatewayController::class, 'storeBankAccount'])->name('admin.accounting.bank_accounts.store');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/paypal/connect', [AccountingGatewayController::class, 'connectPayPal'])->name('admin.accounting.bank_accounts.paypal.connect');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/paypal/test', [AccountingGatewayController::class, 'testPayPalConnection'])->name('admin.accounting.bank_accounts.paypal.test');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/paypal/sync', [AccountingGatewayController::class, 'syncPayPalTransactions'])->name('admin.accounting.bank_accounts.paypal.sync');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/stripe/connect', [AccountingGatewayController::class, 'connectStripe'])->name('admin.accounting.bank_accounts.stripe.connect');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/stripe/sync', [AccountingGatewayController::class, 'syncStripeTransactions'])->name('admin.accounting.bank_accounts.stripe.sync');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/sumup/connect', [AccountingGatewayController::class, 'connectSumUp'])->name('admin.accounting.bank_accounts.sumup.connect');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/sumup/sync', [AccountingGatewayController::class, 'syncSumUpTransactions'])->name('admin.accounting.bank_accounts.sumup.sync');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/gocardless/connect', [AccountingGatewayController::class, 'connectGoCardless'])->name('admin.accounting.bank_accounts.gocardless.connect');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/gocardless/sync', [AccountingGatewayController::class, 'syncGoCardlessTransactions'])->name('admin.accounting.bank_accounts.gocardless.sync');
    Route::post('/{clubSlug}/admin/accounting/giftaid/reconcile-auto', [AccountingExportController::class, 'autoReconcileGiftAid'])->name('admin.accounting.giftaid.reconcile_auto');
    Route::get('/{clubSlug}/admin/accounting/giftaid/export-schedule', [AccountingExportController::class, 'exportGiftAidSchedule'])->name('admin.accounting.giftaid.export_schedule');
    Route::get('/{clubSlug}/admin/accounting/giftaid/reconciled-donations', [AccountingExportController::class, 'filterReconciledDonations'])->name('admin.accounting.giftaid.reconciled_donations');
    // The Gift Aid ledger lives under Charity; the old Accounting address still works.
    Route::redirect('/{clubSlug}/admin/accounting/giftaid/transactions', '/{clubSlug}/admin/charity/gift-aid-transactions');
    Route::get('/{clubSlug}/admin/charity/gift-aid-transactions', [CharityAdminController::class, 'giftAidTransactionsPage'])->name('admin.charity.giftaid.transactions_page');
    Route::post('/{clubSlug}/admin/accounting/bank-accounts/{id}/toggle', [AccountingGatewayController::class, 'toggleBankAccount'])->name('admin.accounting.bank_accounts.toggle');
    Route::post('/{clubSlug}/admin/accounting/opening-balance', [AccountingSettingsController::class, 'storeOpeningBalance'])->name('admin.accounting.opening_balance.store');
    Route::post('/{clubSlug}/admin/accounting/vat-settings', [AccountingSettingsController::class, 'updateVatSettings'])->name('admin.accounting.vat_settings.update');
    Route::get('/{clubSlug}/admin/accounting/export-all', [AccountingExportController::class, 'exportAccountingData'])->name('admin.accounting.export_all');
    Route::post('/{clubSlug}/admin/accounting/charity-commission', [AccountingSettingsController::class, 'updateCharityCommission'])->name('admin.accounting.charity_commission.update');
    Route::post('/{clubSlug}/admin/accounting/independent-examiner-report', [AccountingSettingsController::class, 'requestIndependentExamination'])->name('admin.accounting.independent_examiner.request');
    Route::post('/{clubSlug}/admin/accounting/independent-examiner-report/resend', [AccountingSettingsController::class, 'resendIndependentExaminerSignature'])->name('admin.accounting.independent_examiner.resend');
    Route::get('/{clubSlug}/admin/accounting/independent-examiner-report/export-pdf', [AccountingExportController::class, 'exportIndependentExaminerReportPdf'])->name('admin.accounting.independent_examiner.export_pdf');
    Route::post('/{clubSlug}/admin/accounting/approval-threshold', [AccountingSettingsController::class, 'updateApprovalThreshold'])->name('admin.accounting.approval_threshold.update');
    Route::post('/{clubSlug}/admin/accounting/financial-year-end', [AccountingSettingsController::class, 'updateFinancialYearEndMonth'])->name('admin.accounting.financial_year_end.update');
    Route::post('/{clubSlug}/admin/accounting/onboarding/dismiss', [AccountingSettingsController::class, 'dismissOnboarding'])->name('admin.accounting.onboarding.dismiss');
    // GET; resolved to view_accounting (not manage_billing) via the path override in ClubPermissions::capabilityForPath().
    Route::get('/{clubSlug}/admin/accounting/vat-return/export', [AccountingExportController::class, 'exportVatReturn'])->name('admin.accounting.vat_return.export');
    Route::post('/{clubSlug}/admin/accounting/fixed-assets', [AccountingAssetController::class, 'storeFixedAsset'])->name('admin.accounting.fixed_assets.store');
    Route::post('/{clubSlug}/admin/accounting/fixed-assets/{id}/dispose', [AccountingAssetController::class, 'disposeFixedAsset'])->name('admin.accounting.fixed_assets.dispose');
    Route::post('/{clubSlug}/admin/accounting/fixed-assets/run-depreciation', [AccountingAssetController::class, 'runFixedAssetDepreciation'])->name('admin.accounting.fixed_assets.run_depreciation');
    Route::post('/{clubSlug}/admin/accounting/budget', [AccountingAssetController::class, 'updateBudget'])->name('admin.accounting.budget.update');
    Route::post('/{clubSlug}/admin/accounting/financial-year/close', [AccountingYearEndController::class, 'closeFinancialYear'])->name('admin.accounting.financial_year.close');
    Route::post('/{clubSlug}/admin/accounting/financial-year/reopen', [AccountingYearEndController::class, 'reopenFinancialYear'])->name('admin.accounting.financial_year.reopen');
    Route::post('/{clubSlug}/admin/accounting/financial-year/audit', [AccountingYearEndController::class, 'signOffYearAudit'])->name('admin.accounting.financial_year.audit');
    Route::post('/{clubSlug}/admin/accounting/financial-year/audit/{purpose}/resend', [AccountingYearEndController::class, 'resendYearAuditSignature'])->name('admin.accounting.financial_year.audit.resend');
    Route::post('/{clubSlug}/admin/accounting/financial-year/audit/{purpose}/cancel', [AccountingYearEndController::class, 'cancelYearAuditSignature'])->name('admin.accounting.financial_year.audit.cancel');
    // Under /admin/accounting so it inherits the manage_billing capability from the route map, like every other accounting page.
    Route::get('/{clubSlug}/admin/accounting/signatures/{id}/pdf', [SignatureAdminController::class, 'downloadPdf'])->name('admin.accounting.signatures.pdf');
    Route::get('/{clubSlug}/admin/accounting/reports/{report}/export', [AccountingExportController::class, 'exportReport'])->name('admin.accounting.reports.export');
    Route::get('/{clubSlug}/admin/accounting/reports/{report}/export-pdf', [AccountingExportController::class, 'exportReportPdf'])->name('admin.accounting.reports.export_pdf');
    Route::get('/{clubSlug}/admin/accounting/activity/{entityType}/{entityId}', [AccountingAdminController::class, 'activityLog'])->name('admin.accounting.activity_log');
    Route::get('/{clubSlug}/admin/accounting/treasurer-report/export-pdf', [AccountingExportController::class, 'exportAnnualTreasurerReportPdf'])->name('admin.accounting.treasurer_report.export_pdf');
    Route::get('/{clubSlug}/admin/accounting/treasurer-report/export-csv', [AccountingExportController::class, 'exportAnnualTreasurerReportCsv'])->name('admin.accounting.treasurer_report.export_csv');
    Route::post('/{clubSlug}/admin/accounting/recurring-bills', [AccountingAssetController::class, 'storeRecurringBillTemplate'])->name('admin.accounting.recurring_bills.store');
    Route::post('/{clubSlug}/admin/accounting/recurring-bills/{id}/toggle', [AccountingAssetController::class, 'toggleRecurringBillTemplate'])->name('admin.accounting.recurring_bills.toggle');
    Route::delete('/{clubSlug}/admin/accounting/recurring-bills/{id}', [AccountingAssetController::class, 'destroyRecurringBillTemplate'])->name('admin.accounting.recurring_bills.destroy');
    Route::get('/{clubSlug}/admin/accounting/journal-entries/create', [AccountingJournalController::class, 'createJournal'])->name('admin.accounting.journal.create');
    Route::post('/{clubSlug}/admin/accounting/journal-entries', [AccountingJournalController::class, 'storeJournalEntry'])->name('admin.accounting.journal.store');
    Route::get('/{clubSlug}/admin/accounting/journal-entries/{id}/edit', [AccountingJournalController::class, 'editJournalEntry'])->name('admin.accounting.journal.edit');
    Route::put('/{clubSlug}/admin/accounting/journal-entries/{id}', [AccountingJournalController::class, 'updateJournalEntry'])->name('admin.accounting.journal.update');
    Route::get('/{clubSlug}/admin/accounting/invoices/create', [AccountingInvoiceController::class, 'createInvoice'])->name('admin.accounting.invoices.create');
    Route::get('/{clubSlug}/admin/accounting/invoices/{id}/edit', [AccountingInvoiceController::class, 'editInvoice'])->name('admin.accounting.invoices.edit');
    Route::post('/{clubSlug}/admin/accounting/invoices', [AccountingInvoiceController::class, 'storeInvoice'])->name('admin.accounting.invoices.store');
    Route::put('/{clubSlug}/admin/accounting/invoices/{id}', [AccountingInvoiceController::class, 'updateInvoice'])->name('admin.accounting.invoices.update');
    Route::delete('/{clubSlug}/admin/accounting/invoices/{id}', [AccountingInvoiceController::class, 'destroyInvoice'])->name('admin.accounting.invoices.destroy');
    Route::post('/{clubSlug}/admin/accounting/invoices/{id}/publish', [AccountingInvoiceController::class, 'publishInvoice'])->name('admin.accounting.invoices.publish');
    Route::post('/{clubSlug}/admin/accounting/invoices/{id}/pay', [AccountingInvoiceController::class, 'markInvoicePaid'])->name('admin.accounting.invoices.pay');
    Route::post('/{clubSlug}/admin/accounting/invoices/{id}/approve', [AccountingInvoiceController::class, 'approveInvoice'])->name('admin.accounting.invoices.approve');
    Route::get('/{clubSlug}/admin/accounting/attachments/{mediaId}', [AccountingAdminController::class, 'showAttachment'])->name('admin.accounting.attachments.show');
    Route::delete('/{clubSlug}/admin/accounting/invoices/{id}/attachment', [AccountingInvoiceController::class, 'deleteInvoiceAttachment'])->name('admin.accounting.invoices.attachment.destroy');
    Route::get('/{clubSlug}/admin/accounting/bills/create', [AccountingBillController::class, 'createBill'])->name('admin.accounting.bills.create');
    Route::post('/{clubSlug}/admin/accounting/bills', [AccountingBillController::class, 'storeBill'])->name('admin.accounting.bills.store');
    Route::get('/{clubSlug}/admin/accounting/bills/{id}/edit', [AccountingBillController::class, 'editBill'])->name('admin.accounting.bills.edit');
    Route::put('/{clubSlug}/admin/accounting/bills/{id}', [AccountingBillController::class, 'updateBill'])->name('admin.accounting.bills.update');
    Route::post('/{clubSlug}/admin/accounting/bills/{id}/publish', [AccountingBillController::class, 'publishBill'])->name('admin.accounting.bills.publish');
    Route::post('/{clubSlug}/admin/accounting/bills/{id}/pay', [AccountingBillController::class, 'markBillPaid'])->name('admin.accounting.bills.pay');
    Route::post('/{clubSlug}/admin/accounting/bills/{id}/approve', [AccountingBillController::class, 'approveBill'])->name('admin.accounting.bills.approve');
    Route::delete('/{clubSlug}/admin/accounting/bills/{id}/attachment', [AccountingBillController::class, 'deleteBillAttachment'])->name('admin.accounting.bills.attachment.destroy');
    Route::delete('/{clubSlug}/admin/accounting/bills/{id}', [AccountingBillController::class, 'destroyBill'])->name('admin.accounting.bills.destroy');
    Route::get('/{clubSlug}/admin/accounting/contacts/create', [AccountingContactController::class, 'createContact'])->name('admin.accounting.contacts.create');
    Route::get('/{clubSlug}/admin/accounting/contacts/member/{userId}/edit', [AccountingContactController::class, 'editMemberContact'])->name('admin.accounting.contacts.member.edit');
    Route::get('/{clubSlug}/admin/accounting/contacts/{id}/edit', [AccountingContactController::class, 'editContact'])->name('admin.accounting.contacts.edit');
    Route::post('/{clubSlug}/admin/accounting/contacts', [AccountingContactController::class, 'storeContact'])->name('admin.accounting.contacts.store');
    Route::put('/{clubSlug}/admin/accounting/contacts/{id}', [AccountingContactController::class, 'updateContact'])->name('admin.accounting.contacts.update');
    Route::delete('/{clubSlug}/admin/accounting/contacts/{id}', [AccountingContactController::class, 'destroyContact'])->name('admin.accounting.contacts.destroy');
    Route::post('/{clubSlug}/admin/accounting/reconcile', [AccountingBankController::class, 'reconcileBankTransaction'])->name('admin.accounting.reconcile');
    Route::delete('/{clubSlug}/admin/accounting/reconcile/rules/{id}', [AccountingBankController::class, 'forgetBankMatchRule'])->name('admin.accounting.match_rules.destroy');
    Route::post('/{clubSlug}/admin/accounting/ignore-transaction', [AccountingBankController::class, 'ignoreBankTransaction'])->name('admin.accounting.ignore_transaction');
    Route::post('/{clubSlug}/admin/accounting/statement-lines/delete', [AccountingBankController::class, 'deleteBankStatementLines'])->name('admin.accounting.statement_lines.delete');
    Route::post('/{clubSlug}/admin/accounting/statement-lines/restore', [AccountingBankController::class, 'restoreBankStatementLines'])->name('admin.accounting.statement_lines.restore');
    Route::post('/{clubSlug}/admin/accounting/account-transactions/remove-and-redo', [AccountingBankController::class, 'removeAndRedoAccountTransactions'])->name('admin.accounting.account_transactions.remove_redo');
    Route::post('/{clubSlug}/admin/accounting/import-statement', [AccountingBankController::class, 'importBankStatement'])->name('admin.accounting.import_statement');
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
    Route::get('/{clubSlug}/admin/members/import', MemberImportPage::class)->name('admin.club_acc.members.import');
    Route::get('/{clubSlug}/admin/members/create', MemberFormPage::class)->name('admin.club_acc.members.create');
    Route::get('/{clubSlug}/admin/members/{memberId}/edit', MemberFormPage::class)->whereNumber('memberId')->name('admin.club_acc.members.edit');
    Route::get('/{clubSlug}/admin/members/{memberId}', MemberProfile::class)->name('admin.club_acc.members.show');
    Route::get('/{clubSlug}/admin/candidates', CandidatePipeline::class)->name('admin.club_acc.candidates.index');
    Route::get('/{clubSlug}/admin/candidates/{candidateId}', CandidateDetail::class)->whereNumber('candidateId')->name('admin.club_acc.candidates.show');
    // Under /admin/candidates so it inherits the manage_members capability from the route map, like every other candidate page.
    Route::get('/{clubSlug}/admin/candidates/signatures/{id}/pdf', [SignatureAdminController::class, 'downloadPdf'])->name('admin.candidates.signatures.pdf');
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
    Route::get('/members/signed-documents', [MemberSignatureController::class, 'history'])->name('members.signed_documents');

    Route::get('/members/{slug}', [MemberPortalController::class, 'show'])->name('member.dashboard');
    Route::get('/members/{slug}/calendar', [MemberCalendarController::class, 'show'])->name('member.calendar');
    Route::get('/members/{slug}/news', [MemberListController::class, 'news'])->name('member.news');
    Route::get('/members/{slug}/meetings', [MemberListController::class, 'meetings'])->name('member.meetings');
    Route::post('/members/{slug}/meetings/{id}/quick-rsvp', [QuickRsvpController::class, 'meeting'])->name('member.meetings.quick_rsvp');
    Route::post('/members/{slug}/events/{id}/pay', [EventOnlinePaymentController::class, 'member'])->name('member.events.pay');
    Route::get('/members/{slug}/events/{id}/paypal-return', [EventOnlinePaymentController::class, 'memberPayPalReturn'])->name('member.events.paypal_return');
    Route::post('/members/{slug}/events/{id}/quote', [EventQuoteController::class, 'member'])->name('member.events.quote');
    Route::post('/members/{slug}/events/{id}/quick-rsvp', [QuickRsvpController::class, 'event'])->name('member.events.quick_rsvp');
    Route::get('/members/{slug}/events', [MemberPortalController::class, 'events'])->name('member.events');
    Route::get('/members/{slug}/news/{id}', [MemberPortalController::class, 'showPost'])->name('member.posts.show');
    Route::get('/members/{slug}/dues', [MemberProfileController::class, 'dues'])->name('member.dues');
    Route::get('/members/{slug}/profile', [MemberProfileController::class, 'profile'])->name('member.profile');
    Route::post('/members/{slug}/profile', [MemberProfileController::class, 'updateProfile'])->name('member.profile.update');
    Route::post('/members/{slug}/events/{id}/rsvp', [MemberRsvpController::class, 'updateRsvp'])->name('member.rsvp');
    Route::get('/members/{slug}/meetings/{id}/summons', [MemberMeetingController::class, 'summons'])->name('member.meetings.summons');
    Route::post('/members/{slug}/meetings/{id}/rsvp', [MemberRsvpController::class, 'updateMeetingRsvp'])->name('member.meetings.rsvp');
    Route::get('/members/{slug}/meetings/{id}/pdf', [MemberMeetingController::class, 'downloadMeetingPdf'])->name('member.meetings.pdf');
    Route::get('/members/{slug}/sign/{id}', [MemberSignatureController::class, 'show'])->name('member.signatures.show');
    Route::post('/members/{slug}/sign/{id}', [MemberSignatureController::class, 'store'])->name('member.signatures.store');
    Route::post('/members/{slug}/sign/{id}/decline', [MemberSignatureController::class, 'decline'])->name('member.signatures.decline');
    Route::get('/members/{slug}/sign/{id}/pdf', [MemberSignatureController::class, 'downloadPdf'])->name('member.signatures.pdf');

    // Invite-Only Member Approval & Rejection Routes
    Route::post('/{slug}/members/{userId}/approve', [ClubController::class, 'approveMember'])->name('clubs.members.approve')->middleware('club.admin:manage_members');
    Route::post('/{slug}/members/{userId}/reject', [ClubController::class, 'rejectMember'])->name('clubs.members.reject')->middleware('club.admin:manage_members');

    // Admin Attendance Check-In Routes
    Route::get('/{clubSlug}/admin/events/{id}/checkin', [AttendanceController::class, 'show'])->name('admin.events.checkin');
    Route::post('/{clubSlug}/admin/events/{id}/checkin', [AttendanceController::class, 'checkIn'])->name('admin.events.checkin.store');
    Route::get('/{clubSlug}/admin/email-templates', [ClubEmailTemplateController::class, 'index'])->name('admin.email_templates.index');
    Route::put('/{clubSlug}/admin/email-templates/{key}', [ClubEmailTemplateController::class, 'update'])->name('admin.email_templates.update');
    Route::delete('/{clubSlug}/admin/email-templates/{key}', [ClubEmailTemplateController::class, 'destroy'])->name('admin.email_templates.destroy');
    Route::post('/{clubSlug}/admin/email-templates/{key}/test', [ClubEmailTemplateController::class, 'test'])->middleware('throttle:10,1')->name('admin.email_templates.test');
    Route::post('/{clubSlug}/admin/payment-options/stripe/connect', [StripeConnectController::class, 'connect'])->name('admin.payment_options.stripe.connect');
    Route::post('/{clubSlug}/admin/payment-options/stripe/express', [StripeConnectController::class, 'express'])->name('admin.payment_options.stripe.express');
    Route::get('/{clubSlug}/admin/payment-options/stripe/refresh', [StripeConnectController::class, 'refresh'])->name('admin.payment_options.stripe.refresh');
    Route::get('/{clubSlug}/admin/payment-options/stripe/return', [StripeConnectController::class, 'done'])->name('admin.payment_options.stripe.return');
    Route::post('/{clubSlug}/admin/payment-options/stripe/terms', [StripeConnectController::class, 'acceptTerms'])->name('admin.payment_options.stripe.terms');
    Route::post('/{clubSlug}/admin/payment-options/stripe/disconnect', [StripeConnectController::class, 'disconnect'])->name('admin.payment_options.stripe.disconnect');
    Route::get('/{clubSlug}/admin/payment-options', [PaymentOptionsController::class, 'index'])->name('admin.payment_options.index');
    Route::post('/{clubSlug}/admin/payment-options', [PaymentOptionsController::class, 'store'])->name('admin.payment_options.store');
    Route::post('/{clubSlug}/admin/payment-options/order', [PaymentOptionsController::class, 'reorder'])->name('admin.payment_options.order');
    Route::put('/{clubSlug}/admin/payment-options/{id}', [PaymentOptionsController::class, 'update'])->name('admin.payment_options.update');
    Route::put('/{clubSlug}/admin/payment-options/{id}/active', [PaymentOptionsController::class, 'toggle'])->name('admin.payment_options.toggle');
    Route::delete('/{clubSlug}/admin/payment-options/{id}', [PaymentOptionsController::class, 'destroy'])->name('admin.payment_options.destroy');
    Route::get('/{clubSlug}/admin/events/{id}/guest-list', [EventGuestListController::class, 'guestList'])->name('admin.events.guest_list');
    Route::get('/{clubSlug}/admin/events/{id}/catering', [EventGuestListController::class, 'catering'])->name('admin.events.catering');
    Route::get('/{clubSlug}/admin/events/{id}/export', [EventGuestListController::class, 'export'])->name('admin.events.export');
    Route::post('/{clubSlug}/admin/events/{id}/walk-in', [AttendanceController::class, 'walkIn'])->name('admin.events.checkin.walk_in');
});
