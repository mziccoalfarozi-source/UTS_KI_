<?php

namespace App\Services\Document;

use App\Enums\Algorithm;
use App\Enums\DocumentStatus;
use App\Enums\SignerStatus;
use App\Exceptions\PrivateKeyDecryptionException;
use App\Exceptions\SigningWorkflowException;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SigningKey;
use App\Models\User;
use App\Services\Crypto\PrivateKeyVault;
use App\Services\Crypto\SignatureService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SigningWorkflowService
{
    public function __construct(
        private readonly PrivateKeyVault $privateKeyVault,
        private readonly SignatureService $signatureService,
    ) {}

    public function sign(Document $document, User $user, string $passphrase): DocumentSigner
    {
        try {
            return DB::transaction(function () use ($document, $user, &$passphrase): DocumentSigner {
                $lockedDocument = Document::query()
                    ->whereKey($document->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $assignments = DocumentSigner::query()
                    ->where('document_id', $lockedDocument->getKey())
                    ->orderBy('sign_order')
                    ->lockForUpdate()
                    ->get();
                $assignment = $assignments->firstWhere('user_id', $user->getKey());

                if (! $assignment instanceof DocumentSigner) {
                    throw new SigningWorkflowException('Anda tidak ditugaskan untuk menandatangani dokumen ini.');
                }

                if ($assignment->status === SignerStatus::Signed) {
                    throw new SigningWorkflowException('Dokumen ini sudah Anda tandatangani.');
                }

                $previousSignerIsPending = $assignments->contains(
                    fn (DocumentSigner $candidate): bool => $candidate->sign_order < $assignment->sign_order
                        && $candidate->status !== SignerStatus::Signed,
                );

                if ($previousSignerIsPending) {
                    throw new SigningWorkflowException('Urutan signing belum tersedia untuk Anda.');
                }

                $signingKey = SigningKey::query()
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($signingKey === null) {
                    throw new SigningWorkflowException('Signing key belum tersedia.');
                }

                if ($signingKey->algorithm !== Algorithm::Rsa2048PssSha256) {
                    throw new SigningWorkflowException('Algoritma signing key tidak sesuai contract.');
                }

                $digest = $this->verifiedDigest($lockedDocument);
                $privateKey = null;

                try {
                    $privateKey = $this->privateKeyVault->decrypt(
                        $signingKey->encrypted_private_key,
                        $signingKey->salt,
                        $signingKey->nonce,
                        $signingKey->auth_tag,
                        $signingKey->kdf,
                        $signingKey->kdf_opslimit,
                        $signingKey->kdf_memlimit,
                        $passphrase,
                    );
                    $signature = $this->signatureService->sign($digest, $privateKey);

                    if (! $this->signatureService->verify($digest, $signature, $signingKey->public_key)) {
                        throw new SigningWorkflowException('Signature gagal melewati self-verification.');
                    }

                    $encodedSignature = base64_encode($signature);

                    if (strlen($encodedSignature) !== 344 || base64_decode($encodedSignature, true) !== $signature) {
                        throw new SigningWorkflowException('Signature gagal disimpan dalam format contract.');
                    }

                    $assignment->status = SignerStatus::Signed;
                    $assignment->key_id = $signingKey->getKey();
                    $assignment->signature = $encodedSignature;
                    $assignment->signed_at = now();
                    $assignment->save();

                    $lockedDocument->status = $assignments->every(
                        fn (DocumentSigner $candidate): bool => $candidate->status === SignerStatus::Signed,
                    )
                        ? DocumentStatus::Completed
                        : DocumentStatus::PartiallySigned;
                    $lockedDocument->save();

                    return $assignment->refresh();
                } catch (PrivateKeyDecryptionException|SigningWorkflowException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    throw new SigningWorkflowException('Signing gagal diproses.', $exception);
                } finally {
                    if (is_string($privateKey)) {
                        sodium_memzero($privateKey);
                    }
                }
            });
        } finally {
            sodium_memzero($passphrase);
        }
    }

    private function verifiedDigest(Document $document): string
    {
        if (! preg_match('/^[0-9a-f]{64}$/', $document->document_hash)) {
            throw new SigningWorkflowException('Document hash tersimpan tidak valid.');
        }

        $path = Storage::disk('local')->path($document->file_path);

        if (! is_file($path) || ! is_readable($path)) {
            throw new SigningWorkflowException('PDF final tidak tersedia.');
        }

        $actualHash = hash_file('sha256', $path);

        if (! is_string($actualHash) || ! hash_equals($document->document_hash, $actualHash)) {
            throw new SigningWorkflowException('PDF final berubah dan tidak dapat ditandatangani.');
        }

        $digest = hex2bin($document->document_hash);

        if (! is_string($digest) || strlen($digest) !== 32) {
            throw new SigningWorkflowException('Document hash tidak dapat dikonversi menjadi digest biner.');
        }

        return $digest;
    }
}
