<?php

namespace App\Services\Verification;

use App\Enums\SignerStatus;
use App\Enums\VerificationCode;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Services\Crypto\SignatureService;
use Illuminate\Support\Facades\Storage;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA\PublicKey as RsaPublicKey;
use RuntimeException;
use Throwable;

final class VerificationService
{
    private const RSA_BITS = 2048;

    private const SIGNATURE_BYTES = 256;

    public function __construct(
        private readonly SignatureService $signatureService,
    ) {}

    /**
     * Verifikasi dokumen sesuai CONTRACT §13 dan §14.
     *
     * @return array{code: VerificationCode, signed_count: int, total_signers: int}
     */
    public function verify(
        Document $document,
        ?string $uploadedPdfBytes,
        ?int $advancedSignerId,
        ?string $customPublicKeyPem,
    ): array {
        $signers = $document->signers()
            ->with('signingKey:id,public_key')
            ->orderBy('sign_order')
            ->get();

        $totalSigners = $signers->count();
        $signedSigners = $signers->filter(
            fn (DocumentSigner $s): bool => $s->status === SignerStatus::Signed,
        );
        $signedCount = $signedSigners->count();

        // 1. Hitung hash dari bytes PDF (server atau upload).
        $pdfBytes = $uploadedPdfBytes ?? $this->readServerFile($document);
        $actualHash = hash('sha256', $pdfBytes);

        // 2. Bandingkan hash — selalu cek pertama.
        if (! hash_equals($document->document_hash, $actualHash)) {
            return $this->result(VerificationCode::InvalidDocumentModified, $signedCount, $totalSigners);
        }

        $digest = hex2bin($actualHash);

        if (! is_string($digest) || strlen($digest) !== 32) {
            return $this->result(VerificationCode::InvalidSignature, $signedCount, $totalSigners);
        }

        $hasAdvanced = $advancedSignerId !== null && $customPublicKeyPem !== null;

        // 3. Validasi dan parse custom public key untuk Advanced Verification.
        //    Hasilnya dicatat tapi tidak langsung di-return — INVALID_SIGNATURE punya prioritas lebih tinggi.
        $customKeyFailed = false;

        if ($hasAdvanced) {
            $targetSigner = $signedSigners->firstWhere('id', $advancedSignerId);

            if (! $targetSigner instanceof DocumentSigner) {
                // Target tidak ditemukan atau bukan SIGNED → INVALID_PUBLIC_KEY
                $customKeyFailed = true;
            } else {
                $customKeyFailed = ! $this->isValidRsaPublicKey($customPublicKeyPem);
            }
        }

        // 4. Verifikasi kriptografi semua signer berstatus SIGNED.
        //    - Signer non-target: gunakan official public key → kegagalan = INVALID_SIGNATURE.
        //    - Signer target (advanced): gunakan custom public key → kegagalan = INVALID_PUBLIC_KEY.
        $officialKeyFailed = false;

        foreach ($signedSigners as $signer) {
            $rawSignature = base64_decode($signer->signature ?? '', true);

            if ($rawSignature === false || strlen($rawSignature) !== self::SIGNATURE_BYTES) {
                $officialKeyFailed = true;

                continue;
            }

            $isTarget = $hasAdvanced && $signer->id === $advancedSignerId;

            if ($isTarget) {
                // Custom key sudah gagal parse → tidak perlu coba verify, custom failure sudah tercatat.
                if ($customKeyFailed) {
                    continue;
                }

                try {
                    $isValid = $this->signatureService->verify($digest, $rawSignature, $customPublicKeyPem);
                } catch (Throwable) {
                    $isValid = false;
                }

                if (! $isValid) {
                    $customKeyFailed = true;
                }
            } else {
                $officialPublicKey = $signer->signingKey?->public_key;

                if (! is_string($officialPublicKey) || $officialPublicKey === '') {
                    $officialKeyFailed = true;

                    continue;
                }

                try {
                    $isValid = $this->signatureService->verify($digest, $rawSignature, $officialPublicKey);
                } catch (Throwable) {
                    $isValid = false;
                }

                if (! $isValid) {
                    $officialKeyFailed = true;
                }
            }
        }

        // 5. Evaluasi hasil sesuai prioritas truth table CONTRACT §14.
        if ($officialKeyFailed) {
            return $this->result(VerificationCode::InvalidSignature, $signedCount, $totalSigners);
        }

        if ($customKeyFailed) {
            return $this->result(VerificationCode::InvalidPublicKey, $signedCount, $totalSigners);
        }

        // 6. Cek apakah masih ada signer PENDING.
        $hasPending = $signers->contains(
            fn (DocumentSigner $s): bool => $s->status === SignerStatus::Pending,
        );

        if ($hasPending) {
            return $this->result(VerificationCode::Incomplete, $signedCount, $totalSigners);
        }

        return $this->result(VerificationCode::Valid, $signedCount, $totalSigners);
    }

    private function readServerFile(Document $document): string
    {
        $path = Storage::disk('local')->path($document->file_path);
        $bytes = @file_get_contents($path);

        if ($bytes === false) {
            throw new RuntimeException('PDF server tidak dapat dibaca.');
        }

        return $bytes;
    }

    private function isValidRsaPublicKey(string $pem): bool
    {
        try {
            $key = PublicKeyLoader::loadPublicKey($pem);

            return $key instanceof RsaPublicKey && $key->getLength() === self::RSA_BITS;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{code: VerificationCode, signed_count: int, total_signers: int}
     */
    private function result(VerificationCode $code, int $signedCount, int $totalSigners): array
    {
        return [
            'code' => $code,
            'signed_count' => $signedCount,
            'total_signers' => $totalSigners,
        ];
    }
}
