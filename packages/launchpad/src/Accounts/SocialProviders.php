<?php

namespace Launchpad\Accounts;

/**
 * The providers people can sign in with, and which of them this server has keys for. A button is only drawn for those: a button for a
 * provider with no client id sends the person to the provider's own error page.
 */
final class SocialProviders
{
    /** The name in the URL (and in config/services.php) and the name a person knows it by */
    public const NAMES = ['google' => 'Google', 'facebook' => 'Facebook', 'azure' => 'Microsoft'];

    public static function known(string $provider): bool
    {
        return array_key_exists($provider, self::NAMES);
    }

    public static function name(string $provider): string
    {
        return self::NAMES[$provider] ?? ucfirst($provider);
    }

    /** Keys for it (not the example file's placeholder), and not switched off with `services.<provider>.login` = false */
    public static function configured(string $provider): bool
    {
        $id = (string) config("services.{$provider}.client_id");

        return config('launchpad.modules.social') && self::known($provider)
            && config("services.{$provider}.login", true) !== false
            && filled($id) && ! str_starts_with(strtolower($id), 'your-')
            && filled(config("services.{$provider}.client_secret"));
    }

    /** @return list<string> */
    public static function enabled(): array
    {
        return array_values(array_filter(array_keys(self::NAMES), self::configured(...)));
    }
}
