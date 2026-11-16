<?php

namespace Launchpad\Site;

/** A page of the website, read from a Markdown file (the app's own if it has one, else the package's). */
final readonly class Page
{
    /**
     * @param  array<string, mixed>  $meta  the front matter
     * @param  list<string>  $missingTokens  {{tokens}} that have no value yet
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $html,
        public array $meta,
        public string $source,
        public string $path,
        public string $bodyHash,
        public array $missingTokens = [],
        public string $locale = '',
    ) {}

    public function isOverride(): bool
    {
        return $this->source === 'app';
    }

    public function version(): int
    {
        return (int) ($this->meta['version'] ?? 1);
    }

    public function updated(): ?string
    {
        $updated = $this->meta['updated'] ?? null;

        return $updated instanceof \DateTimeInterface ? $updated->format('Y-m-d') : (is_scalar($updated) ? (string) $updated : null);
    }

    public function noindex(): bool
    {
        return (bool) ($this->meta['noindex'] ?? false);
    }

    /** 'template' (the shipped text, nobody has checked it) or 'reviewed'; only legal pages carry one */
    public function status(): ?string
    {
        return isset($this->meta['status']) ? (string) $this->meta['status'] : null;
    }
}
