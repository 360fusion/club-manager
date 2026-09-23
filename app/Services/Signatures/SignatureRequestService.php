<?php

namespace App\Services\Signatures;

use App\Domains\ClubAccounting\Models\Member;
use App\Enums\SignatureRequestStatus;
use App\Events\SignatureCompleted;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Notifications\ClubNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Requesting a signature from a named person and recording what they did about it. One open request exists per
 * signable+purpose at a time: asking again reissues the same row with a fresh token rather than piling up history,
 * since the signature itself (once given) is the record that matters.
 */
class SignatureRequestService
{
    public function __construct(private readonly SignatureMailer $mailer) {}

    /**
     * Create (or reissue, if one is still pending) a signature request and email the link.
     */
    public function request(
        Model $signable,
        string $purpose,
        Model $signer,
        string $signerName,
        ?string $signerEmail,
        User $requestedBy,
        ?CarbonInterface $expiresAt = null,
    ): SignatureRequest {
        $existing = SignatureRequest::query()
            ->where('signable_type', $signable->getMorphClass())
            ->where('signable_id', $signable->getKey())
            ->where('purpose', $purpose)
            ->where('status', SignatureRequestStatus::Pending)
            ->first();

        $request = $existing ?? new SignatureRequest([
            'club_id' => $signable->club_id ?? $signable->club?->id,
            'signable_type' => $signable->getMorphClass(),
            'signable_id' => $signable->getKey(),
            'purpose' => $purpose,
        ]);

        $request->fill([
            'signer_type' => $signer->getMorphClass(),
            'signer_id' => $signer->getKey(),
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'status' => SignatureRequestStatus::Pending,
            'requested_by_user_id' => $requestedBy->id,
            'requested_at' => now(),
            'expires_at' => $expiresAt,
            'method' => null,
            'typed_name' => null,
            'consent_ip' => null,
            'consent_user_agent' => null,
            'signed_at' => null,
            'document_hash' => null,
        ]);

        $token = $this->issueToken($request);
        $request->save();
        $request->plainToken = $token;

        $this->mailer->requested($request);
        $this->notifySigner($request);

        return $request;
    }

    /**
     * A fresh link for a request that is still pending (the old one stops working).
     */
    public function resend(SignatureRequest $request): SignatureRequest
    {
        if ($request->status !== SignatureRequestStatus::Pending) {
            throw new InvalidArgumentException('Only a pending request can be resent.');
        }

        $token = $this->issueToken($request);
        $request->save();
        $request->plainToken = $token;

        $this->mailer->requested($request);
        $this->notifySigner($request);

        return $request;
    }

    /**
     * The signer's own login, when they have one: the User directly, or the User linked to the roster Member.
     * Used both to decide whether an in-app notification is possible, and to authorise the in-app sign page.
     */
    public function signerUser(SignatureRequest $request): ?User
    {
        if ($request->signer_type === User::class) {
            return $request->signer;
        }

        if ($request->signer_type === Member::class) {
            return $request->signer?->user;
        }

        return null;
    }

    private function notifySigner(SignatureRequest $request): void
    {
        if ($user = $this->signerUser($request)) {
            $user->notify(ClubNotification::signature($request));
        }
    }

    public function cancel(SignatureRequest $request): SignatureRequest
    {
        if ($request->status !== SignatureRequestStatus::Pending) {
            throw new InvalidArgumentException('Only a pending request can be cancelled.');
        }

        $request->update(['status' => SignatureRequestStatus::Cancelled]);

        return $request;
    }

