<?php

namespace Tests\Unit;

use App\Services\Crypto\KeyPairService;
use App\Services\Crypto\SignatureService;
use InvalidArgumentException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use PHPUnit\Framework\TestCase;

class SignatureServiceTest extends TestCase
{
    public function test_signs_binary_digest_with_exact_rsa_pss_parameters(): void
    {
        $keyPair = (new KeyPairService)->generate();
        $digest = hash('sha256', 'finalized-pdf-bytes', true);

        try {
            $signature = (new SignatureService)->sign($digest, $keyPair['private_key']);
            $verifier = PublicKeyLoader::loadPublicKey($keyPair['public_key'])
                ->withPadding(RSA::SIGNATURE_PSS)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->withSaltLength(32);

            $this->assertSame(32, strlen($digest));
            $this->assertSame(256, strlen($signature));
            $this->assertTrue($verifier->verify($digest, $signature));
        } finally {
            sodium_memzero($keyPair['private_key']);
        }
    }

    public function test_rejects_hexadecimal_hash_text_instead_of_binary_digest(): void
    {
        $keyPair = (new KeyPairService)->generate();

        try {
            $this->expectException(InvalidArgumentException::class);
            (new SignatureService)->sign(hash('sha256', 'finalized-pdf-bytes'), $keyPair['private_key']);
        } finally {
            sodium_memzero($keyPair['private_key']);
        }
    }

    public function test_wrong_public_key_and_modified_digest_fail_verification(): void
    {
        $keyPair = (new KeyPairService)->generate();
        $otherKeyPair = (new KeyPairService)->generate();
        $digest = hash('sha256', 'finalized-pdf-bytes', true);
        $modifiedDigest = hash('sha256', 'modified-finalized-pdf-bytes', true);
        $service = new SignatureService;

        try {
            $signature = $service->sign($digest, $keyPair['private_key']);

            $this->assertTrue($service->verify($digest, $signature, $keyPair['public_key']));
            $this->assertFalse($service->verify($digest, $signature, $otherKeyPair['public_key']));
            $this->assertFalse($service->verify($modifiedDigest, $signature, $keyPair['public_key']));
        } finally {
            sodium_memzero($keyPair['private_key']);
            sodium_memzero($otherKeyPair['private_key']);
        }
    }
}
