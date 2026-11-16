<?php

namespace Launchpad\Support;

/**
 * The settings table, read once per request. It is bound as scoped, so a long-running worker (Octane, Swoole) starts every request
 * with an empty copy and a test never sees another test's answer.
 */
final class SettingsCache
{
    /** @var array<string, mixed>|null */
    private ?array $rows = null;

    /** @param  \Closure(): array<string, mixed>  $load */
    public function all(\Closure $load): array
    {
        return $this->rows ??= $load();
    }

    public function forget(): void
    {
        $this->rows = null;
    }
}
