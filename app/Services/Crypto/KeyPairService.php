<?php

namespace App\Services\Crypto;

use RuntimeException;

class KeyPairService
{
    public function __construct(private readonly ?string $opensslConfig = null) {}

    /**
     * @return array{private_key: string, public_key: string}
     */
    public function generate(): array
    {
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        if ($configPath = $this->resolveOpenSslConfig()) {
            $options['config'] = $configPath;
        }

        $key = openssl_pkey_new($options);

        if ($key === false) {
            throw new RuntimeException('Gagal membuat signing key.');
        }

        $privateKey = '';

        if (! openssl_pkey_export($key, $privateKey, null, $options)) {
            if ($privateKey !== '') {
                sodium_memzero($privateKey);
            }

            throw new RuntimeException('Gagal mengekspor signing key.');
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] !== 2048) {
            sodium_memzero($privateKey);

            throw new RuntimeException('Signing key yang dihasilkan tidak valid.');
        }

        return [
            'private_key' => $privateKey,
            'public_key' => $details['key'],
        ];
    }

    private function resolveOpenSslConfig(): ?string
    {
        $candidates = [
            $this->opensslConfig,
            getenv('OPENSSL_CONF') ?: null,
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
