<?php

namespace App\Support;

use App\Models\Club;
use App\Models\User;

/**
 * Blocks as a particular visitor is allowed to see them: elements switched off ("hidden") are left out, and a
 * members-only downloads block reaches a
 * non-member as a "log in to view" notice with its items removed, so not even the document titles leak.
 */
class PageBlocks
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    public static function forViewer(array $blocks, Club $club, ?User $viewer): array
    {
        $isMember = ClubAccess::isActiveMember($viewer, $club);

        // An element switched off in the builder is not sent at all, so nothing in it can be read from the page's data.
        $blocks = array_values(array_filter($blocks, fn ($block) => ! (is_array($block) && ! empty($block['hidden']))));

        return array_map(function (array $block) use ($isMember) {
            if (($block['type'] ?? null) === 'downloads' && ! empty($block['members_only']) && ! $isMember) {
                $block['items'] = [];
                $block['locked'] = true;
            }

            return $block;
        }, $blocks);
    }
}
