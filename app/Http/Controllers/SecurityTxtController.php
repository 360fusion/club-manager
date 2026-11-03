<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/** /.well-known/security.txt (RFC 9116): where to report a security problem. Not served until a contact address is set. */
class SecurityTxtController extends Controller
{
    public function __invoke(): Response
    {
        $contact = config('app.security_contact');

        abort_if(blank($contact), 404);

        // A rolling expiry: the file always says it was written to be read for at least another six months.
        $body = implode("\n", [
            "Contact: mailto:{$contact}",
            'Expires: '.now()->addMonths(6)->endOfMonth()->utc()->format('Y-m-d\TH:i:s\Z'),
            'Preferred-Languages: en',
            'Canonical: '.url('/.well-known/security.txt'),
        ])."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
