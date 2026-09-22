<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\SignatureRequest;

/**
 * Asks a named person to sign a document, with a private link that needs no login.
 */
class SignatureRequestMail extends EventTemplatedMail
{
    public function __construct(public SignatureRequest $request, public string $signUrl)
    {
        $this->onQueue('transactional');
    }

    protected function club(): Club
    {
        return $this->request->club;
    }

    protected function templateKey(): string
    {
        return 'signature_request';
    }

    protected function values(): array
    {
        return [
            'text' => [
                'club_name' => $this->club()->name,
                'signer_name' => $this->request->signer_name,
                'document_label' => $this->request->signable->signatureLabel($this->request->purpose),
                'sign_url' => $this->signUrl,
            ],
            'html' => [],
        ];
    }

    protected function fallbackSubject(): string
    {
        return 'Please sign: '.$this->request->signable->signatureLabel($this->request->purpose);
    }

    protected function fallbackBody(): string
    {
        $values = $this->values()['text'];

        return '<h2>A signature is needed</h2><p>Hello '.e($values['signer_name']).',</p><p>'.e($this->club()->name).' has asked you to sign: <strong>'.e($values['document_label']).'</strong>.</p><p><a href="'.e($values['sign_url']).'">Review and sign</a></p>';
    }
}
