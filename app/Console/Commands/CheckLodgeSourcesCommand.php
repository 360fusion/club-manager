<?php

namespace App\Console\Commands;

use App\Models\LodgeSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class CheckLodgeSourcesCommand extends Command
{
    protected $signature = 'lodges:check-sources
        {--province= : Only lodges in this province code}
        {--lodge= : Only this lodge (its slug)}
        {--kind= : Only this kind of source (province_page, province_list, ugle_hall)}
        {--limit= : Check at most this many distinct pages}
        {--delay=1 : Seconds to wait between requests}
        {--dry-run : List the pages that would be fetched and fetch nothing}';

    protected $description = 'Re-fetch the pages lodge details were taken from and flag the ones that have changed. It never edits a lodge.';

    public function handle(): int
    {
        $sources = LodgeSource::query()
            ->when($this->option('province'), fn ($query, $code) => $query->whereHas('lodge.province', fn ($province) => $province->where('code', $code)))
            ->when($this->option('lodge'), fn ($query, $slug) => $query->whereHas('lodge', fn ($lodge) => $lodge->where('slug', $slug)))
            ->when($this->option('kind'), fn ($query, $kind) => $query->where('kind', $kind))
            ->with('lodge:id,name,number')
            ->orderBy('url')
            ->get()
            ->groupBy('url');

        if ($limit = (int) $this->option('limit')) {
            $sources = $sources->take($limit);
        }

        if ($this->option('dry-run')) {
            foreach ($sources as $url => $group) {
                $this->line("{$url} ({$group->count()} lodge".($group->count() === 1 ? '' : 's').')');
            }

            $this->info($sources->count().' page(s) would be checked.');

            return self::SUCCESS;
        }

        $delay = max(0, (float) $this->option('delay'));
        $counts = ['ok' => 0, 'unchanged' => 0, 'changed' => 0, 'failed' => 0];
        $first = true;

        foreach ($sources as $url => $group) {
            if (! $first && $delay > 0) {
                usleep((int) ($delay * 1_000_000));
            }

            $first = false;
            [$httpStatus, $body] = $this->fetch((string) $url);
            $numbers = $group->pluck('lodge.number')->filter()->map(fn ($number) => (string) $number)->all();

            foreach ($group as $source) {
                // On a page listing several lodges, each lodge is compared by its own entry, so one lodge's change flags only that lodge.
                $hash = match (true) {
                    $body === null => null,
                    $group->count() > 1 && filled($source->lodge?->number) => self::entryHash($body, (string) $source->lodge->number, $numbers),
                    default => self::contentHash($body),
                };
                $status = $this->record($source, $httpStatus, $hash);
                $counts[$status]++;
            }

            $this->line("{$url}: ".($httpStatus ?: 'no response'));
        }

        $this->info("Checked {$sources->count()} page(s): {$counts['ok']} first check, {$counts['unchanged']} unchanged, {$counts['changed']} changed, {$counts['failed']} failed.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: string|null} the HTTP status (0 when nothing came back) and the page, when it loaded
     */
    private function fetch(string $url): array
    {
        try {
            $response = Http::withUserAgent('ClubManagerDirectoryCheck/1.0')->timeout(20)->get($url);
        } catch (Throwable) {
            return [0, null];
        }

        return [$response->status(), $response->successful() ? $response->body() : null];
    }

    /**
     * A hash of one lodge's entry on a list page: from the line giving its number up to the next line giving
     * another listed lodge's number. Falls back to the whole page when the number is not on it, so a lodge
     * dropped from the list shows as changed.
     *
     * @param  list<string>  $numbers  every lodge number this page is a source for
     */
    public static function entryHash(string $page, string $number, array $numbers): string
    {
        $html = preg_replace('#<(script|style|noscript)\b.*?</\1>|<!--.*?-->#is', ' ', $page) ?? $page;
        $html = preg_replace('#<(br|/p|/li|/tr|/td|/th|/dt|/dd|/div|/h[1-6])\b[^>]*>#i', "$0\n", $html) ?? $html;
        $lines = array_values(array_filter(array_map(
            fn ($line) => trim(preg_replace('/\s+/u', ' ', $line) ?? $line),
            explode("\n", html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)),
        ), fn ($line) => $line !== ''));

        $givesNumber = fn (string $line, string $n) => (bool) preg_match('/^\(?(?:No\.?\s*)?'.preg_quote($n, '/').'\)?$|\bNo\.?\s*'.preg_quote($n, '/').'\b/i', $line);
        $others = array_diff($numbers, [$number]);

        foreach ($lines as $start => $line) {
            if (! $givesNumber($line, $number)) {
                continue;
            }

            $entry = [$line];
            foreach (array_slice($lines, $start + 1, 20) as $next) {
                if (array_filter($others, fn ($other) => $givesNumber($next, $other))) {
                    break;
                }
                $entry[] = $next;
            }

            return sha1(implode("\n", $entry));
        }

        return self::contentHash($page);
    }

    /**
     * A page is compared by its visible text only, so changes to scripts, styles or markup do not count.
     */
    public static function contentHash(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript)\b.*?</\1>|<!--.*?-->#is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return sha1(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }

    /**
     * @return 'ok'|'unchanged'|'changed'|'failed'
     */
    private function record(LodgeSource $source, int $httpStatus, ?string $hash): string
    {
        $source->last_checked_at = now();
        $source->last_http_status = $httpStatus ?: null;

        if ($hash === null) {
            $source->last_status = 'failed';
        } elseif ($source->content_hash === null) {
            $source->last_status = 'ok';
            $source->content_hash = $hash;
        } elseif ($source->content_hash === $hash) {
            $source->last_status = 'unchanged';
        } else {
            $source->last_status = 'changed';
            $source->content_hash = $hash;
            $source->changed_at = now();
        }

        $source->save();

        return $source->last_status;
    }
}
