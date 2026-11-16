<?php

namespace Launchpad\Workspaces;

use Launchpad\Models\Membership;
use Launchpad\Models\Workspace;

/** The workspace the request is for and the signed-in person's membership of it. Bound as scoped: one per request. */
final class CurrentWorkspace
{
    public ?Workspace $workspace = null;

    public ?Membership $membership = null;

    public function set(Workspace $workspace, Membership $membership): void
    {
        $this->workspace = $workspace;
        $this->membership = $membership;
    }

    public function role(): ?Role
    {
        return $this->membership?->role;
    }

    public function can(Ability|string $ability): bool
    {
        return $this->membership?->can($ability) ?? false;
    }
}
