<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case WaitingSignature = 'WAITING_SIGNATURE';
    case PartiallySigned = 'PARTIALLY_SIGNED';
    case Completed = 'COMPLETED';
}
