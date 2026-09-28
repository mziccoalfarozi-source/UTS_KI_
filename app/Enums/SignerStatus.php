<?php

namespace App\Enums;

enum SignerStatus: string
{
    case Pending = 'PENDING';
    case Signed = 'SIGNED';
}
