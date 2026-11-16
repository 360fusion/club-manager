<?php

namespace Launchpad\Http\Controllers\Site;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Launchpad\Help\Help;
use Launchpad\Http\Ui;
use Launchpad\Models\HelpFeedback;

class HelpController extends Controller
{
    public function hub(Help $help): View|Responsable
    {
        return Ui::render('help.hub', ['categories' => $help->categories(), 'help' => $help], fn () => [
            'categories' => collect($help->categories())->map(fn ($info, $slug) => ['slug' => $slug, 'title' => $info['title'], 'description' => $info['description'],
                'articles' => $help->inCategory($slug)->take(5)->map(fn ($a) => ['slug' => $a->slug, 'title' => $a->title])->values()])->values(),
        ]);
    }

    public function search(Request $request, Help $help): View|Responsable
    {
        $query = trim((string) $request->query('q'));

        $results = $help->search(mb_substr($query, 0, 120));

        return Ui::render('help.results', ['query' => $query, 'results' => $results], fn () => [
            'query' => $query,
            'results' => $results->map(fn ($r) => ['kind' => $r['kind'], 'slug' => $r['article']->slug, 'title' => $r['article']->title,
                'snippet' => Str::limit($r['article']->description ?: $r['article']->text, 140)])->values(),
        ]);
    }

    public function category(Help $help, string $category): View|Responsable
    {
        $info = $help->categories()[$category] ?? null;
        abort_if($info === null, 404);

        $articles = $help->inCategory($category);

        return Ui::render('help.category', ['category' => $category, 'info' => $info, 'articles' => $articles], fn () => [
            'category' => ['slug' => $category, 'title' => $info['title'], 'description' => $info['description']],
            'articles' => $articles->map(fn ($a) => ['slug' => $a->slug, 'title' => $a->title, 'description' => $a->description])->values(),
        ]);
    }

    public function article(Help $help, string $slug): View|Responsable
    {
        $article = $help->article($slug);
        abort_if($article === null, 404);

        $categoryInfo = $help->categories()[$article->category] ?? null;
        $related = collect($article->related)->map(fn ($s) => $help->article($s))->filter();

        return Ui::render('help.article', ['article' => $article, 'category' => $categoryInfo, 'related' => $related], fn () => [
            'article' => ['slug' => $article->slug, 'title' => $article->title, 'description' => $article->description, 'html' => $article->html],
            'category' => $categoryInfo ? ['slug' => $article->category, 'title' => $categoryInfo['title']] : null,
            'related' => $related->map(fn ($a) => ['slug' => $a->slug, 'title' => $a->title])->values(),
        ]);
    }

    public function feedback(Request $request, Help $help, string $slug): RedirectResponse
    {
        $article = $help->article($slug);
        abort_if($article === null, 404);

        $data = $request->validate(['helpful' => ['required', 'in:0,1'], 'comment' => ['nullable', 'string', 'max:1000']]);

        HelpFeedback::create([
            'reference' => (string) Str::ulid(),
            'article' => $slug,
            'version' => $article->updated,
            'helpful' => $data['helpful'] === '1',
            'comment' => filled($data['comment'] ?? null) ? strip_tags($data['comment']) : null,
        ]);

        return redirect()->route('help.article', $slug)->with('status', __('Thank you. That helps us make this page better.'));
    }

    public function faqs(Request $request, Help $help): View|Responsable
    {
        $query = trim((string) $request->query('q'));
        $faqs = $query === ''
            ? $help->faqs()
            : $help->search($query, 50)->where('kind', 'faq')->pluck('article')->keyBy('slug');

        return Ui::render('help.faq', ['faqs' => $faqs, 'query' => $query], fn () => [
            'query' => $query,
            'faqs' => $faqs->map(fn ($f) => ['slug' => $f->slug, 'title' => $f->title, 'html' => $f->html])->values(),
        ]);
    }

    public function faq(Help $help, string $id): View|Responsable
    {
        $faq = $help->faq($id);
        abort_if($faq === null, 404);

        return Ui::render('help.faq-entry', ['faq' => $faq], fn () => ['faq' => ['slug' => $faq->slug, 'title' => $faq->title, 'html' => $faq->html]]);
    }
}
