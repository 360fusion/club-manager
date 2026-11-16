@extends('launchpad::layouts.base')
@section('title', \Launchpad\Site\Site::get('tagline') ?? __('A starter for Laravel projects with customer accounts and a staff HQ'))
@section('description', \Launchpad\Site\Site::get('tagline') ?? __('Launchpad gives a new Laravel project customer accounts, teams, Stripe-ready billing, a staff HQ and a default website, so you can start on the part that is yours.'))
@section('main-class', 'lp-main--home')
@section('content')
<section class="lp-card lp-hero lp-home-hero" aria-labelledby="home-title">
    <h1 id="home-title">{{ \Launchpad\Site\Site::get('tagline') ?? __('Start your Laravel project with accounts, billing and a staff HQ already done') }}</h1>
    <p class="lp-home-lede">{{ __('Launchpad is a starter for Laravel projects with customer accounts and a staff HQ. Add it to a new app and get on with the part that is yours.') }}</p>
    <p class="lp-home-actions">
        <a class="lp-btn" href="{{ route('register') }}">{{ __('Start free') }}</a>
        @if (Route::has('pricing'))<a class="lp-btn lp-btn--quiet" href="{{ route('pricing') }}">{{ __('See pricing') }}</a>@endif
    </p>
    <pre class="lp-code" aria-label="{{ __('Install commands') }}"><code>composer require 360fusion/launchpad
php artisan launchpad:install --migrate</code></pre>
</section>

<section class="lp-home-section" aria-labelledby="home-what">
    <h2 id="home-what">{{ __('What it is') }}</h2>
    <p>{{ __('Most new projects begin with the same weeks of work: sign-up and sign-in, teams, taking payment, a back office and a public website. Launchpad is a Composer package that adds all of it to a fresh Laravel app. It stands on its own, it brings its own tests, and it keeps your project easy to update, because you only copy a page or a view when you want to change it.') }}</p>
</section>

<section class="lp-home-section" aria-labelledby="home-features">
    <h2 id="home-features">{{ __('What you get') }}</h2>
    <ul class="lp-grid">
        <li class="lp-card">
            <h3>{{ __('Accounts and sign-in') }}</h3>
            <p>{{ __('Sign-up, sign-in, password reset and email verification with a grace period. Two-factor sign-in, Google and Microsoft login, and a security page.') }}</p>
        </li>
        <li class="lp-card">
            <h3>{{ __('Teams and roles') }}</h3>
            <p>{{ __('Teams with members, invitations by email and four roles: owner, admin, manager and member. Every route is classified, and a test checks it.') }}</p>
        </li>
        <li class="lp-card">
            <h3>{{ __('Stripe-ready billing') }}</h3>
            <p>{{ __('Plans and limits, Stripe checkout (even before an account exists), plan changes and cancelling through Stripe\'s portal, and webhooks that ignore duplicates.') }}</p>
        </li>
        <li class="lp-card">
            <h3>{{ __('A staff HQ') }}</h3>
            <p>{{ __('A Filament HQ for users, teams, subscriptions, Stripe events, plan limits, contact messages and site settings. Staff sign in separately and must use an authenticator app.') }}</p>
        </li>
        <li class="lp-card">
            <h3>{{ __('A default website') }}</h3>
            <p>{{ __('Home, features, pricing, about and contact, plus a help centre and FAQ with search. Pages are Markdown, so you change one file at a time.') }}</p>
        </li>
        <li class="lp-card">
            <h3>{{ __('Account closure and privacy') }}</h3>
            <p>{{ __('Customers can download their data and close their account. After a 30-day wait, the data is purged. Legal pages come as templates, and the cookie banner is built in.') }}</p>
        </li>
    </ul>
</section>

<section class="lp-home-section" aria-labelledby="home-sides">
    <h2 id="home-sides">{{ __('Two sides') }}</h2>
    <div class="lp-sides">
        <div class="lp-card">
            <h3>{{ __('What your customers get') }}</h3>
            <ul>
                <li>{{ __('An account they can set up in a minute, and protect with two-factor sign-in.') }}</li>
                <li>{{ __('A team to invite people into, with clear roles.') }}</li>
                <li>{{ __('A billing page to see their plan, change it or cancel it.') }}</li>
                <li>{{ __('A help centre and FAQ that answer common questions.') }}</li>
                <li>{{ __('Data export and account closure when they want them.') }}</li>
            </ul>
        </div>
        <div class="lp-card">
            <h3>{{ __('What your admins get') }}</h3>
            <ul>
                <li>{{ __('One place to find users, teams and subscriptions.') }}</li>
                <li>{{ __('Stripe events and an audit log of what staff have done.') }}</li>
                <li>{{ __('Plan limits you can change without a deploy.') }}</li>
                <li>{{ __('A contact inbox and help feedback to read and answer.') }}</li>
                <li>{{ __('Site settings and page review for the legal text.') }}</li>
            </ul>
        </div>
    </div>
</section>

<section class="lp-home-section" aria-labelledby="home-start">
    <h2 id="home-start">{{ __('How you start') }}</h2>
    <ol class="lp-steps">
        <li class="lp-card">
            <h3>{{ __('Add the package') }}</h3>
            <pre class="lp-code"><code>composer require 360fusion/launchpad</code></pre>
        </li>
        <li class="lp-card">
            <h3>{{ __('Run the installer') }}</h3>
            <pre class="lp-code"><code>php artisan launchpad:install --migrate</code></pre>
        </li>
        <li class="lp-card">
            <h3>{{ __('Make your first admin') }}</h3>
            <pre class="lp-code"><code>php artisan launchpad:make-admin you@example.com</code></pre>
        </li>
    </ol>
    <p>{{ \Launchpad\Support\Trans::html('The installer is safe to run twice. The :page explains what it changes and how updates work.', ['page' => new \Illuminate\Support\HtmlString('<a href="'.e(url('/for-developers')).'">'.e(__('developer page')).'</a>')]) }}</p>
</section>

@if (Route::has('pricing'))
<section class="lp-card lp-home-teaser" aria-labelledby="home-pricing">
    <h2 id="home-pricing">{{ __('Pricing') }}</h2>
    <p>{{ __('You can start on the free plan. Paid plans are set in your own config, with monthly or yearly prices and a per-seat option for teams.') }}</p>
    <p><a class="lp-btn lp-btn--quiet" href="{{ route('pricing') }}">{{ __('See pricing') }}</a></p>
</section>
@endif

<section class="lp-card lp-home-teaser" aria-labelledby="home-faq">
    <h2 id="home-faq">{{ __('Questions') }}</h2>
    <p>{{ __('Can I change plan? Where is my data kept? How do I close my account? The FAQ has short, plain answers.') }}</p>
    <p><a class="lp-btn lp-btn--quiet" href="{{ route('faq') }}">{{ __('Read the FAQ') }}</a></p>
</section>

<section class="lp-card lp-hero lp-home-cta" aria-labelledby="home-cta">
    <h2 id="home-cta">{{ __('Ready to start?') }}</h2>
    <p class="lp-muted">{{ __('Make an account for free and look around. Or read the pricing first.') }}</p>
    <p class="lp-home-actions">
        <a class="lp-btn" href="{{ route('register') }}">{{ __('Start free') }}</a>
        @if (Route::has('pricing'))<a class="lp-btn lp-btn--quiet" href="{{ route('pricing') }}">{{ __('See pricing') }}</a>@endif
    </p>
</section>
@endsection
