<?php

namespace Tests\Feature;

use App\Enums\Algorithm;
use App\Enums\DocumentStatus;
use App\Enums\SignerStatus;
use App\Enums\VerificationCode;
use App\Enums\VerificationMethod;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SigningKey;
use App\Models\User;
use App\Models\VerificationLog;
use App\Services\Crypto\KeyPairService;
use App\Services\Crypto\PrivateKeyVault;
use App\Services\Crypto\SignatureService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> Temp file paths to delete after each test */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    /**
     * Test 1: Token palsu menghasilkan REJECTED_TOKEN_NOT_FOUND dan log tersimpan.
     */
    public function test_fake_token_returns_rejected_and_logs_it(): void
    {
        $fakeToken = 'FAKETOKEN1234567'; // 16 chars, no matching document

        $this->get(route('verify.show', $fakeToken))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::RejectedTokenNotFound->value)
            );

        $this->assertDatabaseHas('verification_logs', [
            'method' => VerificationMethod::Token->value,
            'result_code' => VerificationCode::RejectedTokenNotFound->value,
            'document_id' => null,
            'token_prefix' => substr($fakeToken, 0, 8),
        ]);
    }

    /**
     * Test 2: Token valid dengan semua signer SIGNED → VALID, log tersimpan, status COMPLETED.
     */
    public function test_get_verify_with_valid_token_and_all_signed_returns_valid(): void
    {
        $signer1 = User::factory()->signer()->create();
        $signer2 = User::factory()->signer()->create();
        $document = $this->createDocument([$signer1, $signer2]);

        $assignments = DocumentSigner::query()
            ->where('document_id', $document->id)
            ->orderBy('sign_order')
            ->get();

        $this->signAssignment($document, $assignments[0]);
        $this->signAssignment($document, $assignments[1]);

        // Set status COMPLETED (normalnya di-set oleh DocumentSigningController)
        $document->status = DocumentStatus::Completed;
        $document->save();

        $this->get(route('verify.show', $document->verification_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::Valid->value)
            );

        $this->assertDatabaseHas('verification_logs', [
            'method' => VerificationMethod::Token->value,
            'result_code' => VerificationCode::Valid->value,
            'document_id' => $document->id,
        ]);

        $this->assertSame(DocumentStatus::Completed, $document->fresh()->status);
    }

    /**
     * Test 3: Token valid dengan 1 signer PENDING → INCOMPLETE, signed_count dan total_signers tepat.
     */
    public function test_get_verify_with_one_pending_signer_returns_incomplete(): void
    {
        $signer1 = User::factory()->signer()->create();
        $signer2 = User::factory()->signer()->create();
        $document = $this->createDocument([$signer1, $signer2]);

        $assignment1 = DocumentSigner::query()
            ->where('document_id', $document->id)
            ->where('user_id', $signer1->id)
            ->sole();

        $this->signAssignment($document, $assignment1);
        // signer2 tetap PENDING

        $this->get(route('verify.show', $document->verification_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::Incomplete->value)
                ->where('result.signed_count', 1)
                ->where('result.total_signers', 2)
            );
    }

    /**
     * Test 4: POST dengan PDF yang isinya berbeda (tampered) → INVALID_DOCUMENT_MODIFIED.
     */
    public function test_post_verify_with_tampered_pdf_returns_invalid_document_modified(): void
    {
        $signer = User::factory()->signer()->create();
        $document = $this->createDocument([$signer]);
        $assignment = DocumentSigner::query()->where('document_id', $document->id)->sole();
        $this->signAssignment($document, $assignment);

        // Magic bytes %PDF- agar lolos validasi mimes:pdf, tapi isi berbeda → hash berbeda
        $tamperedBytes = "%PDF-1.4\nISI YANG SUDAH DIMANIPULASI - BUKAN YANG ASLI\n%%EOF";
        $pdfFile = $this->makePdfUpload($tamperedBytes);

        $this->post(route('verify.verify', $document->verification_token), [
            'pdf' => $pdfFile,
        ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::InvalidDocumentModified->value)
            );
    }

    /**
     * Test 5: POST dengan PDF asli → VALID, log method=UPLOAD tersimpan.
     */
    public function test_post_verify_with_original_pdf_upload_returns_valid(): void
    {
        $signer = User::factory()->signer()->create();
        $document = $this->createDocument([$signer]);
        $assignment = DocumentSigner::query()->where('document_id', $document->id)->sole();
        $this->signAssignment($document, $assignment);

        $originalBytes = Storage::disk('local')->get($document->file_path);
        $pdfFile = $this->makePdfUpload($originalBytes);

        $this->post(route('verify.verify', $document->verification_token), [
            'pdf' => $pdfFile,
        ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::Valid->value)
            );

        $this->assertDatabaseHas('verification_logs', [
            'method' => VerificationMethod::Upload->value,
            'result_code' => VerificationCode::Valid->value,
            'document_id' => $document->id,
        ]);
    }

    /**
     * Test 6: Signature di DB dikorupsi (byte dibalik, tetap valid base64 344 char) → INVALID_SIGNATURE.
     */
    public function test_tampered_signature_in_db_returns_invalid_signature(): void
    {
        $signer = User::factory()->signer()->create();
        $document = $this->createDocument([$signer]);
        $assignment = DocumentSigner::query()->where('document_id', $document->id)->sole();
        $this->signAssignment($document, $assignment);

        $assignment->refresh();
        $rawSignature = base64_decode($assignment->signature, true);
        // Flip beberapa byte di tengah signature — hasilnya tetap 256 byte dan base64-valid
        $rawSignature[10] = chr(ord($rawSignature[10]) ^ 0xFF);
        $rawSignature[11] = chr(ord($rawSignature[11]) ^ 0xFF);
        $rawSignature[20] = chr(ord($rawSignature[20]) ^ 0xFF);
        $assignment->signature = base64_encode($rawSignature);
        $assignment->save();

        $this->get(route('verify.show', $document->verification_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::InvalidSignature->value)
            );
    }

    /**
     * Test 7: Advanced verification dengan custom key BERBEDA dari yang dipakai sign → INVALID_PUBLIC_KEY,
     * log method=UPLOAD_CUSTOM_KEY tersimpan.
     */
    public function test_advanced_verification_with_wrong_custom_key_returns_invalid_public_key(): void
    {
        $signer = User::factory()->signer()->create();
        $document = $this->createDocument([$signer]);
        $assignment = DocumentSigner::query()->where('document_id', $document->id)->sole();
        $this->signAssignment($document, $assignment);

        // Key pair berbeda dari yang dipakai untuk sign
        $wrongKeyPair = (new KeyPairService)->generate();

        $originalBytes = Storage::disk('local')->get($document->file_path);
        $pdfFile = $this->makePdfUpload($originalBytes);

        $this->post(route('verify.verify', $document->verification_token), [
            'pdf' => $pdfFile,
            'document_signer_id' => $assignment->id,
            'custom_public_key' => $wrongKeyPair['public_key'],
        ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::InvalidPublicKey->value)
            );

        $this->assertDatabaseHas('verification_logs', [
            'method' => VerificationMethod::UploadCustomKey->value,
            'result_code' => VerificationCode::InvalidPublicKey->value,
        ]);
    }

    /**
     * Test 8: Advanced verification dengan custom key SAMA dengan yang dipakai sign → VALID.
     */
    public function test_advanced_verification_with_correct_custom_key_returns_valid(): void
    {
        $signer = User::factory()->signer()->create();
        $document = $this->createDocument([$signer]);
        $assignment = DocumentSigner::query()->where('document_id', $document->id)->sole();
        $signingKey = $this->signAssignment($document, $assignment);

        $originalBytes = Storage::disk('local')->get($document->file_path);
        $pdfFile = $this->makePdfUpload($originalBytes);

        $this->post(route('verify.verify', $document->verification_token), [
            'pdf' => $pdfFile,
            'document_signer_id' => $assignment->id,
            'custom_public_key' => $signingKey->public_key,
        ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::Valid->value)
            );
    }

    /**
     * Test 9: Advanced verification dengan custom_public_key = "not-a-pem" (PEM tidak valid) → INVALID_PUBLIC_KEY.
     */
    public function test_advanced_verification_with_invalid_pem_returns_invalid_public_key(): void
    {
        $signer = User::factory()->signer()->create();
        $document = $this->createDocument([$signer]);
        $assignment = DocumentSigner::query()->where('document_id', $document->id)->sole();
        $this->signAssignment($document, $assignment);

        $originalBytes = Storage::disk('local')->get($document->file_path);
        $pdfFile = $this->makePdfUpload($originalBytes);

        $this->post(route('verify.verify', $document->verification_token), [
            'pdf' => $pdfFile,
            'document_signer_id' => $assignment->id,
            'custom_public_key' => 'not-a-pem',
        ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::InvalidPublicKey->value)
            );
    }

    /**
     * Test 10: Advanced verification dengan target signer yang masih PENDING → INVALID_PUBLIC_KEY,
     * karena target tidak ditemukan di daftar signed signers.
     */
    public function test_advanced_verification_with_pending_signer_as_target_returns_invalid_public_key(): void
    {
        $signer1 = User::factory()->signer()->create();
        $signer2 = User::factory()->signer()->create();
        $document = $this->createDocument([$signer1, $signer2]);

        $assignment1 = DocumentSigner::query()
            ->where('document_id', $document->id)
            ->where('user_id', $signer1->id)
            ->sole();
        $assignment2 = DocumentSigner::query()
            ->where('document_id', $document->id)
            ->where('user_id', $signer2->id)
            ->sole();

        $this->signAssignment($document, $assignment1);
        // signer2 tetap PENDING — tidak ada signature

        // Key pair valid, tapi target (signer2) bukan SIGNED
        $validKeyPair = (new KeyPairService)->generate();
        $originalBytes = Storage::disk('local')->get($document->file_path);
        $pdfFile = $this->makePdfUpload($originalBytes);

        $this->post(route('verify.verify', $document->verification_token), [
            'pdf' => $pdfFile,
            'document_signer_id' => $assignment2->id,
            'custom_public_key' => $validKeyPair['public_key'],
        ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::InvalidPublicKey->value)
            );
    }

    /**
     * Test 11: Signer PENDING tidak diverifikasi kriptografis → INCOMPLETE (bukan INVALID_SIGNATURE).
     */
    public function test_pending_signers_are_not_cryptographically_verified(): void
    {
        $signer1 = User::factory()->signer()->create();
        $signer2 = User::factory()->signer()->create();
        $document = $this->createDocument([$signer1, $signer2]);

        $assignment1 = DocumentSigner::query()
            ->where('document_id', $document->id)
            ->where('user_id', $signer1->id)
            ->sole();

        $this->signAssignment($document, $assignment1);
        // signer2 PENDING — null signature, tidak disentuh sama sekali

        $this->get(route('verify.show', $document->verification_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::Incomplete->value)
            );

        $this->assertDatabaseHas('verification_logs', [
            'method' => VerificationMethod::Token->value,
            'result_code' => VerificationCode::Incomplete->value,
        ]);
    }

    /**
     * Test 12: Admin dapat melihat halaman logs beserta datanya.
     */
    public function test_admin_can_view_logs(): void
    {
        $admin = User::factory()->admin()->create();

        VerificationLog::query()->create([
            'document_id' => null,
            'method' => VerificationMethod::Token,
            'result_code' => VerificationCode::RejectedTokenNotFound,
            'token_prefix' => 'ABCD1234',
        ]);
        VerificationLog::query()->create([
            'document_id' => null,
            'method' => VerificationMethod::Upload,
            'result_code' => VerificationCode::Valid,
            'token_prefix' => 'EFGH5678',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.logs'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Logs/Index')
                ->has('logs.data', 2)
            );
    }

    /**
     * Test 13: Guest di-redirect ke login, signer biasa mendapat 403.
     */
    public function test_guest_and_signer_cannot_view_admin_logs(): void
    {
        $signer = User::factory()->signer()->create();

        $this->get(route('admin.logs'))
            ->assertRedirect(route('login'));

        $this->actingAs($signer)
            ->get(route('admin.logs'))
            ->assertForbidden();
    }

    /**
     * Test 14: Halaman index verify dapat dirender.
     */
    public function test_verify_index_page_renders(): void
    {
        $this->get(route('verify.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Index')
            );
    }

    /**
     * Test 15: token_prefix disimpan dengan benar — token pendek disimpan utuh, token normal dipotong 8 char.
     */
    public function test_token_prefix_stored_correctly(): void
    {
        // Token pendek (< 8 char): prefix = seluruh token
        $shortToken = 'SHORT'; // 5 chars
        $this->get(route('verify.show', $shortToken));

        $this->assertDatabaseHas('verification_logs', [
            'token_prefix' => 'SHORT',
        ]);

        // Token normal (43 chars): prefix = 8 char pertama
        $normalToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='); // 43 chars
        $this->assertSame(43, strlen($normalToken));

        $this->get(route('verify.show', $normalToken));

        $this->assertDatabaseHas('verification_logs', [
            'token_prefix' => substr($normalToken, 0, 8),
        ]);
    }

    /**
     * Test 16: INVALID_SIGNATURE punya prioritas lebih tinggi dari INVALID_PUBLIC_KEY dalam mode advanced.
     * signer1 (non-target) punya signature rusak → officialKeyFailed = true.
     * signer2 (target) pakai wrong custom key → customKeyFailed = true.
     * Hasilnya: INVALID_SIGNATURE (bukan INVALID_PUBLIC_KEY).
     */
    public function test_invalid_signature_takes_priority_over_invalid_public_key_in_advanced_mode(): void
    {
        $signer1 = User::factory()->signer()->create();
        $signer2 = User::factory()->signer()->create();
        $document = $this->createDocument([$signer1, $signer2]);

        $assignments = DocumentSigner::query()
            ->where('document_id', $document->id)
            ->orderBy('sign_order')
            ->get();

        $assignment1 = $assignments[0]; // non-target → akan dikorupsi
        $assignment2 = $assignments[1]; // advanced target → pakai wrong key

        $this->signAssignment($document, $assignment1);
        $this->signAssignment($document, $assignment2);

        // Korupsi signature signer1 (non-target) di DB
        $assignment1->refresh();
        $rawSig1 = base64_decode($assignment1->signature, true);
        $rawSig1[10] = chr(ord($rawSig1[10]) ^ 0xFF);
        $rawSig1[11] = chr(ord($rawSig1[11]) ^ 0xFF);
        $assignment1->signature = base64_encode($rawSig1);
        $assignment1->save();

        // Wrong custom key untuk signer2 (target advanced)
        $wrongKeyPair = (new KeyPairService)->generate();
        $originalBytes = Storage::disk('local')->get($document->file_path);
        $pdfFile = $this->makePdfUpload($originalBytes);

        $this->post(route('verify.verify', $document->verification_token), [
            'pdf' => $pdfFile,
            'document_signer_id' => $assignment2->id,
            'custom_public_key' => $wrongKeyPair['public_key'],
        ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify/Show')
                ->where('result.code', VerificationCode::InvalidSignature->value)
            );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Buat UploadedFile dari bytes menggunakan file temp nyata di storage/.
     * Menggantikan UploadedFile::fake()->createWithContent() yang membutuhkan sys_get_temp_dir() yang valid.
     */
    private function makePdfUpload(string $bytes, string $name = 'test.pdf'): UploadedFile
    {
        $tempPath = tempnam(storage_path(), 'phptest_');
        file_put_contents($tempPath, $bytes);
        $this->tempFiles[] = $tempPath;

        return new UploadedFile($tempPath, $name, 'application/pdf', null, true);
    }

    /**
     * Buat document beserta assignment signer-nya.
     *
     * @param  list<User>  $signers
     */
    private function createDocument(array $signers): Document
    {
        $admin = User::factory()->admin()->create();
        $path = 'documents/'.Str::uuid().'.pdf';
        $bytes = "%PDF-1.4\nfinalized immutable bytes\n%%EOF";
        Storage::disk('local')->put($path, $bytes);

        $document = Document::query()->create([
            'title' => 'Dokumen Verifikasi',
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

    /**
     * Sign satu DocumentSigner secara kriptografis (bypass HTTP).
     * Membuat SigningKey baru untuk user tersebut, lalu menyimpan signature yang valid ke DB.
     *
     * @return SigningKey SigningKey yang dibuat — public_key-nya bisa dipakai untuk advanced verification.
     */
    private function signAssignment(Document $document, DocumentSigner $assignment): SigningKey
    {
        $keyPair = (new KeyPairService)->generate();
        $protected = (new PrivateKeyVault)->encrypt($keyPair['private_key'], 'test-passphrase');

        $signingKey = SigningKey::query()->create([
            'user_id' => $assignment->user_id,
            'algorithm' => Algorithm::Rsa2048PssSha256,
            'public_key' => $keyPair['public_key'],
            ...$protected,
        ]);

        $digest = hex2bin($document->document_hash);
        $rawSignature = (new SignatureService)->sign($digest, $keyPair['private_key']);

        sodium_memzero($keyPair['private_key']);

        $assignment->status = SignerStatus::Signed;
        $assignment->key_id = $signingKey->id;
        $assignment->signature = base64_encode($rawSignature);
        $assignment->signed_at = now();
        $assignment->save();

        return $signingKey;
    }
}
