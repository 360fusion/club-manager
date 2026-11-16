<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The English sentences are the translation keys, so what is untranslated is found by reading the code for the calls that use them.
 */
class LangMissingCommand extends Command
{
    protected $signature = 'launchpad:lang:missing {locale : the language code, e.g. fr}
        {--vue : also read the $t() and $tc() calls of the Vue pages}
        {--scan=* : directories to read instead of the package\'s own (for a build checking its own code)}';

    protected $description = 'List the sentences used in the code that a language\'s JSON file lacks';

    /**
     * The helpers __, trans, trans_choice, @lang and Launchpad's Trans::html with a string literal as the first argument (so not a variable, a concatenation or an
     * interpolated "$x"), not the method of some other object. Group 1 is the quote, group 2 the text with its escapes.
     */
    private const PHP_CALL = '/(?<![\w$>:])(?:__|trans|trans_choice|@lang|Trans::html)\(\s*([\'"])((?:\\\\.|(?!\1)[^\\\\])*)\1\s*[,)]/s';

    /** Vue's $t and $tc, also as this.$t */
    private const VUE_CALL = '/(?<![\w$])\$tc?\(\s*([\'"])((?:\\\\.|(?!\1)[^\\\\])*)\1\s*[,)]/s';

    /** The Vue pages' <Trans k="a sentence with :placeholders"> (a sentence with a link or emphasis in it), the sentence in double quotes */
    private const VUE_TRANS = '/<Trans\s[^>]*?\bk="([^"]*)"/s';

    public function handle(): int
    {
        $locale = (string) $this->argument('locale');
        $base = dirname(__DIR__, 2);
        $roots = $this->option('scan') ?: [$base.'/src', $base.'/resources/views', ...($this->option('vue') ? [$base.'/stubs/vue/resources/js'] : [])];

        // a path that moved must not read as "nothing missing"
        foreach ($roots as $root) {
            if (! File::isDirectory($root)) {
                $this->error("Not a directory, so nothing could be read there: {$root}");

                return 2;
            }
        }

        $found = [];
        foreach ($roots as $root) {
            $files = File::allFiles($root);
            $this->line('Reading '.count($files).' file(s) in '.$root);
            foreach ($files as $file) {
                foreach ($this->keysIn($file->getPathname(), (string) $file->getExtension()) as $key) {
                    $found[$key] = true;
                }
            }
        }

        // the loader merges the app's lang/<locale>.json over the package's; a line counts as translated when it is there, even if it equals the English
        $lines = app('translator')->getLoader()->load($locale, '*', '*');
        $missing = array_values(array_filter(array_keys($found), fn (string $key) => ! array_key_exists($key, $lines)));
        sort($missing);

        foreach ($missing as $key) {
            $this->line($key);
        }
        $this->{$missing ? 'error' : 'info'}(count($missing).' of '.count($found)." sentence(s) missing from {$locale}.");

        return $missing ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<string> */
    private function keysIn(string $path, string $extension): array
    {
        $pattern = match ($extension) {
            'php' => self::PHP_CALL,
            'vue', 'js', 'ts' => $this->option('vue') ? self::VUE_CALL : null,
            default => null,
        };

        $text = $pattern ? File::get($path) : '';
        $keys = [];

        if ($pattern === self::VUE_CALL && preg_match_all(self::VUE_TRANS, $text, $trans)) {
            $keys = array_map('html_entity_decode', $trans[1]);
        }

        if (! $pattern || ! preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return $keys;
        }

        foreach ($matches as [, $quote, $text]) {
            // "Hello $name" is built at runtime
            if ($quote === '"' && preg_match('/(?<!\\\\)\$/', $text)) {
                continue;
            }
            $key = preg_replace('/\\\\([\'"\\\\])/', '$1', $text);
            // auth.failed, validation.unique, launchpad::group.item: the framework's own lines are not bundled
            if (! preg_match('/^(?:[a-z0-9_-]+::)?[a-z0-9_-]+(?:\.[a-z0-9_-]+)+$/', $key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
