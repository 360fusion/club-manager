<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorAuthController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubDomainAllowedController;
use App\Http\Controllers\EventOnlinePaymentController;
use App\Http\Controllers\EventQuoteController;
use App\Http\Controllers\GoCardlessWebhookController;
use App\Http\Controllers\LegacyClubUrlController;
use App\Http\Controllers\LodgeCalendarController;
use App\Http\Controllers\LodgeDirectoryController;
use App\Http\Controllers\LodgeFeedController;
use App\Http\Controllers\NewsletterPublicController;
use App\Http\Controllers\PasswordlessRsvpController;
use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\PublicSiteExtrasController;
use App\Http\Controllers\SearchIndexController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\StripeConnectWebhookController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\VisitorRegistrationController;
use App\Models\Club;
use Illuminate\Support\Facades\Route;

// Public and sign-in routes: authentication, the public lodge directory and club websites, passwordless links, webhooks.
// Authentication Routes
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:auth-forms'])->name('verification.verify');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
// Fortify's own routes post the code and send people back here by this name (its views are switched off).
Route::get('/two-factor-challenge', [TwoFactorAuthController::class, 'showChallenge'])->name('two-factor.login');
// Unnamed: Fortify already registers a route named 'logout'. Two routes sharing a
// name is fatal to route:cache in production. Layouts post to the /logout URL
// directly, so this route needs no name of its own.
Route::post('/logout', [LoginController::class, 'destroy']);
// A link or another site must not be able to log someone out; visiting the URL just goes to the login page.
Route::get('/logout', fn () => redirect()->route('login'));

// Public Member Registration Routes
Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:auth-forms');

// Password Reset Routes
Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email')->middleware('throttle:auth-forms');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update')->middleware('throttle:auth-forms');

// Member Email Invitation Setup Routes
Route::get('/{slug}/invite/{token}', [InvitationController::class, 'showForm'])->name('invitation.accept')->middleware('throttle:auth-forms');
Route::post('/{slug}/invite/{token}', [InvitationController::class, 'accept'])->name('invitation.submit')->middleware('throttle:auth-forms');

// Passwordless Summons Email RSVP Routes
Route::get('/summons/rsvp/{token}', [PasswordlessRsvpController::class, 'show'])->name('summons.rsvp.show');
Route::post('/summons/rsvp/{token}', [PasswordlessRsvpController::class, 'store'])->name('summons.rsvp.store')->middleware('throttle:public-forms');

// Signature requests: no login, the emailed link's token is the credential.
Route::get('/sign/{token}', [SignatureController::class, 'show'])->name('sign.show')->middleware('throttle:auth-forms');
Route::post('/sign/{token}', [SignatureController::class, 'store'])->name('sign.store')->middleware('throttle:auth-forms');
Route::post('/sign/{token}/decline', [SignatureController::class, 'decline'])->name('sign.decline')->middleware('throttle:auth-forms');

// Multi-Tenant Public Admin & Workspace Landing Routes
Route::get('/', [ClubController::class, 'index'])->name('home');

// Legacy /clubs/... URLs, from before club slugs moved to the top level.
Route::any('/clubs/{legacySlug}/{path?}', LegacyClubUrlController::class)
    ->where('legacySlug', '[A-Za-z0-9_-]+')
    ->where('path', '.*');

// Caddy asks this before issuing a certificate for a lodge's own domain (answers the server itself only).
Route::get('/internal/domain-allowed', ClubDomainAllowedController::class)->name('internal.domain_allowed');

// What search engines read (robots.txt is a file in public/, shared by every host): before /{slug} and the /site/{club} routes.
Route::get('/sitemap.xml', [SearchIndexController::class, 'index'])->name('sitemap.index');

// The public lodge directory. Before the /site/{club} and /{slug} routes, and "lodges" is a reserved club slug.
Route::get('/lodges', [LodgeDirectoryController::class, 'index'])->middleware('throttle:120,1')->name('lodges.index');
Route::get('/lodges/halls/{slug}', [LodgeDirectoryController::class, 'hall'])->middleware('throttle:120,1')->name('lodges.hall');
Route::get('/lodges/{slug}/feed.xml', [LodgeFeedController::class, 'show'])->middleware('throttle:60,1')->name('lodges.feed');
Route::get('/lodges/{slug}/calendar.ics', [LodgeCalendarController::class, 'show'])->middleware('throttle:60,1')->name('lodges.ics');
Route::get('/lodges/{slug}', [LodgeDirectoryController::class, 'show'])->middleware('throttle:120,1')->name('lodges.show');

