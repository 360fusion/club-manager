<?php

namespace App\Http\Controllers;

use App\Enums\SignatureRequestStatus;
use App\Models\Club;
use App\Models\SignatureRequest;
use App\Services\Signatures\SignatureCertificatePdf;

/**
 * Downloads the signed certificate for a request made at a club, whatever it was for. Mounted twice, once under
 * /admin/accounting and once under /admin/candidates, so EnsureUserCanAdministerClub applies that area's own
 * capability (manage_billing or manage_members) rather than a generic, unmapped one.
 */
class SignatureAdminController extends Controller
{
    public function downloadPdf(string $clubSlug, int $id, SignatureCertificatePdf $pdf)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $request = SignatureRequest::where('club_id', $club->id)->findOrFail($id);

        abort_unless($request->status === SignatureRequestStatus::Signed, 404);

        return $pdf->render($request)->download($pdf->filename($request));
    }
}
