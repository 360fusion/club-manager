<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RsvpTokenService
{
    /**
     * Create a cryptographically signed raw token for passwordless email RSVPs.
     * Only the SHA-256 hash is saved to the database.
     */
    public function createTokenForUser(Meeting $meeting, User $user, Carbon $expiresAt): string
    {
        $entropy = Str::random(40);
        $payload = "meeting:{$meeting->id}:user:{$user->id}:exp:{$expiresAt->timestamp}:{$entropy}";

        $rawToken = hash_hmac('sha256', $payload, config('app.key'));
        $tokenHash = hash('sha256', $rawToken);

        MeetingRsvp::updateOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $user->id],
            [
                'token_hash' => $tokenHash,
                'token_expires_at' => $expiresAt,
            ]
        );

        return $rawToken;
    }

    /**
     * Validate an incoming raw token string.
     */
    public function validateToken(string $rawToken): ?MeetingRsvp
    {
        $tokenHash = hash('sha256', $rawToken);

        $rsvp = MeetingRsvp::where('token_hash', $tokenHash)
            ->with(['meeting.club', 'user', 'guests'])
            ->first();

        if (! $rsvp) {
            return null;
        }

        if (Carbon::now()->isAfter($rsvp->token_expires_at)) {
            return null;
        }

        return $rsvp;
    }
}
