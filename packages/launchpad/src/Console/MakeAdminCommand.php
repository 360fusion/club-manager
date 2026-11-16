<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Admin\AdminLog;
use Launchpad\Launchpad;

/**
 * `php artisan launchpad:make-admin you@example.com` makes an existing account a member of staff, or (with --name) makes the account first,
 * with a password it prints once. The first sign-in to the admin asks to set up an authenticator app.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'launchpad:make-admin {email} {--role=admin : admin or support} {--name= : Make the account if there is none}';

    protected $description = 'Make an account a member of staff';

    public function handle(): int
    {
        $role = $this->option('role');
        if (! in_array($role, ['admin', 'support'], true)) {
            $this->error('The role is admin or support.');

            return self::FAILURE;
        }

        $email = AccountRegistration::normalizeEmail($this->argument('email'));
        $user = AccountRegistration::findByEmail($email);

        if ($user === null) {
            if (! $this->option('name')) {
                $this->error("No account has {$email}. Sign up first, or add --name=\"Your Name\" to make one.");

                return self::FAILURE;
            }

            $password = Str::password(20, symbols: false);
            $user = Launchpad::userModel()::create(['name' => AccountRegistration::cleanName($this->option('name')), 'email' => $email, 'password' => Hash::make($password)]);
            $user->forceFill(['email_verified_at' => now(), 'password_set_at' => now()])->save();
            $this->line("Made the account. Its password is {$password} (shown once: change it after signing in).");
        }

        $user->forceFill(['staff_role' => $role])->save();
        AdminLog::record('user.staff_changed', $user, "{$email} made {$role} from the console", ['staff_role' => null], ['staff_role' => $role], $user);
        $this->info("{$email} is now staff ({$role}). Sign in at /".trim((string) config('launchpad.admin.path'), '/').'.');

        return self::SUCCESS;
    }
}
