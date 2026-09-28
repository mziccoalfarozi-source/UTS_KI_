<?php

namespace App\Services\Crypto;

use App\Exceptions\PrivateKeyDecryptionException;
use RuntimeException;
use Throwable;

class PrivateKeyVault
{
    public const KDF = 'ARGON2ID13';

    private const CIPHER = 'aes-256-gcm';

    private const DERIVED_KEY_BYTES = 32;

    private const SALT_BYTES = 16;

    private const NONCE_BYTES = 12;

    private const TAG_BYTES = 16;

    /**
     * @return array{
     *     encrypted_private_key: string,
     *     salt: string,
     *     nonce: string,
     *     auth_tag: string,
     *     kdf: string,
     *     kdf_opslimit: int,
     *     kdf_memlimit: int
     * }
     */
    public function encrypt(string $privateKey, string $passphrase): array
    {
        $salt = random_bytes(self::SALT_BYTES);
        $nonce = random_bytes(self::NONCE_BYTES);
        $opslimit = SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE;
        $memlimit = SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE;
        $derivedKey = $this->deriveKey($passphrase, $salt, $opslimit, $memlimit);

        try {
            $tag = '';
            $ciphertext = openssl_encrypt(
                $privateKey,
                self::CIPHER,
                $derivedKey,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                '',
                self::TAG_BYTES,
            );

            if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
                throw new RuntimeException('Gagal melindungi private key.');
            }

            return [
                'encrypted_private_key' => base64_encode($ciphertext),
                'salt' => base64_encode($salt),
                'nonce' => base64_encode($nonce),
                'auth_tag' => base64_encode($tag),
                'kdf' => self::KDF,
                'kdf_opslimit' => $opslimit,
                'kdf_memlimit' => $memlimit,
            ];
        } finally {
            sodium_memzero($derivedKey);
        }
    }

    public function decrypt(
        string $encryptedPrivateKey,
        string $salt,
        string $nonce,
        string $authTag,
        string $kdf,
        int $opslimit,
        int $memlimit,
        string $passphrase,
    ): string {
        $derivedKey = null;

        try {
            if ($kdf !== self::KDF) {
                throw new PrivateKeyDecryptionException;
            }

            $rawCiphertext = $this->decodeBase64($encryptedPrivateKey);
            $rawSalt = $this->decodeBase64($salt, self::SALT_BYTES);
            $rawNonce = $this->decodeBase64($nonce, self::NONCE_BYTES);
            $rawAuthTag = $this->decodeBase64($authTag, self::TAG_BYTES);
            $derivedKey = $this->deriveKey($passphrase, $rawSalt, $opslimit, $memlimit);

            $privateKey = openssl_decrypt(
                $rawCiphertext,
                self::CIPHER,
                $derivedKey,
                OPENSSL_RAW_DATA,
                $rawNonce,
                $rawAuthTag,
            );

            if ($privateKey === false) {
                throw new PrivateKeyDecryptionException;
            }

            return $privateKey;
        } catch (PrivateKeyDecryptionException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new PrivateKeyDecryptionException;
        } finally {
            if (is_string($derivedKey)) {
                sodium_memzero($derivedKey);
            }
        }
    }

    public function deriveKey(string $passphrase, string $salt, int $opslimit, int $memlimit): string
    {
        if (strlen($salt) !== self::SALT_BYTES || $opslimit <= 0 || $memlimit <= 0) {
            throw new RuntimeException('Parameter derivasi key tidak valid.');
        }

        return sodium_crypto_pwhash(
            self::DERIVED_KEY_BYTES,
            $passphrase,
            $salt,
            $opslimit,
            $memlimit,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }

    private function decodeBase64(string $value, ?int $expectedBytes = null): string
    {
        $decoded = base64_decode($value, true);

        if (
            $decoded === false
            || $decoded === ''
            || base64_encode($decoded) !== $value
            || ($expectedBytes !== null && strlen($decoded) !== $expectedBytes)
        ) {
            throw new PrivateKeyDecryptionException;
        }

        return $decoded;
    }
}
