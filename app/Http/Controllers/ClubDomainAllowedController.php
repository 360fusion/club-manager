<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Asked by Caddy (on-demand TLS) before it issues a certificate for a host: yes only for a lodge domain whose DNS
 * check has passed. Answers only callers on the server itself, so nobody can probe it or make the server request
 * certificates for other domains.
 */
class ClubDomainAllowedController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // REMOTE_ADDR, not $request->ip(): a trusted-proxy header must not be able to pass as the server itself.
        abort_unless(in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true), 404);

        $allowed = Club::where('custom_domain', strtolower((string) $request->query('domain')))
            ->where('domain_status', 'active')
            ->exists();

        return response('', $allowed ? 200 : 404);
    }
}
