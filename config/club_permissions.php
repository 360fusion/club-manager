<?php

/**
 * Club role permissions.
 *
 * `capabilities` is the default matrix shown on the club settings screen and
 * used when a club has not customised its own. A club may override the roles
 * for any capability; overrides live in club.settings.permission_matrix.
 *
 * `route_map` maps the first segment after /{slug}/admin/ to the
 * capability that guards it. Prefixes absent from this map fall back to
 * requiring any staff role.
 */
return [

    'capabilities' => [
        'view_dashboard' => [
            'label' => 'Dashboard',
            'description' => 'Access analytics dashboard, revenue reports, and attendance statistics',
            'roles' => ['owner', 'admin', 'treasurer'],
        ],
        'manage_meetings' => [
            'label' => 'Meetings & Summonses',
            'description' => 'Create, edit, generate recurring rules, and publish summonses for lodge/club meetings',
            'roles' => ['owner', 'admin', 'secretary'],
        ],
        'manage_events' => [
            'label' => 'Events',
            'description' => 'Create, edit, and publish social events, ticketing tiers, and dining options',
            'roles' => ['owner', 'admin', 'coach'],
        ],
        'manage_subscriptions' => [
            'label' => 'Subscriptions',
            'description' => 'Configure membership dues tiers, pricing, and recurring subscription plans',
            'roles' => ['owner', 'admin', 'treasurer'],
        ],
        'manage_members' => [
            'label' => 'Members',
            'description' => 'View member roster, approve new join applications, and manage member roles',
            'roles' => ['owner', 'admin'],
        ],
        'manage_billing' => [
            'label' => 'Billing',
            'description' => 'Access payment history, issue receipts, and manage club platform billing',
            'roles' => ['owner', 'admin', 'treasurer'],
        ],
        'manage_communications' => [
            'label' => 'Communications',
            'description' => 'Create and publish community announcements, posts, and news updates',
            'roles' => ['owner', 'admin', 'coach'],
        ],
        'send_newsletters' => [
            'label' => 'Newsletters',
            'description' => 'Draft, preview, and dispatch bulk email newsletters to members',
            'roles' => ['owner', 'admin', 'coach'],
        ],
        'edit_website' => [
            'label' => 'Website Builder',
            'description' => 'Build CMS pages, customize navigation, and update site content',
            'roles' => ['owner', 'admin'],
        ],
        'manage_settings' => [
            'label' => 'Club Settings',
            'description' => 'Configure organization profile, branding, custom domain, and permission matrix',
            'roles' => ['owner', 'admin'],
        ],
    ],

    'route_map' => [
        '' => 'view_dashboard',
        'analytics' => 'view_dashboard',

        'accounting' => 'manage_billing',
        'bank-accounts' => 'manage_billing',
        'bank-imports' => 'manage_billing',
        'bank-reconciliation' => 'manage_billing',
        'reconciliation-workspace' => 'manage_billing',
        'billing' => 'manage_billing',
        'charity' => 'manage_billing',

        'subscriptions' => 'manage_subscriptions',
        'dues-subscriptions' => 'manage_subscriptions',

        'meetings' => 'manage_meetings',
        'officers' => 'manage_meetings',
        'committee' => 'manage_meetings',

        'members' => 'manage_members',
        'users' => 'manage_members',
        'candidates' => 'manage_members',

        'events' => 'manage_events',

        'newsletters' => 'send_newsletters',

        'posts' => 'manage_communications',
        'updates' => 'manage_communications',

        'pages' => 'edit_website',
        'media' => 'edit_website',
        'media-manager' => 'edit_website',

        'settings' => 'manage_settings',
    ],

    /**
     * Always permitted, regardless of the club's matrix. Without this a club
     * could edit its own matrix in a way that locked its owner out for good.
     */
    'always_allowed_roles' => ['owner'],

];
