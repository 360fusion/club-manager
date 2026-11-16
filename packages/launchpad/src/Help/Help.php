<?php

namespace Launchpad\Help;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Launchpad\Site\Markdown;

/**
 * The help centre and the FAQ: Markdown files, the address is the file's name. An app's own file (resources/launchpad/help/...) beats the
 * package's of the same name; an app can also add files. Categories come from categories.php, the app's if it has one.
 */
class Help
{
    /** @var array<string, array<string, Article>> by language */
    private array $articles = [];

    /** @var array<string, array<string, Article>> by language */
    private array $faqs = [];

    public function directory(string $kind, string $source, string $locale = ''): string
    {
        return ($source === 'app' ? resource_path('launchpad/help') : __DIR__.'/../../resources/content/help').'/'.$kind.($locale === '' ? '' : "/{$locale}");
    }

    /** The request's language, if it is a safe folder name */
    private function locale(): string
    {
        $locale = app()->getLocale();

        return preg_match('/\A[A-Za-z]{2,3}([_-][A-Za-z0-9]+)?\z/', $locale) ? $locale : '';
    }

    /** @return array<string, array{title: string, description: string, icon: string}> */
    public function categories(): array
    {
        $package = __DIR__.'/../../resources/content/help';
        $locale = $this->locale();
        $first = function (array $files): array {
            foreach ($files as $file) {
                if (File::exists($file)) {
                    return (array) require $file;
                }
            }

            return [];
        };

        $english = $first([resource_path('launchpad/help/categories.php'), $package.'/categories.php']);

        // a language's file is laid over the English one, so a category it leaves out keeps its articles reachable
        return $locale === '' ? $english : array_replace($english, $first([resource_path("launchpad/help/{$locale}/categories.php"), "{$package}/{$locale}/categories.php"]));
    }

    /** @return Collection<string, Article> */
    public function articles(): Collection
    {
        return collect($this->articles[app()->getLocale()] ??= $this->load('articles'))->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE);
    }

    /** @return Collection<string, Article> */
    public function faqs(): Collection
    {
        return collect($this->faqs[app()->getLocale()] ??= $this->load('faq'))->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE);
    }

    public function article(string $slug): ?Article
    {
        return $this->articles()->get($slug);
    }

    public function faq(string $id): ?Article
    {
        return $this->faqs()->get($id);
    }

    /** @return Collection<string, Article> */
    public function inCategory(string $category): Collection
    {
        return $this->articles()->filter(fn (Article $article) => $article->category === $category);
    }

    /** @return array<string, Article> */
    private function load(string $kind): array
    {
        $found = [];

        // the package's first, then the app's over the top, the request's language last of all: a file of the same name replaces it
        $order = [['package', ''], ['app', '']];
        $this->locale() !== '' && array_push($order, ['package', $this->locale()], ['app', $this->locale()]);

        foreach ($order as [$source, $locale]) {
            $directory = $this->directory($kind, $source, $locale);

            foreach (File::isDirectory($directory) ? File::files($directory) : [] as $file) {
                if ($file->getExtension() !== 'md') {
                    continue;
                }

                $slug = $file->getFilenameWithoutExtension();
                $found[$slug] = $this->parse($slug, File::get($file->getPathname()), $source, $kind === 'faq');
            }
        }

        return $found;
    }

    private function parse(string $slug, string $raw, string $source, bool $faq): Article
    {
        [$meta, $html, $missing] = Markdown::parse($raw);
        $updated = $meta['updated'] ?? null;

        return new Article(
            slug: $slug,
            title: (string) ($faq ? ($meta['question'] ?? $slug) : ($meta['title'] ?? $slug)),
            description: (string) ($meta['description'] ?? ''),
            category: (string) ($meta['category'] ?? ''),
            keywords: array_values(array_map('strval', (array) ($meta['keywords'] ?? []))),
            related: array_values(array_map('strval', (array) ($meta['related'] ?? []))),
            updated: $updated instanceof \DateTimeInterface ? $updated->format('Y-m-d') : (is_scalar($updated) ? (string) $updated : null),
            html: $html,
            text: trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? ''),
            source: $source,
            markdown: $raw,
            missingTokens: $missing,
        );
    }

    public function forget(): void
    {
        $this->articles = $this->faqs = [];
    }

    /**
     * Weighted matching, no search server: a word in the title counts most, then the keywords, the description and the body. A word matches
     * the start of a word, so "invoice" finds "invoices". The whole phrase in the title counts extra.
     *
     * @return Collection<int, array{article: Article, score: int, kind: string}>
     */
    public function search(string $query, int $limit = 10): Collection
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY))
            ->reject(fn ($word) => mb_strlen($word) < 2 || in_array($word, self::STOP, true))
            ->unique()->values();

        if ($words->isEmpty()) {
            return collect();
        }

        $hits = [];

        foreach (['article' => $this->articles(), 'faq' => $this->faqs()] as $kind => $items) {
            foreach ($items as $article) {
                $score = $this->score($article, $words->all(), mb_strtolower(trim($query)));

                if ($score > 0) {
                    $hits[] = ['article' => $article, 'score' => $score, 'kind' => $kind];
                }
            }
        }

        return collect($hits)->sortByDesc('score')->take($limit)->values();
    }

    private const STOP = ['the', 'and', 'for', 'how', 'can', 'what', 'does', 'did', 'you', 'your', 'are', 'was', 'with', 'from', 'this', 'that', 'i', 'my', 'do', 'to', 'of', 'in', 'on', 'is', 'it', 'a', 'an'];

    /** @param  list<string>  $words */
    private function score(Article $article, array $words, string $phrase): int
    {
        $title = mb_strtolower($article->title);
        $keywords = mb_strtolower(implode(' ', $article->keywords));
        $description = mb_strtolower($article->description);
        $body = mb_strtolower($article->text);
        $score = str_contains($title, $phrase) && count($words) > 1 ? 10 : 0;

        foreach ($words as $word) {
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'/u';

            $score += preg_match($pattern, $title) ? 5 : 0;
            $score += preg_match($pattern, $keywords) ? 3 : 0;
            $score += preg_match($pattern, $description) ? 2 : 0;
            $score += min(3, preg_match_all($pattern, $body));
        }

        return $score;
    }
}
