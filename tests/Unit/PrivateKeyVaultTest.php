<?php

namespace Tests\Unit;

use App\Exceptions\PrivateKeyDecryptionException;
use App\Services\Crypto\PrivateKeyVault;
use PHPUnit\Framework\TestCase;

class PrivateKeyVaultTest extends TestCase
{
    private const PASSPHRASE = 'correct-signing-passphrase';

    private const PRIVATE_KEY = "-----BEGIN PRIVATE KEY-----\ntest-private-key\n-----END PRIVATE KEY-----\n";

    private PrivateKeyVault $vault;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vault = new PrivateKeyVault;
    }

    public function test_argon2id_derives_exactly_32_raw_bytes(): void
    {
        $derivedKey = $this->vault->deriveKey(
            self::PASSPHRASE,
            random_bytes(16),
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
        );

        $this->assertSame(32, strlen($derivedKey));

        sodium_memzero($derivedKey);
    }

    public function test_each_encryption_uses_different_salt_and_nonce(): void
    {
        $first = $this->vault->encrypt(self::PRIVATE_KEY, self::PASSPHRASE);
        $second = $this->vault->encrypt(self::PRIVATE_KEY, self::PASSPHRASE);

        $this->assertNotSame($first['salt'], $second['salt']);
        $this->assertNotSame($first['nonce'], $second['nonce']);
    }

    public function test_encrypt_then_decrypt_returns_identical_private_key(): void
    {
        $encrypted = $this->vault->encrypt(self::PRIVATE_KEY, self::PASSPHRASE);

        $this->assertSame(self::PRIVATE_KEY, $this->decrypt($encrypted, self::PASSPHRASE));
    }

    public function test_wrong_passphrase_fails_decryption_safely(): void
    {
        $encrypted = $this->vault->encrypt(self::PRIVATE_KEY, self::PASSPHRASE);

        $this->expectException(PrivateKeyDecryptionException::class);
        $this->expectExceptionMessage('Passphrase salah atau data kunci rusak');

        $this->decrypt($encrypted, 'wrong-signing-passphrase');
    }

    public function test_modified_ciphertext_fails_decryption(): void
    {
        $encrypted = $this->vault->encrypt(self::PRIVATE_KEY, self::PASSPHRASE);
        $ciphertext = base64_decode($encrypted['encrypted_private_key'], true);
        $ciphertext[0] = chr(ord($ciphertext[0]) ^ 1);
        $encrypted['encrypted_private_key'] = base64_encode($ciphertext);

        $this->expectException(PrivateKeyDecryptionException::class);

        $this->decrypt($encrypted, self::PASSPHRASE);
    }

    public function test_modified_authentication_tag_fails_decryption(): void
    {
        $encrypted = $this->vault->encrypt(self::PRIVATE_KEY, self::PASSPHRASE);
        $tag = base64_decode($encrypted['auth_tag'], true);
        $tag[0] = chr(ord($tag[0]) ^ 1);
        $encrypted['auth_tag'] = base64_encode($tag);

        $this->expectException(PrivateKeyDecryptionException::class);

        $this->decrypt($encrypted, self::PASSPHRASE);
    }

    public function test_binary_crypto_fields_are_strict_base64_with_expected_raw_lengths(): void
    {
        $encrypted = $this->vault->encrypt(self::PRIVATE_KEY, self::PASSPHRASE);

        foreach (['encrypted_private_key', 'salt', 'nonce', 'auth_tag'] as $field) {
            $decoded = base64_decode($encrypted[$field], true);

            $this->assertIsString($decoded);
            $this->assertSame($encrypted[$field], base64_encode($decoded));
        }

        $this->assertSame(16, strlen(base64_decode($encrypted['salt'], true)));
        $this->assertSame(12, strlen(base64_decode($encrypted['nonce'], true)));
        $this->assertSame(16, strlen(base64_decode($encrypted['auth_tag'], true)));
    }

    public function test_kdf_metadata_uses_actual_moderate_parameters(): void
    {
        $encrypted = $this->vault->encrypt(self::PRIVATE_KEY, self::PASSPHRASE);

        $this->assertSame(PrivateKeyVault::KDF, $encrypted['kdf']);
        $this->assertSame(SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE, $encrypted['kdf_opslimit']);
        $this->assertSame(SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE, $encrypted['kdf_memlimit']);
    }

    /**
     * @param  array{
     *     encrypted_private_key: string,
     *     salt: string,
     *     nonce: string,
     *     auth_tag: string,
     *     kdf: string,
     *     kdf_opslimit: int,
     *     kdf_memlimit: int
     * }  $encrypted
     */
    private function decrypt(array $encrypted, string $passphrase): string
    {
        return $this->vault->decrypt(
            $encrypted['encrypted_private_key'],
            $encrypted['salt'],
            $encrypted['nonce'],
            $encrypted['auth_tag'],
            $encrypted['kdf'],
            $encrypted['kdf_opslimit'],
            $encrypted['kdf_memlimit'],
            $passphrase,
        );
    }
}
