<?php

namespace App\Support;

/**
 * Finds the Node and npm programs that Browsershot needs to print a PDF. Only fixed names are looked up, never
 * anything a user typed.
 */
class NodeBinaries
{
    /**
     * @return array{node: string|null, npm: string|null}
     */
    public static function find(): array
    {
        $found = [];

        foreach (['node', 'npm'] as $name) {
            $path = trim((string) shell_exec("which {$name} 2>/dev/null"));

            if (! $path || ! file_exists($path)) {
                $candidates = [
                    ...(glob("/Users/*/Library/Application Support/Herd/config/nvm/versions/node/*/bin/{$name}") ?: []),
                    "/opt/homebrew/bin/{$name}", "/usr/local/bin/{$name}", "/usr/bin/{$name}",
                ];
                $path = collect($candidates)->first(fn (string $candidate) => file_exists($candidate)) ?? '';
            }

            $found[$name] = $path ?: null;
        }

        return $found;
    }
}
