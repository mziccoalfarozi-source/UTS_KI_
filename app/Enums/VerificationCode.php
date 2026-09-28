<?php

namespace App\Enums;

enum VerificationCode: string
{
    case Valid = 'VALID';
    case Incomplete = 'INCOMPLETE';
    case InvalidDocumentModified = 'INVALID_DOCUMENT_MODIFIED';
    case InvalidSignature = 'INVALID_SIGNATURE';
    case InvalidPublicKey = 'INVALID_PUBLIC_KEY';
    case RejectedTokenNotFound = 'REJECTED_TOKEN_NOT_FOUND';
}
