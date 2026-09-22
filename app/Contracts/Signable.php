<?php

namespace App\Contracts;

/**
 * A model that a signature request can point at. Keeps the signature system decoupled from what it is signing.
 */
interface Signable
{
    /**
     * A plain-language description of what is being signed, shown to the signer and used in the request email.
     */
    public function signatureLabel(string $purpose): string;
}
