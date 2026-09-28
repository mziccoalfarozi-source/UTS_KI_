<?php

namespace Tests\Unit;

use App\Services\Crypto\KeyPairService;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;

class KeyPairServiceTest extends TestCase
{
    /** @var array{private_key: string, public_key: string} */
    private static array $keyPair;

    public static function setUpBeforeClass(): void
    {
        self::$keyPair = (new KeyPairService)->generate();
    }

    public function test_rsa_keypair_is_generated(): void
    {
        $this->assertStringContainsString('BEGIN PRIVATE KEY', self::$keyPair['private_key']);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', self::$keyPair['public_key']);
    }

    public function test_rsa_key_size_is_2048_bits(): void
    {
        $privateKey = openssl_pkey_get_private(self::$keyPair['private_key']);

        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $privateKey);
        $this->assertSame(2048, openssl_pkey_get_details($privateKey)['bits']);
    }

    public function test_public_key_is_valid_rsa_key(): void
    {
        $publicKey = openssl_pkey_get_public(self::$keyPair['public_key']);

        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $publicKey);
        $this->assertSame(OPENSSL_KEYTYPE_RSA, openssl_pkey_get_details($publicKey)['type']);
    }

    public function test_private_key_is_valid_rsa_key(): void
    {
        $privateKey = openssl_pkey_get_private(self::$keyPair['private_key']);

        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $privateKey);
        $this->assertSame(OPENSSL_KEYTYPE_RSA, openssl_pkey_get_details($privateKey)['type']);
    }
}
