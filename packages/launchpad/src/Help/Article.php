<?php

namespace Launchpad\Help;

/** A help article or an FAQ answer, read from a Markdown file */
final readonly class Article
{
    /**
     * @param  list<string>  $keywords
     * @param  list<string>  $related
     * @param  list<string>  $missingTokens
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $category,
        public array $keywords,
        public array $related,
        public ?string $updated,
        public string $html,
        public string $text,
        public string $source,
        public string $markdown,
        public array $missingTokens = [],
    ) {}
}
