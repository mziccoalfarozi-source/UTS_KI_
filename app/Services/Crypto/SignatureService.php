<?php

namespace App\Services\Crypto;

use InvalidArgumentException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey as RsaPrivateKey;
use phpseclib3\Crypt\RSA\PublicKey as RsaPublicKey;
use RuntimeException;
use Throwable;

final class SignatureService
{
    private const DIGEST_BYTES = 32;

    private const RSA_BITS = 2048;

    private const SIGNATURE_BYTES = 256;

    private const PSS_SALT_BYTES = 32;

    public function sign(string $digest, string $privateKeyPem): string
    {
        $this->assertDigest($digest);

        try {
            $privateKey = PublicKeyLoader::loadPrivateKey($privateKeyPem);

            if (! $privateKey instanceof RsaPrivateKey || $privateKey->getLength() !== self::RSA_BITS) {
                throw new RuntimeException('Private key RSA tidak valid.');
            }

            $signature = $this->configure($privateKey)->sign($digest);

            if (strlen($signature) !== self::SIGNATURE_BYTES) {
                throw new RuntimeException('Signature RSA tidak valid.');
            }

            return $signature;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('Private key RSA tidak valid.', previous: $exception);
        }
    }

    public function verify(string $digest, string $signature, string $publicKeyPem): bool
    {
        $this->assertDigest($digest);

        if (strlen($signature) !== self::SIGNATURE_BYTES) {
            return false;
        }

        try {
            $publicKey = PublicKeyLoader::loadPublicKey($publicKeyPem);

            if (! $publicKey instanceof RsaPublicKey || $publicKey->getLength() !== self::RSA_BITS) {
                throw new RuntimeException('Public key RSA tidak valid.');
            }

            return $this->configure($publicKey)->verify($digest, $signature);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('Public key RSA tidak valid.', previous: $exception);
        }
    }

    private function configure(RsaPrivateKey|RsaPublicKey $key): RsaPrivateKey|RsaPublicKey
    {
        return $key
            ->withPadding(RSA::SIGNATURE_PSS)
            ->withHash('sha256')
            ->withMGFHash('sha256')
            ->withSaltLength(self::PSS_SALT_BYTES);
    }

    private function assertDigest(string $digest): void
    {
        if (strlen($digest) !== self::DIGEST_BYTES) {
            throw new InvalidArgumentException('Signed data harus berupa digest biner SHA-256 sepanjang 32 byte.');
        }
    }
}
