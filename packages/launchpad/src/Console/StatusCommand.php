<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Launchpad\Site\Markdown;

/**
 * `php artisan launchpad:status` lists the files the app has overridden and says, for each, whether the package's default it came from has
 * changed since (a fix to the Terms must not be missed because a build edited its copy). `--diff` shows what changed.
 */
class StatusCommand extends Command
{
    protected $signature = 'launchpad:status {--diff : Show what changed in the defaults}';

    protected $description = 'List overridden pages and help files and whether their defaults have changed';

    public function handle(): int
    {
        $rows = [];
        $changed = 0;

        foreach (['page', 'article', 'faq'] as $kind) {
            $directory = resource_path('launchpad/'.PublishCommand::folder($kind));

            foreach (File::isDirectory($directory) ? File::files($directory) : [] as $file) {
                if ($file->getExtension() !== 'md') {
                    continue;
                }

                $slug = $file->getFilenameWithoutExtension();
                $default = __DIR__.'/../../resources/content/'.PublishCommand::folder($kind)."/{$slug}.md";
                $raw = File::get($file->getPathname());
                [$meta] = Markdown::split($raw);

                if (! File::isFile($default)) {
                    $state = 'your own (no default)';
                } elseif (! isset($meta['based_on_hash'])) {
                    $state = 'no record of its default: re-publish with --force to start one';
                } else {
                    $current = File::get($default);
                    $same = sha1(Markdown::body($current)) === $meta['based_on_hash'];
                    $state = $same ? 'up to date' : 'DEFAULT CHANGED since version '.($meta['based_on_version'] ?? '?');
                    $changed += $same ? 0 : 1;

                    if (! $same && $this->option('diff')) {
                        $this->line("--- {$kind} {$slug}");
                        $this->diff(Markdown::body($raw), Markdown::body($current));
                    }
                }

                $rows[] = [$kind, $slug, $state];
            }
        }

        $rows === [] ? $this->info('Nothing is overridden: every page and help file is the package\'s own.') : $this->table(['Kind', 'Name', 'State'], $rows);
        $changed && $this->warn("{$changed} default(s) changed since you copied them. Run with --diff to see what.");

        return self::SUCCESS;
    }

    /** Lines only in yours (-) and only in the new default (+): enough to see what a fix touched */
    private function diff(string $yours, string $theirs): void
    {
        $a = explode("\n", $yours);
        $b = explode("\n", $theirs);

        foreach (array_diff($a, $b) as $line) {
            $this->line('<fg=red>- '.$line.'</>');
        }
        foreach (array_diff($b, $a) as $line) {
            $this->line('<fg=green>+ '.$line.'</>');
        }
    }
}