    /**
     * The one entry point admin screens should call when a form is (re)submitted: does nothing if this purpose
     * already has an active request (pending or signed) — even for a newly-picked, different person, since
     * changing who is asked requires explicitly cancelling their current request first via cancel(). Only
     * creates a fresh request when the purpose is genuinely open (never asked, or cancelled/declined/expired).
     */
    public function requestIfOpen(Model $signable, string $purpose, Model $signer, string $signerName, ?string $signerEmail, User $requestedBy, ?CarbonInterface $expiresAt = null): SignatureRequest
    {
        $existing = SignatureRequest::query()
            ->where('signable_type', $signable->getMorphClass())
            ->where('signable_id', $signable->getKey())
            ->where('purpose', $purpose)
            ->whereIn('status', [SignatureRequestStatus::Pending, SignatureRequestStatus::Signed])
            ->latest('requested_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->request($signable, $purpose, $signer, $signerName, $signerEmail, $requestedBy, $expiresAt);
    }

    /**
     * Look a request up by its plaintext link token, without leaking timing information about which tokens exist.
     */
    public function findByToken(string $token): ?SignatureRequest
    {
        if (strlen($token) < 20) {
            return null;
        }

        $request = SignatureRequest::where('token_hash', hash('sha256', $token))->first();

        if ($request && $request->status === SignatureRequestStatus::Pending && $request->isExpired()) {
            $request->update(['status' => SignatureRequestStatus::Expired]);
        }

        return $request?->fresh();
    }

    /**
     * Record the signature: a typed name, or a drawn image uploaded as a file.
     *
     * @param  array{typed_name?: string, image?: UploadedFile}  $data
     */
    public function sign(SignatureRequest $request, string $method, array $data, string $ip, string $userAgent): SignatureRequest
    {
        if ($request->status !== SignatureRequestStatus::Pending) {
            throw new InvalidArgumentException('This request has already been actioned.');
        }

        if ($request->isExpired()) {
            $request->update(['status' => SignatureRequestStatus::Expired]);
            throw new InvalidArgumentException('This signature link has expired.');
        }

        if (! in_array($method, ['typed', 'drawn'], true)) {
            throw new InvalidArgumentException('Unknown signature method.');
        }

        return DB::transaction(function () use ($request, $method, $data, $ip, $userAgent) {
            $request->update([
                'method' => $method,
                'typed_name' => $method === 'typed' ? $data['typed_name'] : null,
                'status' => SignatureRequestStatus::Signed,
                'signed_at' => now(),
                'consent_ip' => $ip,
                'consent_user_agent' => $userAgent,
                'document_hash' => $this->hashSignable($request),
            ]);

            if ($method === 'drawn' && isset($data['image'])) {
                $request->addMedia($data['image'])->toMediaCollection('signature', 'local');
            }

            event(new SignatureCompleted($request->fresh()));

            return $request->fresh();
        });
    }

    public function decline(SignatureRequest $request, ?string $reason, string $ip, string $userAgent): SignatureRequest
    {
        if ($request->status !== SignatureRequestStatus::Pending) {
            throw new InvalidArgumentException('This request has already been actioned.');
        }

        $request->update([
            'status' => SignatureRequestStatus::Declined,
            'notes' => $reason,
            'consent_ip' => $ip,
            'consent_user_agent' => $userAgent,
        ]);

        return $request;
    }

    /**
     * All requests for one signable, grouped by purpose — the shape a "who has signed" panel needs.
     *
     * @return Collection<int, SignatureRequest>
     */
    public function forSignable(Model $signable): Collection
    {
        return SignatureRequest::query()
            ->where('signable_type', $signable->getMorphClass())
            ->where('signable_id', $signable->getKey())
            ->latest('requested_at')
            ->get();
    }

    private function issueToken(SignatureRequest $request): string
    {
        $token = Str::random(40);
        $request->token_hash = hash('sha256', $token);

        return $token;
    }

    /**
     * A stable snapshot of what the signer was shown, so a later dispute can prove nothing changed underneath them.
     */
    private function hashSignable(SignatureRequest $request): string
    {
        $signable = $request->signable;

        return hash('sha256', $request->purpose.'|'.$signable->getMorphClass().'|'.$signable->getKey().'|'.$signable->updated_at?->toIso8601String());
    }
}
