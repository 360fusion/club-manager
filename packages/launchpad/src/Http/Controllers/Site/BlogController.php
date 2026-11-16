<?php

namespace Launchpad\Http\Controllers\Site;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Models\BlogPost;
use Launchpad\Site\Site;

class BlogController
{
    public function index(): View|Responsable
    {
        $posts = BlogPost::published()->with('author')->latest('published_at')->simplePaginate(10);

        return Ui::render('site.blog.index', ['posts' => $posts], fn () => [
            'posts' => $posts->getCollection()->map(fn (BlogPost $p) => ['slug' => $p->slug, 'title' => $p->title, 'summary' => $p->summary, 'published' => $p->published_at->toIso8601String()])->values(),
            'newerUrl' => $posts->previousPageUrl(),
            'olderUrl' => $posts->nextPageUrl(),
        ]);
    }

    public function show(string $slug): View|Responsable
    {
        $post = BlogPost::published()->with('author')->where('slug', $slug)->firstOrFail();

        return Ui::render('site.blog.show', ['post' => $post], fn () => [
            'post' => ['title' => $post->title, 'summary' => $post->summary, 'html' => $post->html(), 'author' => $post->author?->name, 'published' => $post->published_at->toIso8601String()],
        ]);
    }

    /** An Atom feed of the latest posts */
    public function feed(): Response
    {
        $posts = BlogPost::published()->with('author')->latest('published_at')->limit(20)->get();
        $updated = ($posts->first()?->updated_at ?? now())->toAtomString();

        $xml = '<?xml version="1.0" encoding="utf-8"?>'."\n".'<feed xmlns="http://www.w3.org/2005/Atom">'
            .'<title>'.e(Site::name().' blog').'</title><id>'.e(route('blog.index')).'</id><updated>'.$updated.'</updated>'
            .'<link rel="self" href="'.e(route('blog.feed')).'"/><link href="'.e(route('blog.index')).'"/>';

        foreach ($posts as $post) {
            $url = route('blog.show', $post->slug);
            $xml .= '<entry><title>'.e($post->title).'</title><id>'.e($url).'</id><link href="'.e($url).'"/>'
                .'<published>'.$post->published_at->toAtomString().'</published><updated>'.$post->updated_at->toAtomString().'</updated>'
                .($post->author ? '<author><name>'.e($post->author->name).'</name></author>' : '')
                .'<summary>'.e($post->summary ?? '').'</summary><content type="html">'.e($post->html()).'</content></entry>';
        }

        return response($xml.'</feed>', 200, ['Content-Type' => 'application/atom+xml; charset=UTF-8']);
    }
}