// A private copy of a newsletter, opened from "view in your browser" (before the /site/{club} routes so "newsletter" is never read as a club).
Route::get('/site/newsletter/{token}', [NewsletterPublicController::class, 'view'])->middleware('throttle:60,1')->name('newsletters.view');
Route::get('/site/oxford-boating', function () {
    $activeSlug = Club::first()?->slug ?? 'lodge-of-fraternity';

    return redirect('/site/'.$activeSlug, 301);
});

Route::get('/site/{clubSlug}/events/{eventSlug}', [PublicEventController::class, 'show'])->name('public.event');
Route::get('/site/{clubSlug}/news/{postSlug}', [PublicSiteController::class, 'showPost'])->name('public.site.post');
Route::post('/site/{clubSlug}/events/{eventSlug}/quote', [EventQuoteController::class, 'guest'])->name('public.event.quote')->middleware('throttle:public-forms');
Route::post('/site/{clubSlug}/events/{eventSlug}/register', [PublicEventController::class, 'register'])->name('public.event.register')->middleware('throttle:public-forms');
Route::get('/site/{clubSlug}/booking/{token}', [PublicEventController::class, 'booking'])->name('public.event.booking')->middleware('throttle:auth-forms');
Route::post('/site/{clubSlug}/booking/{token}/pay', [EventOnlinePaymentController::class, 'guest'])->name('public.event.booking.pay')->middleware('throttle:auth-forms');
Route::get('/site/{clubSlug}/booking/{token}/paypal-return', [EventOnlinePaymentController::class, 'guestPayPalReturn'])->name('public.event.booking.paypal_return')->middleware('throttle:auth-forms');
Route::post('/site/{clubSlug}/booking/{token}/cancel', [PublicEventController::class, 'cancel'])->name('public.event.booking.cancel')->middleware('throttle:auth-forms');
Route::get('/site/{clubSlug}/files/{mediaId}', [PublicSiteExtrasController::class, 'file'])->whereNumber('mediaId')->middleware('throttle:60,1')->name('public.site.file');
Route::get('/site/{clubSlug}/sitemap.xml', [SearchIndexController::class, 'club'])->middleware('throttle:60,1')->name('public.site.sitemap');
Route::get('/site/{clubSlug}/calendar.ics', [PublicSiteExtrasController::class, 'calendarFeed'])->middleware('throttle:60,1')->name('public.site.calendar_feed');
// The page slug matches any depth so an old nested address (/about/history) can still be redirected.
Route::get('/site/{clubSlug}/{pageSlug?}', [PublicSiteController::class, 'showPage'])->where('pageSlug', '.*')->name('public.site');
Route::post('/site/{clubSlug}/contact-form', [PublicSiteExtrasController::class, 'submitContactForm'])->name('public.site.contact_form')->middleware('throttle:public-forms');

Route::get('/{slug}/overview', [ClubController::class, 'show'])->name('clubs.show');
Route::get('/{slug}/visitor-register', [VisitorRegistrationController::class, 'create'])->name('clubs.visitor.register');
Route::post('/{slug}/visitor-register', [VisitorRegistrationController::class, 'store'])->name('clubs.visitor.store')->middleware('throttle:public-forms');

// Protected Authenticated Routes
// Local-only preview of the shared UI primitives.
if (app()->isLocal()) {
    Route::get('/ui-kit', fn () => inertia('UiKit'))->name('ui_kit');
}

// GoCardless posts here unauthenticated; the request is authenticated by its
// HMAC signature instead. Must stay outside the auth group and exempt from CSRF.
Route::post('/webhooks/stripe/{clubId}', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe.club');
Route::get('/webhooks/newsletters/unsubscribe/{token}', [NewsletterPublicController::class, 'unsubscribeForm'])->middleware('throttle:60,1')->name('newsletters.unsubscribe.form');
Route::post('/webhooks/newsletters/unsubscribe/{token}', [NewsletterPublicController::class, 'unsubscribe'])->middleware('throttle:60,1')->name('newsletters.unsubscribe');
Route::post('/webhooks/paypal/{clubId}', [PayPalWebhookController::class, 'handle'])->name('webhooks.paypal.club');
Route::post('/webhooks/stripe-connect', [StripeConnectWebhookController::class, 'handle'])->name('webhooks.stripe.connect');
Route::post('/webhooks/gocardless/{clubId}', [GoCardlessWebhookController::class, 'handle'])
    ->name('webhooks.gocardless');
