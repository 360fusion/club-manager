<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubType;

class TerminologyService
{
    /**
     * Get order-specific terminology for a given club or club type.
     */
    public function getTerminology(Club|ClubType|null $clubOrType): array
    {
        $clubType = null;
        if ($clubOrType instanceof Club) {
            $clubType = $clubOrType->clubType;
        } elseif ($clubOrType instanceof ClubType) {
            $clubType = $clubOrType;
        }

        $defaults = [
            'meeting' => 'Meeting',
            'meetings' => 'Meetings',
            'summons' => 'Summons',
            'festive_board' => 'Festive Board',
            'alms' => 'Alms Plate',
            'head_officer' => 'Worshipful Master',
            'organisation_unit' => 'Lodge',
            'member_term' => 'Brethren / Member',
        ];

        if (! $clubType || empty($clubType->terminology)) {
            return $defaults;
        }

        return array_merge($defaults, $clubType->terminology);
    }

    /**
     * Resolve a specific term key for a club.
     */
    public function term(Club|ClubType|null $clubOrType, string $key, ?string $fallback = null): string
    {
        $terms = $this->getTerminology($clubOrType);

        return $terms[$key] ?? $fallback ?? ucfirst(str_replace('_', ' ', $key));
    }
}
