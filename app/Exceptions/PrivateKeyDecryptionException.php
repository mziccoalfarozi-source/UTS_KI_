<?php

namespace App\Exceptions;

use RuntimeException;

class PrivateKeyDecryptionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Passphrase salah atau data kunci rusak');
    }
}
