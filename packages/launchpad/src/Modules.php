<?php

namespace Launchpad;

/**
 * Which of config launchpad.modules an app has on, and which migrations that makes it run: a module that is off leaves its tables out. A migration
 * not listed here (users, settings, email templates) always runs. Turning a module on later is `php artisan migrate`.
 */
final class Modules
{
    /** the tables of a workspace are needed by every module that stores something per workspace */
    private const TEAMS = ['workspaces', 'billing', 'api', 'webhooks', 'files', 'sso', 'scim', 'sync'];

    /** migration name (without date and number) => the modules that need it: it runs when any of them is on */
    private const MIGRATIONS = [
        'create_social_accounts_table' => ['social'],
        'create_workspaces_tables' => self::TEAMS,
        'add_permissions_to_memberships' => self::TEAMS,
        'add_permissions_to_invitations' => self::TEAMS,
        'add_role_permissions_to_workspaces' => self::TEAMS,
        // made and then dropped again by use_pennant_for_features; its foreign key needs the team table
        'create_workspace_features_table' => self::TEAMS,
        'add_onboarding_dismissed_to_memberships' => self::TEAMS,
        'create_custom_roles_table' => self::TEAMS,
        'create_workspace_events_table' => self::TEAMS,
        'add_late_columns_to_workspaces' => self::TEAMS,
        'create_sync_events_and_legacy_ids' => ['sync'],
        'create_billing_tables' => ['billing'],
        'create_usage_counts_table' => ['billing'],
        'create_api_tokens_and_webhooks_tables' => ['api', 'webhooks'],
        'move_api_tokens_to_sanctum' => ['api'],
        'create_api_token_usage_table' => ['api'],
        'add_replaced_at_to_personal_access_tokens' => ['api'],
        'create_workspace_files_table' => ['files'],
        'create_sso_connections_table' => ['sso', 'scim'],
        'let_unverified_sso_domains_repeat' => ['sso', 'scim'],
        'add_scim_provisioning' => ['sso', 'scim'],
        'add_saml_to_sso_connections' => ['sso', 'scim'],
        'create_passkeys_table' => ['passkeys'],
        'create_logged_errors_table' => ['admin'],
        'create_reports_table' => ['moderation'],
        'create_blog_posts_table' => ['blog'],
        'create_roadmap_tables' => ['roadmap'],
        'create_waitlist_entries_table' => ['waitlist'],
    ];

    /** what the code assumes when the key is missing from an app's older config/launchpad.php */
    private const ON_BY_DEFAULT = ['scim', 'passkeys', 'mcp', 'login_links'];

    public static function enabled(string $module): bool
    {
        return (bool) config("launchpad.modules.{$module}", in_array($module, self::ON_BY_DEFAULT, true));
    }

    public static function migrates(string $file): bool
    {
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($file, '.php'));

        return ! isset(self::MIGRATIONS[$name]) || array_filter(self::MIGRATIONS[$name], self::enabled(...)) !== [];
    }
}
