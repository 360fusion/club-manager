<?php

namespace Launchpad\Accounts;

use Illuminate\Database\Eloquent\Model;

/**
 * Fired just before a closed account is removed for good. An app listens to delete what it keeps for the person (their files, their rows in
 * its own tables); the package removes the account, its social links, its memberships and the workspaces it owned.
 */
class AccountPurging
{
    public function __construct(public Model $user) {}
}
