<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\SignerStatus;
use App\Models\Document;
use App\Models\User;
use FPDF;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $uploadDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['verification.base_url' => 'https://verify.example.test']);
        $this->uploadDirectory = storage_path('framework/testing/document-uploads-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->uploadDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->uploadDirectory);

        parent::tearDown();
    }

    public function test_guest_is_rejected_and_signer_cannot_create_documents(): void
    {
        $this->get(route('documents.index'))->assertRedirect(route('login'));
        $this->post(route('documents.store'))->assertRedirect(route('login'));

        $signer = User::factory()->signer()->create();
        $this->actingAs($signer)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Signer/Documents/Index')
                ->has('documents', 0));
        $this->actingAs($signer)->post(route('documents.store'))->assertForbidden();
    }

    public function test_admin_can_open_document_list_and_create_form(): void
    {
        $admin = User::factory()->admin()->create();
        $signer = User::factory()->signer()->create();

        $this->actingAs($admin)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Documents/Index')
                ->has('documents', 0)
                ->has('availableSigners', 1)
                ->where('availableSigners.0.id', $signer->id));
    }

    public function test_non_pdf_and_oversized_pdf_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $signer = User::factory()->signer()->create();

        $this->actingAs($admin)
            ->post(route('documents.store'), $this->payload(
                $signer,
                UploadedFile::fake()->create('not-a-pdf.txt', 10, 'text/plain'),
            ))
            ->assertSessionHasErrors('pdf');

        $this->actingAs($admin)
            ->post(route('documents.store'), $this->payload(
                $signer,
                UploadedFile::fake()->create('oversized.pdf', 10241, 'application/pdf'),
            ))
            ->assertSessionHasErrors('pdf');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_malformed_pdf_is_rejected_without_database_or_file_artifacts(): void
    {
        $admin = User::factory()->admin()->create();
        $signer = User::factory()->signer()->create();

        $this->actingAs($admin)
            ->post(route('documents.store'), $this->payload(
                $signer,
                UploadedFile::fake()->create('malformed.pdf', 1, 'application/pdf'),
            ))
            ->assertSessionHasErrors('pdf');

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
    }

    public function test_at_least_one_signer_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = $this->basePayload($this->validPdf());
        $payload['signers'] = [];

        $this->actingAs($admin)->post(route('documents.store'), $payload)->assertSessionHasErrors('signers');
    }

    public function test_admin_cannot_be_assigned_as_signer(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('documents.store'), $this->payload($admin, $this->validPdf()))
            ->assertSessionHasErrors('signers.0.user_id');
    }

    public function test_duplicate_signer_and_duplicate_sign_order_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $signer = User::factory()->signer()->create();
        $payload = $this->basePayload($this->validPdf());
        $payload['signers'] = [
            ['user_id' => $signer->id, 'sign_order' => 1, 'position_title' => 'Ketua'],
            ['user_id' => $signer->id, 'sign_order' => 1, 'position_title' => 'Ketua'],
        ];

        $this->actingAs($admin)
            ->post(route('documents.store'), $payload)
            ->assertSessionHasErrors(['signers.1.user_id', 'signers.1.sign_order']);
    }

    public function test_gap_in_sign_order_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $first = User::factory()->signer()->create();
        $second = User::factory()->signer()->create();
        $payload = $this->basePayload($this->validPdf());
        $payload['signers'] = [
            ['user_id' => $first->id, 'sign_order' => 1, 'position_title' => 'Ketua'],
            ['user_id' => $second->id, 'sign_order' => 3, 'position_title' => 'Sekretaris'],
        ];

        $this->actingAs($admin)->post(route('documents.store'), $payload)->assertSessionHasErrors('signers');
    }

    public function test_admin_finalizes_document_with_immutable_hash_and_initial_signer_state(): void
    {
        $admin = User::factory()->admin()->create();
        $first = User::factory()->signer()->create(['name' => 'Signer Pertama']);
        $second = User::factory()->signer()->create(['name' => 'Signer Kedua']);
        $upload = $this->validPdf('original-source.pdf', true);
        $sourcePath = $upload->getRealPath();
        $payload = $this->basePayload($upload);
        $payload['created_by'] = User::factory()->admin()->create()->id;
        $payload['file_path'] = 'attacker/path.pdf';
        $payload['signers'] = [
            ['user_id' => $first->id, 'sign_order' => 1, 'position_title' => 'Ketua'],
            ['user_id' => $second->id, 'sign_order' => 2, 'position_title' => 'Sekretaris'],
        ];

        $response = $this->actingAs($admin)->post(route('documents.store'), $payload);
        $document = Document::query()->with('signers')->sole();

        $response->assertRedirect(route('documents.show', $document));
        $this->assertSame($admin->id, $document->created_by);
        $this->assertSame(DocumentStatus::WaitingSignature, $document->status);
        $this->assertSame('original-source.pdf', $document->original_filename);
        $this->assertNotSame($sourcePath, Storage::disk('local')->path($document->file_path));
        $this->assertMatchesRegularExpression('/^documents\/[0-9a-f-]{36}\.pdf$/', $document->file_path);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $document->verification_token);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $document->document_hash);
        Storage::disk('local')->assertExists($document->file_path);

        $finalPath = Storage::disk('local')->path($document->file_path);
        $this->assertSame(hash_file('sha256', $finalPath), $document->document_hash);
        $this->assertSame(filesize($finalPath), $document->file_size);

        $pdf = new Fpdi;
        $this->assertSame(3, $pdf->setSourceFile($finalPath));
        $this->assertCount(2, $document->signers);

        foreach ($document->signers as $signer) {
            $this->assertSame(SignerStatus::Pending, $signer->status);
            $this->assertNull($signer->key_id);
            $this->assertNull($signer->signature);
            $this->assertNull($signer->signed_at);
        }

        $finalBytes = file_get_contents($finalPath);
        $this->assertStringContainsString(
            'https://verify.example.test/verify/'.$document->verification_token,
            $finalBytes,
        );
        $this->assertStringContainsString('/Subtype /Image', $finalBytes);
        $this->assertStringNotContainsString('PENDING', $finalBytes);

        $this->actingAs($admin)
            ->get(route('documents.show', $document))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Documents/Show')
                ->where('document.id', $document->id)
                ->missing('document.file_path'));

        $download = $this->actingAs($admin)->get(route('documents.download', $document));
        $download->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame($finalBytes, $download->streamedContent());
    }

    public function test_verification_tokens_are_unique_across_documents(): void
    {
        $admin = User::factory()->admin()->create();
        $signer = User::factory()->signer()->create();

        $this->actingAs($admin)->post(route('documents.store'), $this->payload($signer, $this->validPdf('one.pdf')));
        $this->actingAs($admin)->post(route('documents.store'), $this->payload($signer, $this->validPdf('two.pdf')));

        $tokens = Document::query()->pluck('verification_token');
        $this->assertCount(2, $tokens);
        $this->assertCount(2, $tokens->unique());
    }

    public function test_persistence_failure_rolls_back_database_and_removes_finalized_file(): void
    {
        $admin = User::factory()->admin()->create();
        $signer = User::factory()->signer()->create();
        Exceptions::fake();

        Document::created(function (): void {
            throw new RuntimeException('simulated persistence failure');
        });

        $this->actingAs($admin)
            ->post(route('documents.store'), $this->payload($signer, $this->validPdf()))
            ->assertServerError();

        Exceptions::assertReported(RuntimeException::class);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_signers', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
    }

    public function test_unassigned_signer_cannot_open_document_detail_or_download(): void
    {
        $admin = User::factory()->admin()->create();
        $assignedSigner = User::factory()->signer()->create();
        $unassignedSigner = User::factory()->signer()->create();
        $this->actingAs($admin)->post(route('documents.store'), $this->payload($assignedSigner, $this->validPdf()));
        $document = Document::query()->sole();

        $this->actingAs($unassignedSigner)->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($unassignedSigner)->get(route('documents.download', $document))->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function payload(User $signer, UploadedFile $pdf): array
    {
        return [
            ...$this->basePayload($pdf),
            'signers' => [
                ['user_id' => $signer->id, 'sign_order' => 1, 'position_title' => 'Ketua'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function basePayload(UploadedFile $pdf): array
    {
        return [
            'title' => 'Dokumen UTS',
            'institution' => 'Universitas Contoh',
            'document_date' => '2026-09-28',
            'pdf' => $pdf,
        ];
    }

    private function validPdf(string $name = 'document.pdf', bool $twoPages = false): UploadedFile
    {
        $path = $this->uploadDirectory.'/'.bin2hex(random_bytes(4)).'.pdf';
        $pdf = new FPDF;
        $pdf->AddPage('P', 'A4');
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Source PDF');

        if ($twoPages) {
            $pdf->AddPage('L', 'A5');
            $pdf->Cell(0, 10, 'Second Page');
        }

        $pdf->Output('F', $path);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }
}
