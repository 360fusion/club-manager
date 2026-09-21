<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every free-text, number and list field must have an upper bound, so nobody can
 * submit thousands of words in a name field or a million-item list.
 */
class ValidationLimitsTest extends TestCase
{
    public function test_string_numeric_and_array_rules_all_have_a_max(): void
    {
        $offenders = [];
        $bounds = ['max:', 'size:', 'between:', 'in:', 'digits', 'regex:', 'exists:', 'enum', 'uuid', 'mimes', 'dimensions', 'boolean', 'date', 'confirmed', 'file', 'image'];

        foreach (['Http', 'Domains', 'Actions'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/'.$dir, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Models/')) {
                    continue;
                }

                foreach (file($file->getPathname()) as $number => $line) {
                    if (! preg_match("/^\s*'([A-Za-z0-9_.*]+)' => (\[)?'((?:required|nullable|sometimes|present)[^']*)'/", $line, $m)) {
                        continue;
                    }

                    [$key, $rules] = [$m[1], $m[3]];
                    $leaf = substr($key, strrpos('.'.$key, '.'));

                    if (! preg_match('/\b(string|numeric|integer|array|email)\b/', $rules) || preg_match('/(^id$|_ids?$|\*$)/', $leaf)) {
                        continue;
                    }

                    foreach ($bounds as $bound) {
                        if (str_contains($line, $bound)) {
                            continue 2;
                        }
                    }

                    $offenders[] = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname()).':'.($number + 1).' '.$key;
                }
            }
        }

        $this->assertSame([], $offenders, "Validation rules without an upper bound:\n".implode("\n", $offenders));
    }
}
