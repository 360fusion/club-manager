<?php

namespace Launchpad\Workspaces;

enum Ability: string
{
    case View = 'view';
    case ViewMembers = 'view-members';
    case ManageMembers = 'manage-members';
    case ManageBilling = 'manage-billing';
    case ManageWorkspace = 'manage-workspace';
    case Transfer = 'transfer';
    case Delete = 'delete';
}
