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
            [$httpStatus, $hash] = $this->fetch((string) $url);

            foreach ($group as $source) {
                $status = $this->record($source, $httpStatus, $hash);
                $counts[$status]++;
            }

            $this->line("{$url}: ".($httpStatus ?: 'no response'));
        }

        $this->info("Checked {$sources->count()} page(s): {$counts['ok']} first check, {$counts['unchanged']} unchanged, {$counts['changed']} changed, {$counts['failed']} failed.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: string|null} the HTTP status (0 when nothing came back) and a hash of the page's visible text
     */
    private function fetch(string $url): array
    {
        try {
            $response = Http::withUserAgent('ClubManagerDirectoryCheck/1.0')->timeout(20)->get($url);
        } catch (Throwable) {
            return [0, null];
        }

        return [$response->status(), $response->successful() ? self::contentHash($response->body()) : null];
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
