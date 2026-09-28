<?php

namespace App\Enums;

enum VerificationMethod: string
{
    case Token = 'TOKEN';
    case Upload = 'UPLOAD';
    case UploadCustomKey = 'UPLOAD_CUSTOM_KEY';
}
