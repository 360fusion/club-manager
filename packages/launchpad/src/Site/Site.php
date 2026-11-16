<?php

namespace Launchpad\Site;

use Illuminate\Support\Str;
use Launchpad\Models\Setting;

/**
 * Who the site is: the company, the product, the contact addresses. The admin edits them (Setting site_<key>); the config's value is the
 * default. Pages and mails use them as {{tokens}} so almost no build has to edit a page.
 */
final class Site
{
    /** The keys the admin can set, with the label it shows */
    public const FIELDS = [
        'product' => 'Product name',
        'company' => 'Company (legal name)',
        'tagline' => 'Tagline',
        'support_email' => 'Support email',
        'dpo_email' => 'Privacy contact email',
        'address' => 'Company postal address',
        'jurisdiction' => 'Governing law (for example England and Wales)',
        'effective_date' => 'Date the Terms and Privacy Policy took effect',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $stored = Setting::get('site_'.$key);

        if (is_string($stored) && trim($stored) !== '') {
            return $stored;
        }

        $configured = match ($key) {
            'product' => config('launchpad.site.product') ?: config('launchpad.site.name'),
            'site_url' => config('launchpad.site.url') ?: config('app.url'),
            'retention_days' => (string) config('launchpad.accounts.retention_days'),
            default => config("launchpad.site.{$key}"),
        };

        return is_scalar($configured) && trim((string) $configured) !== '' ? (string) $configured : $default;
    }

    /** @return array<string, string> every token a page can use, with its value (missing ones are absent) */
    public static function tokens(): array
    {
        $tokens = [];

        foreach (array_merge(array_keys(self::FIELDS), ['site_url', 'retention_days']) as $key) {
            if (($value = self::get($key)) !== null) {
                $tokens[$key] = $value;
            }
        }

        if (isset($tokens['product'])) {
            $tokens['product'] = Str::of($tokens['product'])->toString();
        }

        return $tokens;
    }

    /** The name the pages are headed with */
    public static function name(): string
    {
        return self::get('product') ?? 'Launchpad';
    }
}
