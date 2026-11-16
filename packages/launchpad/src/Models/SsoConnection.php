<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A team's single sign-on through its company's OpenID Connect provider, for the people whose email is on its (verified) domain */
class SsoConnection extends Model
{
    protected $guarded = [];

    protected $hidden = ['client_secret', 'scim_token_hash'];

    protected function casts(): array
    {
        return ['client_secret' => 'encrypted', 'domain_verified_at' => 'datetime', 'enforce' => 'boolean'];
    }

    /** The connection that signs in this address: its domain's, once the domain is verified */
    public static function forEmail(?string $email): ?self
    {
        $domain = strtolower(Str::afterLast(trim((string) $email), '@'));

        return $domain === '' || ! str_contains((string) $email, '@') ? null : self::where('domain', $domain)->whereNotNull('domain_verified_at')->first();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
