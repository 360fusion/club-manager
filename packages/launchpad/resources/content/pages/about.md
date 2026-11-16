---
title: "About"
description: "Why Launchpad exists: a stand-alone starter for Laravel projects with customer and admin accounts, built to stay easy to update."
updated: 2026-11-02
version: 2
noindex: false
---
# About Launchpad

Launchpad is a starter for Laravel projects with customer and admin accounts. It exists because nearly every new product begins with the same work: sign-up, teams, billing, a back office and a public website. That work is rarely what makes a product special, and it is easy to get slightly wrong.

So Launchpad does it once, carefully, and lets each new project start from there.

## How it is built

- **Stand-alone.** It is a Composer package. It knows nothing about any particular product, and your product's code lives in your own project.
- **Easy to update.** You change one page or view at a time, and everything else keeps getting fixes when you update the package.
- **Tested.** The tests run on SQLite, MySQL and Postgres.
- **Plain.** Roles are a column and policies. Tenancy is a column too. There is no clever machinery to learn.

## What we care about

- **Clear pricing.** Plans, prices and limits are in config where you can read them.
- **Your customers' data is theirs.** People can download it and close their account, and the data is purged after a wait.
- **Honest limits.** The legal pages are templates, not legal advice, and a production site is stopped until they are reviewed. Stripe is exercised through a fake in the tests, so you check it with your own account.

## Where to go next

Read the [features](/features), the [developer guide](/for-developers) or the [help centre](/help). Questions? [Contact us](/contact).
