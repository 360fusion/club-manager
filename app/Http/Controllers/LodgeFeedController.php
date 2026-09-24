<?php

namespace App\Http\Controllers;

use App\Enums\Visibility;
use App\Models\Lodge;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * A managed lodge's public news as an RSS feed, so anyone can follow it in a feed reader. Only
 * posts the lodge has made public are in it; nothing else ever leaves the lodge this way.
 */
class LodgeFeedController extends Controller
{
    public function show(string $slug): Response
    {
        $lodge = Lodge::listed()->with('club')->where('slug', $slug)->whereNotNull('club_id')->firstOrFail();
        $club = $lodge->club;

        $posts = Post::where('club_id', $club->id)->published()
            ->where('visibility', Visibility::Public->value)
            ->orderByRaw('COALESCE(posts.published_at, posts.created_at) DESC')
            ->limit(20)->get();

        $items = $posts->map(function (Post $post) use ($club) {
            $link = route('member.posts.show', ['slug' => $club->slug, 'id' => $post->id]);
            $summary = $post->excerpt ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $post->content))), 300);

            return '<item>'
                .'<title>'.$this->xml($post->title).'</title>'
                .'<link>'.$this->xml($link).'</link>'
                .'<guid isPermaLink="true">'.$this->xml($link).'</guid>'
                .'<pubDate>'.($post->published_at ?? $post->created_at)->toRfc2822String().'</pubDate>'
                .'<description>'.$this->xml($summary).'</description>'
                .'</item>';
        })->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0"><channel>'
            .'<title>'.$this->xml($lodge->displayName()).'</title>'
            .'<link>'.$this->xml(route('lodges.show', $lodge->slug)).'</link>'
            .'<description>'.$this->xml('News from '.$lodge->displayName()).'</description>'
            .$items
            .'</channel></rss>';

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }

    private function xml(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
