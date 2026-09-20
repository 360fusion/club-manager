<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SuperAdminGrantCommand extends Command
{
    protected $signature = 'superadmin:grant {email : The email address of the user to promote}
                            {--revoke : Remove super admin access instead of granting it}';

    protected $description = 'Grant or revoke super admin access for a user';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $revoking = (bool) $this->option('revoke');

        if ($user->is_super_admin === ! $revoking) {
            $this->info("[{$email}] already has that access level. Nothing to do.");

            return self::SUCCESS;
        }

        $user->forceFill(['is_super_admin' => ! $revoking])->save();

        $this->info($revoking
            ? "Revoked super admin access for [{$email}]."
            : "Granted super admin access to [{$email}].");

        return self::SUCCESS;
    }
}
