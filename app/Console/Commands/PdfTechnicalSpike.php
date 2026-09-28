<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Throwable;

#[Signature('spike:pdf {input : Path to a local input PDF} {--output= : Optional output PDF path}')]
#[Description('Run the isolated FPDI compatibility and immutability spike')]
class PdfTechnicalSpike extends Command
{
    public function handle(): int
    {
        $inputPath = $this->resolveInputPath();

        if ($inputPath === null) {
            return self::FAILURE;
        }

        $outputPath = $this->resolveOutputPath($inputPath);

        if ($outputPath === null) {
            return self::FAILURE;
        }

        try {
            $result = $this->processPdf($inputPath, $outputPath);
        } catch (Throwable $exception) {
            File::delete($outputPath);
            $this->components->error('FPDI could not process the input PDF: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Input path', $inputPath],
                ['Output path', $outputPath],
                ['Input pages', (string) $result['input_pages']],
                ['Output pages', (string) $result['output_pages']],
                ['Output bytes', (string) $result['output_bytes']],
                ['SHA-256', $result['sha256']],
                ['Re-hash identical', $result['rehash_identical'] ? 'yes' : 'no'],
            ],
        );

        return self::SUCCESS;
    }

    private function resolveInputPath(): ?string
    {
        $input = $this->argument('input');

        if (! is_string($input) || $input === '') {
            $this->components->error('The input PDF path is required.');

            return null;
        }

        $inputPath = realpath($input);

        if ($inputPath === false || ! is_file($inputPath) || ! is_readable($inputPath)) {
            $this->components->error('The input PDF does not exist or is not readable.');

            return null;
        }

        return $inputPath;
    }

    private function resolveOutputPath(string $inputPath): ?string
    {
        $output = $this->option('output');
        $outputPath = is_string($output) && $output !== ''
            ? $output
            : storage_path('app/private/spikes/pdf/'.pathinfo($inputPath, PATHINFO_FILENAME).'-spike.pdf');

        File::ensureDirectoryExists(dirname($outputPath));

        $outputDirectory = realpath(dirname($outputPath));

        if ($outputDirectory === false || ! is_writable($outputDirectory)) {
            $this->components->error('The output directory cannot be created or is not writable.');

            return null;
        }

        $resolvedOutputPath = $outputDirectory.DIRECTORY_SEPARATOR.basename($outputPath);

        if (strcasecmp($inputPath, $resolvedOutputPath) === 0) {
            $this->components->error('The output path must be different from the input path.');

            return null;
        }

        return $resolvedOutputPath;
    }

    /**
     * @return array{input_pages: int, output_pages: int, output_bytes: int, sha256: string, rehash_identical: bool}
     */
    private function processPdf(string $inputPath, string $outputPath): array
    {
        $pdf = new Fpdi;
        $inputPages = $pdf->setSourceFile($inputPath);

        for ($pageNumber = 1; $pageNumber <= $inputPages; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], $size);
            $pdf->useTemplate($templateId);
        }

        $this->addDummyApprovalPage($pdf);
        $pdf->Output('F', $outputPath);
        clearstatcache(true, $outputPath);

        $outputBytes = filesize($outputPath);
        $firstHash = hash_file('sha256', $outputPath);

        if ($outputBytes === false || $firstHash === false) {
            throw new RuntimeException('The completed output PDF could not be read.');
        }

        $secondHash = hash_file('sha256', $outputPath);

        if ($secondHash === false || ! hash_equals($firstHash, $secondHash)) {
            throw new RuntimeException('The output PDF changed after the first hash.');
        }

        $outputPdf = new Fpdi;
        $outputPages = $outputPdf->setSourceFile($outputPath);

        if ($outputPages !== $inputPages + 1) {
            throw new RuntimeException('The output PDF does not contain the expected number of pages.');
        }

        return [
            'input_pages' => $inputPages,
            'output_pages' => $outputPages,
            'output_bytes' => $outputBytes,
            'sha256' => $firstHash,
            'rehash_identical' => true,
        ];
    }

    private function addDummyApprovalPage(Fpdi $pdf): void
    {
        $pdf->AddPage('P', 'A4');
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'SECURE DOCUMENT SIGNATURE', 0, 1, 'C');
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'PDF TECHNICAL SPIKE', 0, 1, 'C');
        $pdf->Ln(8);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Cell(0, 7, 'Document Title: Technical Spike', 0, 1);
        $pdf->Cell(0, 7, 'Institution: Test', 0, 1);
        $pdf->Cell(0, 7, 'Date: '.now()->toDateString(), 0, 1);
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Signers:', 0, 1);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Cell(0, 7, '1. Signer One - Test Position', 0, 1);
        $pdf->Cell(0, 7, '2. Signer Two - Test Position', 0, 1);
        $pdf->Ln(8);
        $pdf->Rect(75, $pdf->GetY(), 60, 35);
        $pdf->SetXY(75, $pdf->GetY() + 13);
        $pdf->Cell(60, 8, '[QR PLACEHOLDER]', 0, 1, 'C');
        $pdf->SetY($pdf->GetY() + 18);
        $pdf->Cell(0, 7, 'Verification:', 0, 1);
        $pdf->Cell(0, 7, 'https://example.invalid/verify/test-token', 0, 1);
    }
}
