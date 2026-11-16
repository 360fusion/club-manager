---
title: "Features"
description: "What Launchpad gives a new Laravel project: accounts, teams, Stripe-ready billing, an admin, a default website and account closure."
updated: 2026-11-02
version: 2
noindex: false
---
# Features

Launchpad is a starter for Laravel projects with customer and admin accounts. This page lists what is in it today.

## Accounts and sign-in

- Sign-up, sign-in and password reset.
- Email verification, with a grace period so people can start before they confirm.
- Two-factor sign-in with an authenticator app.
- Sign-in with Google or Microsoft.
- A security page where people manage their password and two-factor settings.

## Teams and roles

- A team for each team, with members and invitations by email.
- Four roles: owner, admin, manager and member.
- Every route is classified by who may use it, and the package gives your tests a way to check that nothing is left out.

## Billing

- Plans, limits and features set in config. The admin can change limits without a deploy.
- Stripe checkout, including for someone who has not made an account yet.
- Plan changes and cancelling through Stripe's customer portal.
- A billing page, and webhooks that ignore duplicates.
- A doctor command, `launchpad:doctor`, that checks your setup.

Billing is built on Cashier and is Stripe-ready. Launchpad's own tests exercise it through a fake, so you run the test-mode check with your own Stripe account (the [developer guide](/for-developers) says how).

## Admin

A Filament admin, kept apart from customer sign-in. Staff have their own guard and must set up an authenticator app. It covers:

- Users, teams and subscriptions.
- Stripe events and an audit log of what staff have done.
- Plan limits, the contact inbox and help feedback.
- Site settings and a review step for the legal pages.

## A default website

- Home, features, about, pricing and contact pages. The contact form is guarded against spam and sends a reference to the sender.
- A help centre of 17 guides and an FAQ of 10 answers, both with search and feedback.
- Nine legal page templates, a cookie banner, a sitemap, robots.txt, llms.txt and security.txt.
- Pages are Markdown files with placeholders for your company details. You override one file at a time and the rest keep updating.

The legal pages are templates, not legal advice. A production site fails the doctor check until each one has been marked as reviewed.

## Account closure and privacy

- Customers can download their data.
- Customers can close their account. After a 30-day wait, the account's data is purged.
- A scheduled command does the purge, and `launchpad:purge-closed --dry-run` shows who is due.

## Built to be updated

- Laravel 13, PHP 8.3 or newer, and Filament 5.
- Tests that run on SQLite, MySQL 8.4 and Postgres 16.
- `launchpad:status` tells you when a page you copied has a newer default.

Ready to try it? [Read the developer guide](/for-developers) or [see pricing](/pricing).
