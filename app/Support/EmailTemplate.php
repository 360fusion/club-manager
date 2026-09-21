<?php

namespace App\Support;

use App\Models\Club;
use App\Models\ClubEmailTemplate;
use App\Models\DefaultEmailTemplate;

/**
 * Fills an editable email template: the lodge's own wording if it has set one, otherwise the platform default the
 * superadmin edits. Plain values are escaped; blocks that the app builds itself
 * (lists, payment details) are passed as HTML and inserted as they are. Returns null when the template has
 * been removed, so the caller can fall back to its built-in view.
 */
class EmailTemplate
{
    /**
     * The emails a lodge may reword for itself, with a placeholder the wording must keep (or null).
     * Anything not listed here stays with the platform.
     */
    public const LODGE_EDITABLE = [
        'event_booking_confirmation' => 'manage_url',
        'event_guest_confirmation' => null,
        'event_payment_reminder' => null,
        'event_payment_received' => null,
        'event_refund' => null,
        'event_place_available' => null,
    ];

    /**
     * @param  array<string, scalar|null>  $text  placeholder => plain text value
     * @param  array<string, string>  $html  placeholder => trusted HTML block
     * @return array{subject: string, body: string}|null
     */
    public static function render(string $key, array $text, array $html = [], ?Club $club = null): ?array
    {
        $template = ($club && array_key_exists($key, self::LODGE_EDITABLE) ? ClubEmailTemplate::where('club_id', $club->id)->where('template_key', $key)->first() : null)
            ?? DefaultEmailTemplate::where('template_key', $key)->first();

        if (! $template) {
            return null;
        }

        $subject = self::replace($template->subject, array_map(fn ($v) => (string) $v, $text));
        $body = self::replace($template->body_html, array_map(fn ($v) => e((string) $v), $text) + $html);

        return ['subject' => trim(str_replace(["\r", "\n"], ' ', $subject)), 'body' => $body];
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function replace(string $text, array $values): string
    {
        $pairs = [];

        foreach ($values as $name => $value) {
            $pairs['{{'.$name.'}}'] = $value;
        }

        return strtr($text, $pairs);
    }
}
