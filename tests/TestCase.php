<?php

namespace Tests;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Give a user an active staff membership of a club.
     *
     * Club admin routes are gated by EnsureUserCanAdministerClub, so a user
     * with no membership gets a 403 regardless of what the route does.
     */
    protected function makeClubAdmin(User $user, Club $club, string $role = 'admin'): User
    {
        $club->users()->syncWithoutDetaching([
            $user->id => ['role' => $role, 'status' => 'active'],
        ]);

        return $user;
    }
}
