<?php

namespace Tests\Feature;

use FPDF;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class PdfTechnicalSpikeTest extends TestCase
{
    public function test_it_copies_all_pages_preserves_their_sizes_and_appends_one_dummy_page(): void
    {
        $directory = storage_path('framework/testing/pdf-technical-spike');
        $inputPath = $directory.DIRECTORY_SEPARATOR.'input.pdf';
        $outputPath = $directory.DIRECTORY_SEPARATOR.'output.pdf';
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        try {
            $this->createInputPdf($inputPath);
            $inputSizes = $this->pageSizes($inputPath);

            $exitCode = Artisan::call('spike:pdf', [
                'input' => $inputPath,
                '--output' => $outputPath,
            ]);

            $outputSizes = $this->pageSizes($outputPath);
            $firstHash = hash_file('sha256', $outputPath);
            $secondHash = hash_file('sha256', $outputPath);
            $commandOutput = Artisan::output();

            $this->assertSame(0, $exitCode);
            $this->assertFileExists($outputPath);
            $this->assertGreaterThan(0, filesize($outputPath));
            $this->assertCount(2, $inputSizes);
            $this->assertCount(3, $outputSizes);
            $this->assertSame($inputSizes[0]['orientation'], $outputSizes[0]['orientation']);
            $this->assertSame($inputSizes[1]['orientation'], $outputSizes[1]['orientation']);
            $this->assertEqualsWithDelta($inputSizes[0]['width'], $outputSizes[0]['width'], 0.01);
            $this->assertEqualsWithDelta($inputSizes[0]['height'], $outputSizes[0]['height'], 0.01);
            $this->assertEqualsWithDelta($inputSizes[1]['width'], $outputSizes[1]['width'], 0.01);
            $this->assertEqualsWithDelta($inputSizes[1]['height'], $outputSizes[1]['height'], 0.01);
            $this->assertIsString($firstHash);
            $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $firstHash);
            $this->assertSame($firstHash, $secondHash);
            $this->assertStringContainsString('Input pages', $commandOutput);
            $this->assertStringContainsString('Re-hash identical', $commandOutput);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_it_rejects_a_missing_input_file(): void
    {
        $outputPath = storage_path('framework/testing/pdf-technical-spike/missing-output.pdf');
        File::delete($outputPath);

        $exitCode = Artisan::call('spike:pdf', [
            'input' => storage_path('framework/testing/pdf-technical-spike/missing.pdf'),
            '--output' => $outputPath,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertFileDoesNotExist($outputPath);
        $this->assertStringContainsString('does not exist or is not readable', Artisan::output());
    }

    public function test_it_rejects_a_file_that_fpdi_cannot_parse(): void
    {
        $directory = storage_path('framework/testing/pdf-technical-spike');
        $inputPath = $directory.DIRECTORY_SEPARATOR.'invalid.pdf';
        $outputPath = $directory.DIRECTORY_SEPARATOR.'invalid-output.pdf';
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);
        File::put($inputPath, 'not a PDF');

        try {
            $exitCode = Artisan::call('spike:pdf', [
                'input' => $inputPath,
                '--output' => $outputPath,
            ]);

            $this->assertSame(1, $exitCode);
            $this->assertFileDoesNotExist($outputPath);
            $this->assertStringContainsString('FPDI could not process', Artisan::output());
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function createInputPdf(string $path): void
    {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetCompression(false);
        $pdf->AddPage('P', 'A4');
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Portrait A4 test page', 0, 1);
        $pdf->AddPage('L', 'A5');
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Landscape A5 test page', 0, 1);
        $pdf->Output('F', $path);
    }

    /**
     * @return array<int, array{width: float, height: float, orientation: string}>
     */
    private function pageSizes(string $path): array
    {
        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($path);
        $sizes = [];

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);

            $sizes[] = [
                'width' => $size['width'],
                'height' => $size['height'],
                'orientation' => $size['orientation'],
            ];
        }

        return $sizes;
    }
}
