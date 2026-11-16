<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Launchpad\Help\Article;
use Launchpad\Help\Help;
use Launchpad\Site\Markdown;
use Launchpad\Site\Pages;
use Launchpad\Support\Locales;

/**
 * `php artisan help:build` checks every help article, FAQ answer and page: the front matter is there and sensible, the category exists, the
 * related articles exist, every internal link goes somewhere, no {{token}} is left without a value. It exits with 1 on any problem, so a
 * deploy can stop on it.
 */
class HelpBuildCommand extends Command
{
    protected $signature = 'help:build {--quiet-ok : Print nothing when all is well}';

    protected $description = 'Check the help centre, the FAQ and the pages';

    public function handle(Help $help, Pages $pages): int
    {
        $help->forget();
        $problems = $this->problems($help, $pages);

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        if ($problems === []) {
            $this->option('quiet-ok') || $this->info($help->articles()->count().' articles, '.$help->faqs()->count().' FAQ answers and '.count($pages->slugs()).' pages checked.');

            return self::SUCCESS;
        }

        $this->error(count($problems).' problem(s).');

        return self::FAILURE;
    }

    /** @return list<string> the base language's problems, then what each other language's own files add, marked [fr] and so on */
    public function problems(Help $help, Pages $pages): array
    {
        $original = app()->getLocale();
        $problems = $this->problemsIn($help, $pages);

        try {
            foreach (array_diff(array_keys(Locales::supported()), [Locales::default()]) as $locale) {
                app()->setLocale($locale);
                $help->forget();

                foreach (array_diff($this->problemsIn($help, $pages), $problems) as $problem) {
                    $problems[] = "[{$locale}] {$problem}";
                }
            }
        } finally {
            app()->setLocale($original);
            $help->forget();
        }

        return $problems;
    }

    /** @return list<string> */
    private function problemsIn(Help $help, Pages $pages): array
    {
        // a file whose front matter is not valid YAML cannot be read at all: say which, and stop there
        if ($broken = $this->frontMatterProblems($help, $pages)) {
            return $broken;
        }

        $problems = [];
        $categories = $help->categories();

        foreach ($help->articles() as $article) {
            $at = "help/articles/{$article->slug}";

            foreach (['title' => $article->title === $article->slug, 'description' => $article->description === '', 'category' => $article->category === ''] as $field => $empty) {
                $empty && $problems[] = "{$at}: no {$field} in the front matter";
            }
            if ($article->category !== '' && ! isset($categories[$article->category])) {
                $problems[] = "{$at}: category \"{$article->category}\" is not in categories.php";
            }
            if ($article->keywords === []) {
                $problems[] = "{$at}: no keywords";
            }
            foreach ($article->related as $related) {
                $help->article($related) ?: $problems[] = "{$at}: related article \"{$related}\" does not exist";
            }
            array_push($problems, ...$this->contentProblems($at, $article));
        }

        foreach ($help->faqs() as $faq) {
            $at = "help/faq/{$faq->slug}";

            $faq->title === $faq->slug && $problems[] = "{$at}: no question in the front matter";
            $faq->category === '' && $problems[] = "{$at}: no category";
            array_push($problems, ...$this->contentProblems($at, $faq));
        }

        foreach ($pages->slugs() as $slug) {
            $page = $pages->find($slug);
            $at = "pages/{$slug}";

            $page->description === '' && $problems[] = "{$at}: no description in the front matter";
            $page->missingTokens && $problems[] = "{$at}: no value yet for {{".implode('}}, {{', $page->missingTokens).'}}';
            array_push($problems, ...$this->linkProblems($at, $page->path ? (string) file_get_contents($page->path) : ''));
        }

        return $problems;
    }

    /** @return list<string> */
    private function frontMatterProblems(Help $help, Pages $pages): array
    {
        $problems = [];
        $directories = [
            'pages' => [$pages->appDirectory(), $pages->packageDirectory()],
            'help/articles' => [$help->directory('articles', 'app'), $help->directory('articles', 'package')],
            'help/faq' => [$help->directory('faq', 'app'), $help->directory('faq', 'package')],
        ];

        foreach (array_diff(array_keys(Locales::supported()), [Locales::default()]) as $locale) {
            foreach ($directories as $label => $paths) {
                $directories["{$label}/{$locale}"] = array_map(fn ($path) => "{$path}/{$locale}", $paths);
            }
        }

        foreach ($directories as $label => $paths) {
            foreach ($paths as $directory) {
                foreach (File::isDirectory($directory) ? File::files($directory) : [] as $file) {
                    try {
                        Markdown::split(File::get($file->getPathname()));
                    } catch (\InvalidArgumentException $e) {
                        $problems[] = "{$label}/{$file->getFilename()}: ".$e->getMessage();
                    }
                }
            }
        }

        return $problems;
    }

    /** @return list<string> */
    private function contentProblems(string $at, Article $article): array
    {
        $problems = [];

        if ($article->missingTokens) {
            $problems[] = "{$at}: no value yet for {{".implode('}}, {{', $article->missingTokens).'}}';
        }

        return array_merge($problems, $this->linkProblems($at, $article->markdown));
    }

    /** @return list<string> */
    private function linkProblems(string $at, string $markdown): array
    {
        $problems = [];
        preg_match_all('/\]\((\/[^)\s#]*)(?:#[^)\s]*)?\)/', $markdown, $matches);

        foreach (array_unique($matches[1]) as $path) {
            if (! $this->routeExists($path)) {
                $problems[] = "{$at}: the link {$path} goes nowhere";
            }
        }

        return $problems;
    }

    private function routeExists(string $path): bool
    {
        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (\Throwable) {
            return false;
        }

        // a route that is there but answers 404 because the content behind it is not (help/slug, faq/id, a page that is off)
        if ($route->getName() === 'help.article') {
            return app(Help::class)->article($route->parameter('slug')) !== null;
        }
        if ($route->getName() === 'faq.show') {
            return app(Help::class)->faq($route->parameter('id')) !== null;
        }
        if (str_starts_with((string) $route->getName(), 'page.')) {
            return (bool) config('launchpad.site.pages.'.substr($route->getName(), 5));
        }

        return true;
    }
}
