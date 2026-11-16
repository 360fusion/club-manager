<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Launchpad\Site\Markdown;

/**
 * `php artisan launchpad:publish privacy` copies the package's default page into the app so it can be edited, and writes into the copy
 * what it came from (`based_on_hash`, `based_on_version`). `launchpad:status` later says whether that default has changed. Help
 * files: `launchpad:publish create-an-account --kind=article` (or faq).
 */
class PublishCommand extends Command
{
    protected $signature = 'launchpad:publish {slug} {--kind=page : page, article or faq} {--force : Overwrite the app\'s copy}';

    protected $description = 'Copy a default page or help file into the app to edit it';

    public function handle(): int
    {
        $kind = $this->option('kind');
        $slug = (string) $this->argument('slug');
        abort_unless(in_array($kind, ['page', 'article', 'faq'], true) && preg_match('/\A[a-z0-9-]+\z/', $slug), 422);

        $from = __DIR__.'/../../resources/content/'.self::folder($kind)."/{$slug}.md";
        $to = resource_path('launchpad/'.self::folder($kind))."/{$slug}.md";

        if (! File::isFile($from)) {
            $this->error("There is no default {$kind} called {$slug}.");

            return self::FAILURE;
        }

        if (File::exists($to) && ! $this->option('force')) {
            $this->error("{$to} already exists. Use --force to replace it.");

            return self::FAILURE;
        }

        $raw = File::get($from);
        [$meta] = Markdown::split($raw);
        $note = 'based_on_hash: '.sha1(Markdown::body($raw))."\nbased_on_version: ".((int) ($meta['version'] ?? 1));

        // the note goes at the end of the front matter, which every default has
        $copy = preg_replace('/\A(---\R.*?)(\R---\R)/s', '$1'."\n".$note.'$2', $raw, 1);

        File::ensureDirectoryExists(dirname($to));
        File::put($to, $copy);
        $this->info("Copied to {$to}. Edit it there; the package's own is left alone.");

        return self::SUCCESS;
    }

    public static function folder(string $kind): string
    {
        return match ($kind) {
            'article' => 'help/articles',
            'faq' => 'help/faq',
            default => 'pages',
        };
    }
}
