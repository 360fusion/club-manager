<?php

namespace Launchpad\Http\Controllers\Site;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Site\Pages;
use Launchpad\Site\Site;

class PageController extends Controller
{
    public function show(Request $request, Pages $pages, string $slug): View|Responsable
    {
        abort_unless(config("launchpad.site.pages.{$slug}"), 404);

        $page = $pages->find($slug);
        abort_if($page === null, 404);

        $status = $pages->status($page);
        $isLegal = in_array($slug, (array) config('launchpad.site.legal'), true);

        return Ui::render('site.page', ['page' => $page, 'status' => $status, 'isLegal' => $isLegal], fn () => [
            'page' => ['slug' => $page->slug, 'title' => $page->title, 'description' => $page->description, 'html' => $page->html, 'updated' => $page->updated(), 'noindex' => $page->noindex()],
            'showTemplateNotice' => $isLegal && $status === 'template' && ! app()->isProduction(),
        ]);
    }

    public function home(): View|Responsable
    {
        return Ui::render('site.home', [], fn () => ['name' => Site::name(), 'tagline' => Site::get('tagline')]);
    }
}
