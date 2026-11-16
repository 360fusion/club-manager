<?php

namespace Launchpad\Mail;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Turns a template into a MailMessage in the app's mail layout. A {{variable}} is replaced by its value with the Markdown in it escaped
 * (a name like "<b>x</b>" is shown as text, never as markup); one with no value renders empty. The author's raw HTML is dropped and
 * links must be safe (no javascript:). A paragraph that is only a link becomes the layout's button.
 */
final class Renderer
{
    /** Variables every template may use, filled in here */
    public const GLOBALS = ['site'];

    /**
     * @param  array<string, scalar|null>  $vars
     * @param  array{subject: string, body: string}|null  $wording  unsaved wording to try (the admin's preview); default: what is saved, else the default
     */
    public static function message(string $key, array $vars, ?array $wording = null): MailMessage
    {
        $wording ??= Templates::resolve($key);
        $vars += ['site' => (string) config('launchpad.site.name', config('app.name'))];

        $html = self::html(self::fill($wording['body'], $vars, self::escapeMarkdown(...)));
        $action = null;

        // the first paragraph that is only a link
        if (preg_match('~<p><a href="([^"]*)">(.*?)</a></p>~s', $html, $m)) {
            $action = ['url' => html_entity_decode($m[1]), 'label' => html_entity_decode(strip_tags($m[2]))];
            [$before, $after] = explode($m[0], $html, 2);
        }

        return (new MailMessage)
            ->subject(trim(preg_replace('/\s+/', ' ', self::fill($wording['subject'], $vars, fn (string $v) => $v))))
            ->markdown('launchpad::mail.template', [
                'before' => $before ?? $html,
                'after' => $after ?? '',
                'action' => $action,
                'text' => self::fill($wording['body'], $vars, fn (string $v) => $v),
                'site' => $vars['site'],
            ]);
    }

    /** Laravel's verification and password reset mails are sent from the templates; an app that sets its own toMailUsing afterwards wins */
    public static function useForAccountMails(): void
    {
        VerifyEmail::toMailUsing(fn ($user, string $url) => self::message('account.verify', ['name' => $user->name ?? '', 'url' => $url]));
        ResetPassword::toMailUsing(function ($user, string $token) {
            $url = ResetPassword::$createUrlCallback
                ? call_user_func(ResetPassword::$createUrlCallback, $user, $token)
                : url(route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false));

            return self::message('account.reset_password', ['name' => $user->name ?? '', 'url' => $url, 'minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]);
        });
    }

    /** @param  array<string, scalar|null>  $vars */
    private static function fill(string $text, array $vars, callable $escape): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', fn (array $m) => $escape((string) ($vars[$m[1]] ?? '')), $text);
    }

    /** Every ASCII punctuation mark gets a backslash (CommonMark takes it as the mark itself), a line break stays one */
    private static function escapeMarkdown(string $value): string
    {
        return preg_replace('/\r\n|\r|\n/', "\\\n", preg_replace('/[!-\/:-@\[-`{-~]/', '\\\\$0', $value));
    }

    /** One line per block: the layout parses its contents as Markdown again, and a blank line would end the HTML block */
    private static function html(string $markdown): string
    {
        $environment = new Environment(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $environment->addExtension(new CommonMarkCoreExtension);

        return preg_replace('/\n+/', "\n", trim((new MarkdownConverter($environment))->convert($markdown)->getContent()));
    }
}
