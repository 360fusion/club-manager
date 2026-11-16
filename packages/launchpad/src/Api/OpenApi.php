<?php

namespace Launchpad\Api;

use Launchpad\Site\Site;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The API's description (OpenAPI 3.1): the package's resources/openapi/launchpad.yaml with the app's own resources/openapi/*.yaml laid over it.
 * An app file may add or replace paths, components, tags and info; the servers entry is always this site's /api/v1.
 */
final class OpenApi
{
    /** @return list<string> the files read, the package's first */
    public static function files(): array
    {
        $app = glob(resource_path('openapi/*.{yaml,yml}'), GLOB_BRACE) ?: [];
        sort($app);

        return [__DIR__.'/../../resources/openapi/launchpad.yaml', ...$app];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException naming the file when one is not valid YAML
     */
    public static function build(): array
    {
        $spec = [];

        foreach (self::files() as $file) {
            try {
                $part = (array) Yaml::parseFile($file);
            } catch (ParseException $e) {
                throw new RuntimeException(basename($file).': '.$e->getMessage(), 0, $e);
            }

            foreach ($part as $key => $value) {
                $spec[$key] = match ($key) {
                    // one level deeper, so an app adds a method to a path or a schema to components without dropping the package's
                    'paths', 'components' => self::mergeTwoLevels((array) ($spec[$key] ?? []), (array) $value),
                    'info' => array_replace((array) ($spec[$key] ?? []), (array) $value),
                    'tags' => [...($spec[$key] ?? []), ...(array) $value],
                    default => $value,
                };
            }
        }

        if (($spec['info']['title'] ?? 'API') === 'API') {
            $spec['info']['title'] = Site::name().' API';
        }
        $spec['servers'] = [['url' => url('api/v1')]];

        return $spec;
    }

    /**
     * What is wrong with a description: the version, a title, paths that start with "/", an operation with no responses, and every local $ref.
     *
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    public static function problems(array $spec): array
    {
        $problems = [];

        if (! str_starts_with((string) ($spec['openapi'] ?? ''), '3.')) {
            $problems[] = 'openapi must be a 3.x version';
        }
        if (blank($spec['info']['title'] ?? null)) {
            $problems[] = 'info.title is missing';
        }

        foreach ((array) ($spec['paths'] ?? []) as $path => $operations) {
            if (! str_starts_with((string) $path, '/')) {
                $problems[] = "path {$path} must start with /";
            }
            foreach ((array) $operations as $method => $operation) {
                if (in_array($method, ['get', 'put', 'post', 'delete', 'patch', 'options', 'head'], true) && empty($operation['responses'])) {
                    $problems[] = strtoupper($method)." {$path} has no responses";
                }
            }
        }

        array_walk_recursive($spec, function ($value, $key) use ($spec, &$problems) {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, '#/') && ! self::resolves($spec, $value)) {
                $problems[] = "{$value} does not exist";
            }
        });

        return array_values(array_unique($problems));
    }

    private static function resolves(array $spec, string $ref): bool
    {
        $node = $spec;
        foreach (explode('/', substr($ref, 2)) as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);
            if (! is_array($node) || ! array_key_exists($part, $node)) {
                return false;
            }
            $node = $node[$part];
        }

        return true;
    }

    private static function mergeTwoLevels(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            $base[$key] = is_array($value) && is_array($base[$key] ?? null) ? array_replace($base[$key], $value) : $value;
        }

        return $base;
    }
}
