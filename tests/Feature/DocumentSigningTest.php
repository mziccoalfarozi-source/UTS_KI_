<?php

namespace Tests\Feature;

use App\Enums\Algorithm;
use App\Enums\DocumentStatus;
use App\Enums\SignerStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SigningKey;
use App\Models\User;
use App\Services\Crypto\KeyPairService;
use App\Services\Crypto\PrivateKeyVault;
use App\Services\Crypto\SignatureService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentSigningTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PASSPHRASE = 'correct signing passphrase';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_guest_admin_and_unassigned_signer_cannot_sign(): void
    {
        $admin = User::factory()->admin()->create();
        $assignedSigner = User::factory()->signer()->create();
        $unassignedSigner = User::factory()->signer()->create();
        $document = $this->createDocument([$assignedSigner]);

        $this->post(route('documents.sign', $document))->assertRedirect(route('login'));
        $this->actingAs($admin)->post(route('documents.sign', $document), [
            'signing_passphrase' => self::PASSPHRASE,
        ])->assertForbidden();
        $this->actingAs($unassignedSigner)->post(route('documents.sign', $document), [
            'signing_passphrase' => self::PASSPHRASE,
        ])->assertForbidden();

        $this->assertDatabaseMissing('document_signers', ['status' => SignerStatus::Signed->value]);
    }

    public function test_signer_only_sees_assigned_documents_and_safe_detail_props(): void
    {
        $signer = User::factory()->signer()->create();
        $otherSigner = User::factory()->signer()->create();
        $assignedDocument = $this->createDocument([$signer]);
        $otherDocument = $this->createDocument([$otherSigner]);
        $signingKey = $this->createSigningKey($signer);

        $this->actingAs($signer)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Signer/Documents/Index')
                ->has('documents', 1)
                ->where('documents.0.id', $assignedDocument->id)
                ->where('documents.0.can_sign', true));

        $response = $this->actingAs($signer)->get(route('documents.show', $assignedDocument));
        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('Signer/Documents/Show')
            ->where('document.id', $assignedDocument->id)
            ->where('assignment.can_sign', true)
            ->missing('document.file_path')
            ->missing('assignment.key_id')
            ->missing('assignment.signature'));
        $response->assertDontSee($signingKey->encrypted_private_key, false);
        $response->assertDontSee(self::PASSPHRASE, false);

        $download = $this->actingAs($signer)->get(route('documents.download', $assignedDocument));
        $download->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(Storage::disk('local')->get($assignedDocument->file_path), $download->streamedContent());

        $this->actingAs($signer)->get(route('documents.show', $otherDocument))->assertForbidden();
        $this->actingAs($signer)->get(route('documents.download', $otherDocument))->assertForbidden();
    }

    public function test_wrong_passphrase_fails_without_database_changes(): void
    {
        $signer = User::factory()->signer()->create();
        $document = $this->createDocument([$signer]);
        $this->createSigningKey($signer);

        $response = $this->actingAs($signer)->post(route('documents.sign', $document), [
            'signing_passphrase' => 'wrong signing passphrase',
        ]);
        $response->assertSessionHasErrors([
            'signing_passphrase' => 'Passphrase salah atau data kunci rusak',
        ]);
        $this->assertArrayNotHasKey('signing_passphrase', session('_old_input', []));

        $this->assertPendingAndUnchanged($document, $signer);
    }

    public function test_invalid_passphrase_input_is_not_flashed_to_session(): void
    {
        $signer = User::factory()->signer()->create();
        $document = $this->createDocument([$signer]);
        $oversizedPassphrase = str_repeat('secret', 700);

        $this->actingAs($signer)->post(route('documents.sign', $document), [
            'signing_passphrase' => $oversizedPassphrase,
        ])->assertSessionHasErrors('signing_passphrase');

        $this->assertArrayNotHasKey('signing_passphrase', session('_old_input', []));
        $this->assertStringNotContainsString($oversizedPassphrase, json_encode(session()->all()));
    }

    public function test_signer_without_key_is_rejected(): void
    {
        $signer = User::factory()->signer()->create();
        $document = $this->createDocument([$signer]);

        $this->actingAs($signer)->post(route('documents.sign', $document), [
            'signing_passphrase' => self::PASSPHRASE,
        ])->assertSessionHasErrors(['signing' => 'Signing key belum tersedia.']);

        $this->assertPendingAndUnchanged($document, $signer);
    }

    public function test_out_of_order_signer_is_rejected(): void
    {
        $first = User::factory()->signer()->create();
        $second = User::factory()->signer()->create();
        $document = $this->createDocument([$first, $second]);
        $this->createSigningKey($second);

        $this->actingAs($second)->post(route('documents.sign', $document), [
            'signing_passphrase' => self::PASSPHRASE,
        ])->assertSessionHasErrors(['signing' => 'Urutan signing belum tersedia untuk Anda.']);

        $this->assertPendingAndUnchanged($document, $second);
    }

    public function test_file_hash_mismatch_rejects_signing(): void
    {
        $signer = User::factory()->signer()->create();
        $document = $this->createDocument([$signer]);
        $this->createSigningKey($signer);
        Storage::disk('local')->put($document->file_path, 'tampered-pdf-bytes');

        $this->actingAs($signer)->post(route('documents.sign', $document), [
            'signing_passphrase' => self::PASSPHRASE,
        ])->assertSessionHasErrors(['signing' => 'PDF final berubah dan tidak dapat ditandatangani.']);

        $this->assertPendingAndUnchanged($document, $signer);
    }

    public function test_first_signer_creates_base64_signature_and_cannot_sign_twice_without_changing_pdf(): void
    {
        $first = User::factory()->signer()->create();
        $second = User::factory()->signer()->create();
        $document = $this->createDocument([$first, $second]);
        $firstKey = $this->createSigningKey($first);
        $bytesBefore = Storage::disk('local')->get($document->file_path);

        $this->actingAs($first)->post(route('documents.sign', $document), [
            'signing_passphrase' => self::PASSPHRASE,
        ])->assertRedirect();

        $assignment = DocumentSigner::query()
            ->where('document_id', $document->id)
            ->where('user_id', $first->id)
            ->sole();
        $rawSignature = base64_decode($assignment->signature, true);

        $this->assertSame(SignerStatus::Signed, $assignment->status);
        $this->assertSame($firstKey->id, $assignment->key_id);
        $this->assertNotNull($assignment->signed_at);
        $this->assertIsString($rawSignature);
        $this->assertSame(256, strlen($rawSignature));
        $this->assertSame($assignment->signature, base64_encode($rawSignature));
        $this->assertStringEndsWith('==', $assignment->signature);
        $this->assertSame(DocumentStatus::PartiallySigned, $document->fresh()->status);
        $this->assertSame($bytesBefore, Storage::disk('local')->get($document->file_path));
        $this->assertSame($document->document_hash, hash_file('sha256', Storage::disk('local')->path($document->file_path)));

        $this->actingAs($first)->post(route('documents.sign', $document), [
            'signing_passphrase' => self::PASSPHRASE,
        ])->assertSessionHasErrors(['signing' => 'Dokumen ini sudah Anda tandatangani.']);

        $this->assertSame($assignment->signature, $assignment->fresh()->signature);
    }

    public function test_all_signers_complete_with_independent_signatures_over_the_same_digest(): void
    {
        $first = User::factory()->signer()->create();
        $second = User::factory()->signer()->create();
        $document = $this->createDocument([$first, $second]);
        $firstKey = $this->createSigningKey($first);
        $secondKey = $this->createSigningKey($second);
        $bytesBefore = Storage::disk('local')->get($document->file_path);

        foreach ([$first, $second] as $signer) {
            $this->actingAs($signer)->post(route('documents.sign', $document), [
                'signing_passphrase' => self::PASSPHRASE,
            ])->assertRedirect();
        }

        $assignments = DocumentSigner::query()
            ->where('document_id', $document->id)
            ->orderBy('sign_order')
            ->get();
        $digest = hex2bin($document->document_hash);
        $signatureService = new SignatureService;
        $firstSignature = base64_decode($assignments[0]->signature, true);
        $secondSignature = base64_decode($assignments[1]->signature, true);

        $this->assertSame(DocumentStatus::Completed, $document->fresh()->status);
        $this->assertNotSame($firstSignature, $secondSignature);
        $this->assertTrue($signatureService->verify($digest, $firstSignature, $firstKey->public_key));
        $this->assertTrue($signatureService->verify($digest, $secondSignature, $secondKey->public_key));
        $this->assertFalse($signatureService->verify($digest, $firstSignature, $secondKey->public_key));
        $this->assertFalse($signatureService->verify($digest, $secondSignature, $firstKey->public_key));
        $this->assertSame($bytesBefore, Storage::disk('local')->get($document->file_path));
        $this->assertSame($document->document_hash, hash_file('sha256', Storage::disk('local')->path($document->file_path)));

        $persistedData = json_encode([
            DB::table('documents')->get()->toArray(),
            DB::table('document_signers')->get()->toArray(),
            DB::table('signing_keys')->get()->toArray(),
        ]);
        $this->assertIsString($persistedData);
        $this->assertStringNotContainsString(self::PASSPHRASE, $persistedData);
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $persistedData);
    }

    /** @param list<User> $signers */
    private function createDocument(array $signers): Document
    {
        $admin = User::factory()->admin()->create();
        $path = 'documents/'.Str::uuid().'.pdf';
        $bytes = "%PDF-1.4\nfinalized immutable bytes\n%%EOF";
        Storage::disk('local')->put($path, $bytes);

        $document = Document::query()->create([
            'title' => 'Dokumen Signing',
            'institution' => 'Universitas Contoh',
            'document_date' => '2026-09-28',
            'original_filename' => 'final.pdf',
            'file_path' => $path,
            'file_size' => strlen($bytes),
            'document_hash' => hash('sha256', $bytes),
            'verification_token' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
            'status' => DocumentStatus::WaitingSignature,
            'created_by' => $admin->id,
        ]);

        foreach ($signers as $index => $signer) {
            DocumentSigner::query()->create([
                'document_id' => $document->id,
                'user_id' => $signer->id,
                'sign_order' => $index + 1,
                'position_title' => 'Signer '.($index + 1),
                'status' => SignerStatus::Pending,
            ]);
        }

        return $document;
    }

    private function createSigningKey(User $user): SigningKey
    {
        $keyPair = (new KeyPairService)->generate();

        try {
            $protected = (new PrivateKeyVault)->encrypt($keyPair['private_key'], self::PASSPHRASE);

            return SigningKey::query()->create([
                'user_id' => $user->id,
                'algorithm' => Algorithm::Rsa2048PssSha256,
                'public_key' => $keyPair['public_key'],
                ...$protected,
            ]);
        } finally {
            sodium_memzero($keyPair['private_key']);
        }
    }

    private function assertPendingAndUnchanged(Document $document, User $signer): void
    {
        $assignment = DocumentSigner::query()
            ->where('document_id', $document->id)
            ->where('user_id', $signer->id)
            ->sole();

        $this->assertSame(SignerStatus::Pending, $assignment->status);
        $this->assertNull($assignment->signature);
        $this->assertNull($assignment->key_id);
        $this->assertNull($assignment->signed_at);
        $this->assertSame(DocumentStatus::WaitingSignature, $document->fresh()->status);
    }
}
