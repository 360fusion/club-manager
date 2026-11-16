<?php

namespace Launchpad\Http\Controllers\Site;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Launchpad\Help\Help;
use Launchpad\Models\BlogPost;
use Launchpad\Site\Pages;
use Launchpad\Site\Site;

/** robots.txt, sitemap.xml, llms.txt and security.txt: what search engines, AI tools and security researchers look for */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Allow: /', 'Disallow: /'.trim((string) config('launchpad.admin.path', 'hq'), '/'), 'Disallow: /w/', 'Disallow: /account', 'Disallow: /invitations/', 'Disallow: /checkout', '', 'Sitemap: '.url('/sitemap.xml')];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(Pages $pages, Help $help): Response
    {
        $xml = Cache::remember('launchpad.sitemap', 3600, function () use ($pages, $help) {
            $urls = ['/' => null, '/pricing' => null, '/contact' => null];

            foreach ($pages->slugs() as $slug) {
                $page = config("launchpad.site.pages.{$slug}") ? $pages->find($slug) : null;

                if ($page && ! $page->noindex()) {
                    $urls["/{$slug}"] = $page->updated();
                }
            }

            if (config('launchpad.modules.help')) {
                $urls['/help'] = null;
                $urls['/faq'] = null;
                foreach ($help->articles() as $article) {
                    $urls["/help/{$article->slug}"] = $article->updated;
                }
                foreach ($help->faqs() as $faq) {
                    $urls["/faq/{$faq->slug}"] = $faq->updated;
                }
            }

            if (config('launchpad.modules.blog')) {
                $urls['/blog'] = null;
                foreach (BlogPost::published()->get(['slug', 'updated_at']) as $post) {
                    $urls["/blog/{$post->slug}"] = $post->updated_at->toDateString();
                }
            }

            $extra = config('launchpad.site.sitemap', []);
            foreach (is_callable($extra) ? $extra() : $extra as $path) {
                $urls[$path] = null;
            }

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as $path => $updated) {
                $out .= '  <url><loc>'.e(url($path)).'</loc>'.($updated ? '<lastmod>'.e($updated).'</lastmod>' : '').'</url>'."\n";
            }

            return $out.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function llms(Pages $pages, Help $help): Response
    {
        $lines = ['# '.Site::name(), ''];
        if ($tagline = Site::get('tagline')) {
            $lines[] = '> '.$tagline;
            $lines[] = '';
        }

        $lines[] = '## Pages';
        foreach ($pages->slugs() as $slug) {
            if (config("launchpad.site.pages.{$slug}") && ($page = $pages->find($slug)) && ! $page->noindex()) {
                $lines[] = "- [{$page->title}](".url("/{$slug}")."): {$page->description}";
            }
        }

        if (config('launchpad.modules.help')) {
            $lines[] = '';
            $lines[] = '## Help';
            foreach ($help->articles() as $article) {
                $lines[] = "- [{$article->title}](".url("/help/{$article->slug}")."): {$article->description}";
            }
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function securityTxt(): Response
    {
        $email = Site::get('support_email');
        abort_if($email === null, 404);

        $lines = ["Contact: mailto:{$email}", 'Expires: '.now()->addYear()->startOfDay()->toIso8601ZuluString(), 'Preferred-Languages: en', 'Canonical: '.url('/.well-known/security.txt'), 'Policy: '.url('/security')];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
