<?php

namespace App\Exceptions;

use RuntimeException;

class DocumentFinalizationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('PDF tidak dapat diproses untuk finalization.');
    }
}
