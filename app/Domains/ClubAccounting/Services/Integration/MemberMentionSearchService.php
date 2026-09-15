<?php

namespace App\Domains\ClubAccounting\Services\Integration;

use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Collection;

class MemberMentionSearchService
{
    /**
     * Search club members by name or email for quick @mention autocomplete.
     */
    public function search(int $clubId, string $query = '', int $limit = 10): Collection
    {
        $club = Club::findOrFail($clubId);
        $cleanQuery = trim($query);

        return $club->users()
            ->when($cleanQuery !== '', function ($q) use ($cleanQuery) {
                $q->where(function ($sub) use ($cleanQuery) {
                    $sub->where('name', 'like', "%{$cleanQuery}%")
                        ->orWhere('email', 'like', "%{$cleanQuery}%");
                });
            })
            ->select('users.id', 'users.name', 'users.email')
            ->limit($limit)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role ?? 'Member',
                'mention_tag' => '@' . $user->name,
            ]);
    }
}
