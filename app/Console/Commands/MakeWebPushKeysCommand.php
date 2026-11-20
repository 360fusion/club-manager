<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Makes the key pair the website signs its phone notifications with (Web Push), and writes it to .env without showing
 * the private key. Run once per install: a new pair stops every browser that already said yes from getting them.
 */
class MakeWebPushKeysCommand extends Command
{
    protected $signature = 'app:make-web-push-keys {--force : replace keys that are already set}';

    protected $description = 'Make the key pair for notifications from the website and save it in .env';

    public function handle(): int
    {
        $path = base_path('.env');
        $env = is_readable($path) ? (string) file_get_contents($path) : '';

        if (preg_match('/^WEB_PUSH_PRIVATE_KEY=.+$/m', $env) && ! $this->option('force')) {
            $this->warn('Keys are already set. Replacing them stops notifications to everyone who turned them on; use --force if you mean it.');

            return self::FAILURE;
        }

        $keys = VAPID::createVapidKeys();
        foreach (['WEB_PUSH_PUBLIC_KEY' => $keys['publicKey'], 'WEB_PUSH_PRIVATE_KEY' => $keys['privateKey']] as $name => $value) {
            $line = $name.'='.$value;
            $env = preg_match("/^{$name}=.*$/m", $env) ? (string) preg_replace("/^{$name}=.*$/m", $line, $env) : rtrim($env)."\n".$line."\n";
        }
        file_put_contents($path, $env);

        $this->info('Saved WEB_PUSH_PUBLIC_KEY and WEB_PUSH_PRIVATE_KEY in .env. Clear the config cache if it is cached.');

        return self::SUCCESS;
    }
}
