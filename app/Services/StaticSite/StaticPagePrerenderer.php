<?php

namespace App\Services\StaticSite;

use App\Support\NodeBinaries;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Opens a page in headless Chrome and returns the HTML once Vue has drawn it, so a static file holds the real page for
 * search engines and visitors without JavaScript (the page data stays in it, so Vue still starts up). Without Chrome
 * the page is kept as the server sent it: it still works, Vue just draws it in the visitor's browser.
 */
class StaticPagePrerenderer
{
    public function render(string $url, string $fallbackHtml): string
    {
        try {
            ['node' => $node, 'npm' => $npm] = NodeBinaries::find();
            $browsershot = Browsershot::url($url)->waitUntilNetworkIdle()->timeout(60);

            if ($node && file_exists($node)) {
                $browsershot->setNodeBinary($node)->setIncludePath(str_replace(' ', '\ ', dirname($node)).':/opt/homebrew/bin:/usr/local/bin:/usr/bin');
            }
            if ($npm && file_exists($npm)) {
                $browsershot->setNpmBinary($npm);
            }

            $html = $browsershot->bodyHtml();

            // Only a page that still carries its data can start Vue on the static site, and only one drawn from the
            // built code: a Vite dev server started during the build would leave its own addresses in the page.
            if (! str_contains($html, 'data-page="app"') || str_contains($html, '/@vite/client')) {
                return $fallbackHtml;
            }

            return str_starts_with(ltrim($html), '<!') ? $html : "<!DOCTYPE html>\n".$html;
        } catch (Throwable $e) {
            report($e);

            return $fallbackHtml;
        }
    }
}
