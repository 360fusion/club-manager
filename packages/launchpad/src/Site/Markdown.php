<?php

namespace Launchpad\Site;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * A Markdown file with a YAML front matter. The front matter is read first and the {{tokens}} are filled in afterwards, in each value and in
 * the body, so a company name with a colon in it cannot break the YAML. The body becomes safe HTML: raw HTML is dropped, unsafe links are
 * dropped, links to other sites open safely.
 */
final class Markdown
{
    /**
     * @return array{0: array<string, mixed>, 1: string} the front matter and the body, both as written
     *
     * @throws \InvalidArgumentException when the front matter is not valid YAML
     */
    public static function split(string $raw): array
    {
        if (! preg_match('/\A---\R(.*?)\R---(?:\R|\z)(.*)\z/s', $raw, $m)) {
            return [[], $raw];
        }

        try {
            $meta = Yaml::parse($m[1], Yaml::PARSE_DATETIME);
        } catch (ParseException $e) {
            throw new \InvalidArgumentException('The front matter is not valid: '.$e->getMessage(), 0, $e);
        }

        return [is_array($meta) ? $meta : [], ltrim($m[2], "\r\n")];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: list<string>} the front matter with its tokens filled, the HTML, and the tokens that had no value
     */
    public static function parse(string $raw): array
    {
        [$meta, $body] = self::split($raw);
        $missing = [];

        array_walk_recursive($meta, function (&$value) use (&$missing) {
            if (is_string($value)) {
                [$value, $lacking] = Tokens::fill($value);
                $missing = array_merge($missing, $lacking);
            }
        });

        [$text, $lacking] = Tokens::fill($body);

        return [$meta, self::html($text), array_values(array_unique(array_merge($missing, $lacking)))];
    }

    public static function html(string $markdown): string
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'external_link' => ['internal_hosts' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: [], 'open_in_new_window' => false, 'html_class' => 'external', 'nofollow' => '', 'noopener' => 'external', 'noreferrer' => 'external'],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new ExternalLinkExtension);

        return (string) (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }

    /** Just the body of a document as it is written, without its front matter (for hashing and diffs) */
    public static function body(string $markdown): string
    {
        return preg_match('/\A---\R.*?\R---(?:\R|\z)(.*)\z/s', $markdown, $m) ? ltrim($m[1], "\r\n") : $markdown;
    }
}
