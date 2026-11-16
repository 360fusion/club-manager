<?php

namespace Launchpad\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Launchpad\Admin\AdminLog;
use Launchpad\Models\EmailTemplate;

/**
 * The mails the package sends, each with a key, a label, its default subject and Markdown body, the {{variables}} it may use and the ones
 * it must use (a link someone has to click). The defaults live here, so a fresh install needs no rows; a row in `email_templates` that
 * staff edited in the admin wins over the default. An app adds its own with Templates::register() (docs/email-templates.md).
 */
final class Templates
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $registry = null;

    /**
     * @param  array<string, string>  $variables  name => what it holds, shown to the editor
     * @param  list<string>  $required  variables that must appear in the subject or the body
     * @param  array<string, string>  $sample  name => a value for the admin's preview and test mail
     */
    public static function register(string $key, string $label, string $subject, string $body, array $variables = [], array $required = [], array $sample = []): void
    {
        self::$registry = self::all();
        self::$registry[$key] = compact('label', 'subject', 'body', 'variables', 'required', 'sample');
    }

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return self::$registry ??= self::defaults();
    }

    /** @return array<string, mixed> */
    public static function get(string $key): array
    {
        return self::all()[$key] ?? throw new InvalidArgumentException("No email template '{$key}'.");
    }

    /** The saved wording of one language (default: the language in use now) */
    public static function override(string $key, ?string $locale = null): ?EmailTemplate
    {
        return EmailTemplate::where('key', $key)->where('locale', $locale ?? app()->getLocale())->first();
    }

    /**
     * @return array{subject: string, body: string} what to send now, in the language ($locale, default the one in use now): what staff saved for
     *                                              that language, else the wording the package ships for it (resources/lang/<locale>/mail.php, entries '<key>.subject' and '<key>.body'), else the English default
     */
    public static function resolve(string $key, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $template = self::get($key);
        $row = self::override($key, $locale);

        return [
            'subject' => filled($row?->subject) ? $row->subject : self::shipped("{$key}.subject", $locale) ?? $template['subject'],
            'body' => filled($row?->body) ? $row->body : self::shipped("{$key}.body", $locale) ?? $template['body'],
        ];
    }

    /** A language's shipped wording, if the package has some: the file is read through the translator, so a build's lang/vendor/launchpad copy wins */
    private static function shipped(string $line, string $locale): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $locale)) {
            return null;
        }

        $translator = app('translator');
        $translator->addNamespace('launchpad', dirname(__DIR__, 2).'/resources/lang');

        return $translator->hasForLocale("launchpad::mail.{$line}", $locale) ? $translator->get("launchpad::mail.{$line}", [], $locale, false) : null;
    }

    /** @return list<string> the {{variables}} used in a text */
    public static function used(string $text): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $found);

        return array_values(array_unique($found[1]));
    }

    /** @return list<string> what is wrong with this wording: a variable the template does not have, or a required one left out */
    public static function problems(string $key, string $subject, string $body): array
    {
        $template = self::get($key);
        $used = self::used($subject.' '.$body);
        $problems = [];

        if (trim($subject) === '' || trim($body) === '') {
            $problems[] = 'The subject and the body cannot be empty.';
        }

        foreach (array_diff($used, array_keys($template['variables']), Renderer::GLOBALS) as $name) {
            $problems[] = '{'.'{'.$name.'}'.'} is not a variable of this email.';
        }

        foreach (array_diff($template['required'], $used) as $name) {
            $problems[] = 'The email must contain {'.'{'.$name.'}'.'}: without it the person cannot use it.';
        }

        return $problems;
    }

    /** Saves the wording as the override; refused (ValidationException) when a required variable is missing or an unknown one is used */
    public static function save(string $key, string $subject, string $body, ?Model $by = null, ?string $locale = null): EmailTemplate
    {
        $locale ??= app()->getLocale();

        if ($problems = self::problems($key, $subject, $body)) {
            throw ValidationException::withMessages(['body' => $problems]);
        }

        $before = self::resolve($key, $locale);
        $row = self::override($key, $locale) ?? new EmailTemplate(['key' => $key, 'locale' => $locale]);
        $row->fill(['subject' => $subject, 'body' => $body, 'updated_by' => ($by ?? AdminLog::actor())?->getKey()])->save();
        AdminLog::record('email_template.updated', $row, "Email template {$key}", $before, ['subject' => $subject, 'body' => $body]);

        return $row;
    }

    /** Back to the default wording */
    public static function reset(string $key, ?string $locale = null): void
    {
        if ($row = self::override($key, $locale)) {
            $row->delete();
            AdminLog::record('email_template.reset', $row, "Email template {$key}", ['subject' => $row->subject, 'body' => $row->body]);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private static function defaults(): array
    {
        $template = fn (string $label, string $subject, string $body, array $variables, array $required, array $sample) => compact('label', 'subject', 'body', 'variables', 'required', 'sample');

        return [
            'account.verify' => $template(
                'Verify email address',
                'Verify your email address',
                "Please click the button below to verify your email address.\n\n[Verify email address]({{url}})\n\nIf you did not create an account, no further action is required.",
                ['name' => 'The person\'s name', 'url' => 'The verification link'],
                ['url'],
                ['name' => 'Ada Lovelace', 'url' => 'https://example.test/verify-email/1/abc'],
            ),
            'account.reset_password' => $template(
                'Password reset',
                'Reset your password',
                "You are receiving this email because we received a password reset request for your account.\n\n[Reset password]({{url}})\n\nThis password reset link will expire in {{minutes}} minutes.\n\nIf you did not request a password reset, no further action is required.",
                ['name' => 'The person\'s name', 'url' => 'The reset link', 'minutes' => 'How long the link lasts, in minutes'],
                ['url'],
                ['name' => 'Ada Lovelace', 'url' => 'https://example.test/reset-password/abc', 'minutes' => '60'],
            ),
            'account.login_link' => $template(
                'Sign-in link',
                'Your sign-in link',
                "Use the button below to sign in. The link works once and lasts {{minutes}} minutes.\n\n[Sign in]({{url}})\n\nIf you did not ask for it, no further action is required: nobody can sign in without this email.",
                ['name' => 'The person\'s name', 'url' => 'The sign-in link', 'minutes' => 'How long the link lasts, in minutes'],
                ['url'],
                ['name' => 'Ada Lovelace', 'url' => 'https://example.test/login/link/abc', 'minutes' => '15'],
            ),
            'account.waitlist_invite' => $template(
                'Waitlist: there is room',
                'There is room for you on {{site}}',
                "Good news: you can now create your account on {{site}}.\n\n[Create your account]({{url}})\n\nUse the address this email came to.",
                ['name' => 'The person\'s name, if they gave one', 'url' => 'The sign-up page'],
                ['url'],
                ['name' => 'Ada Lovelace', 'url' => 'https://example.test/register?email=ada%40example.test'],
            ),
            'workspace.invitation' => $template(
                'Team invitation',
                '{{inviter}} invited you to {{workspace}} on {{site}}',
                "# You have been invited\n\n{{inviter}} invited you to join {{workspace}} as {{role}}.\n\n[Accept the invitation]({{url}})\n\nThe invitation lasts {{days}} days. If you were not expecting it, you can ignore this email.",
                ['inviter' => 'Who sent the invitation', 'workspace' => 'The team\'s name', 'role' => 'The role offered', 'days' => 'How long the invitation lasts', 'url' => 'The link that accepts it'],
                ['url'],
                ['inviter' => 'Ada Lovelace', 'workspace' => 'Analytical Engines', 'role' => 'Member', 'days' => '7', 'url' => 'https://example.test/invitations/abc'],
            ),
            'billing.payment_failed' => $template(
                'Payment failed',
                'Your payment did not go through',
                "The latest payment for {{workspace}} failed. Until it is paid, the team has the features of the free plan.\n\n[Update your card]({{url}})\n\nYour data is safe. Once the payment goes through, everything comes back.",
                ['workspace' => 'The team\'s name', 'url' => 'The billing page'],
                ['url'],
                ['workspace' => 'Analytical Engines', 'url' => 'https://example.test/w/analytical-engines/billing'],
            ),
            'billing.trial_ending' => $template(
                'Free trial ending',
                'Your free trial ends on {{date}}',
                "The free trial of {{workspace}} ends on {{date}}. After that your card is charged and the plan carries on.\n\n[Review your plan]({{url}})\n\nIf you do not want to continue, cancel before then and you will pay nothing.",
                ['workspace' => 'The team\'s name', 'date' => 'The day the trial ends', 'url' => 'The billing page'],
                ['url'],
                ['workspace' => 'Analytical Engines', 'date' => '14 Nov 2026', 'url' => 'https://example.test/w/analytical-engines/billing'],
            ),
            'contact.received' => $template(
                'Contact form: message for support',
                '[{{reference}}] {{topic}} from {{name}}',
                "From: {{name}} ({{email}})\n\nTopic: {{topic}}\n\n{{message}}",
                ['reference' => 'The reference the sender was given', 'name' => 'The sender\'s name', 'email' => 'The sender\'s address', 'topic' => 'The topic they chose', 'message' => 'What they wrote'],
                [],
                ['reference' => 'CM-ABCD1234', 'name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'topic' => 'Billing', 'message' => "Hello,\nCould you help me?"],
            ),
            'contact.receipt' => $template(
                'Contact form: receipt for the sender',
                'We have your message ({{reference}})',
                "# Thank you, {{name}}\n\nWe have your message and will reply to this address.\n\nYour reference is {{reference}}. Please quote it if you write to us again.\n\nYou wrote:\n\n{{message}}",
                ['reference' => 'The reference the sender was given', 'name' => 'The sender\'s name', 'message' => 'What they wrote'],
                ['reference'],
                ['reference' => 'CM-ABCD1234', 'name' => 'Ada Lovelace', 'message' => "Hello,\nCould you help me?"],
            ),
        ];
    }
}
