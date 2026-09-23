<?php

namespace App\Services\Signatures;

use App\Enums\SignatureRequestStatus;
use App\Models\SignatureRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfBuilder;
use InvalidArgumentException;

/**
 * The one-page proof of a completed signature: what was signed, by whom, how, and when. Downloadable by the
 * signer themselves and by the club's own staff, once (and only once) a request has actually been signed.
 */
class SignatureCertificatePdf
{
    public function render(SignatureRequest $request): DomPdfBuilder
    {
        if ($request->status !== SignatureRequestStatus::Signed) {
            throw new InvalidArgumentException('Only a signed request has a certificate.');
        }

        $signature = $this->renderedSignature($request);

        return Pdf::loadView('pdf.signatures.certificate', [
            'request' => $request,
            'club' => $request->club,
            'documentLabel' => $request->signable->signatureLabel($request->purpose),
            'signatureImage' => $signature['method'] === 'drawn' ? $signature['value'] : null,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif']);
    }

    public function filename(SignatureRequest $request): string
    {
        return 'signature-'.$request->id.'-'.$request->signed_at?->format('Y-m-d').'.pdf';
    }

    /**
     * A signed request's mark, ready to drop straight into any PDF: the typed name, or the drawn image as a
     * base64 data URI (the file lives on the private disk, so a plain <img src> to it would not work).
     *
     * @return array{method: string, value: string}
     */
    public function renderedSignature(SignatureRequest $request): array
    {
        if ($request->method === 'drawn' && ($media = $request->getFirstMedia('signature'))) {
            return ['method' => 'drawn', 'value' => 'data:'.$media->mime_type.';base64,'.base64_encode((string) file_get_contents($media->getPath()))];
        }

        return ['method' => 'typed', 'value' => (string) $request->typed_name];
    }
}
