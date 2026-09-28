<?php

namespace Tests\Unit;

use App\Exceptions\DocumentFinalizationException;
use App\Services\Document\DocumentFinalizer;
use FPDF;
use Illuminate\Support\Facades\File;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class DocumentFinalizerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        config(['verification.base_url' => 'https://verify.example.test/base']);
        $this->directory = storage_path('framework/testing/document-finalizer-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_token_is_unique_base64url_without_padding_and_exactly_43_characters(): void
    {
        $finalizer = new DocumentFinalizer;
        $first = $finalizer->generateToken();
        $second = $finalizer->generateToken();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $first);
        $this->assertSame(43, strlen($first));
        $this->assertStringNotContainsString('=', $first);
        $this->assertNotSame($first, $second);
    }

    public function test_finalizer_preserves_input_pages_appends_static_approval_and_hashes_finished_qr_pdf(): void
    {
        $inputPath = $this->directory.'/input.pdf';
        $outputPath = $this->directory.'/final.pdf';
        $this->writeTwoPagePdf($inputPath);
        $token = (new DocumentFinalizer)->generateToken();

        $result = (new DocumentFinalizer)->finalize(
            $inputPath,
            $outputPath,
            [
                'title' => 'Surat Keputusan UTS',
                'institution' => 'Universitas Contoh',
                'document_date' => '2026-09-28',
            ],
            [
                ['name' => 'Signer Pertama', 'position_title' => 'Ketua', 'sign_order' => 1],
                ['name' => 'Signer Kedua', 'position_title' => 'Sekretaris', 'sign_order' => 2],
            ],
            $token,
        );

        $this->assertFileExists($outputPath);
        $this->assertSame(hash_file('sha256', $outputPath), $result['document_hash']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result['document_hash']);
        $this->assertSame(filesize($outputPath), $result['file_size']);
        $this->assertSame(3, $result['page_count']);
        $this->assertSame('https://verify.example.test/base/verify/'.$token, $result['verification_url']);

        $input = new Fpdi;
        $output = new Fpdi;
        $this->assertSame(2, $input->setSourceFile($inputPath));
        $this->assertSame(3, $output->setSourceFile($outputPath));

        for ($page = 1; $page <= 2; $page++) {
            $inputSize = $input->getTemplateSize($input->importPage($page));
            $outputSize = $output->getTemplateSize($output->importPage($page));

            $this->assertSame($inputSize['orientation'], $outputSize['orientation']);
            $this->assertEqualsWithDelta($inputSize['width'], $outputSize['width'], 0.01);
            $this->assertEqualsWithDelta($inputSize['height'], $outputSize['height'], 0.01);
        }

        $finalBytes = file_get_contents($outputPath);
        $this->assertIsString($finalBytes);
        $this->assertStringContainsString('HALAMAN PENGESAHAN', $finalBytes);
        $this->assertStringContainsString('Surat Keputusan UTS', $finalBytes);
        $this->assertStringContainsString('1. Signer Pertama', $finalBytes);
        $this->assertStringContainsString('Ketua', $finalBytes);
        $this->assertStringContainsString('https://verify.example.test/base/verify/'.$token, $finalBytes);
        $this->assertStringContainsString('/Subtype /Image', $finalBytes);
        $this->assertStringNotContainsString('PENDING', $finalBytes);
        $this->assertStringNotContainsString('SIGNED', $finalBytes);
        $this->assertStringNotContainsString('VALID', $finalBytes);
        $this->assertStringNotContainsString('document_number', $finalBytes);
    }

    public function test_unprocessable_pdf_is_rejected_without_partial_output(): void
    {
        $inputPath = $this->directory.'/malformed.pdf';
        $outputPath = $this->directory.'/final.pdf';
        File::put($inputPath, '%PDF-1.4 malformed');

        try {
            (new DocumentFinalizer)->finalize(
                $inputPath,
                $outputPath,
                ['title' => 'Test', 'institution' => 'Test', 'document_date' => '2026-09-28'],
                [['name' => 'Signer', 'position_title' => 'Ketua', 'sign_order' => 1]],
                (new DocumentFinalizer)->generateToken(),
            );

            $this->fail('Malformed PDF should not be finalized.');
        } catch (DocumentFinalizationException) {
            $this->assertFileDoesNotExist($outputPath);
        }
    }

    private function writeTwoPagePdf(string $path): void
    {
        $pdf = new FPDF;
        $pdf->AddPage('P', 'A4');
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Portrait A4');
        $pdf->AddPage('L', 'A5');
        $pdf->Cell(0, 10, 'Landscape A5');
        $pdf->Output('F', $path);
    }
}
