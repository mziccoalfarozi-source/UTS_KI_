<?php

namespace App\Services\Document;

use App\Exceptions\DocumentFinalizationException;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\File;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Throwable;

class DocumentFinalizer
{
    public function generateToken(): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        if (strlen($token) !== 43) {
            throw new RuntimeException('Verification token tidak dapat dibuat.');
        }

        return $token;
    }

    public function verificationUrl(string $token): string
    {
        $baseUrl = config('verification.base_url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new RuntimeException('VERIFY_BASE_URL belum dikonfigurasi.');
        }

        return rtrim($baseUrl, '/').'/verify/'.$token;
    }

    /**
     * @param  array{title: string, institution: string, document_date: string}  $document
     * @param  list<array{name: string, position_title: string, sign_order: int}>  $signers
     * @return array{document_hash: string, file_size: int, page_count: int, verification_url: string}
     */
    public function finalize(
        string $sourcePath,
        string $outputPath,
        array $document,
        array $signers,
        string $verificationToken,
    ): array {
        $qrPath = null;

        try {
            if (! is_file($sourcePath) || ! is_readable($sourcePath) || is_file($outputPath)) {
                throw new DocumentFinalizationException;
            }

            File::ensureDirectoryExists(dirname($outputPath));

            $verificationUrl = $this->verificationUrl($verificationToken);
            $qrPath = $this->writeQrImage($verificationUrl, dirname($outputPath));
            $pdf = new Fpdi;
            $pdf->SetCompression(false);
            $inputPages = $pdf->setSourceFile($sourcePath);

            for ($pageNumber = 1; $pageNumber <= $inputPages; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], $size);
                $pdf->useTemplate($templateId);
            }

            $this->addApprovalPage($pdf, $document, $signers, $verificationUrl, $qrPath);
            $pdf->Output('F', $outputPath);
            clearstatcache(true, $outputPath);

            $fileSize = filesize($outputPath);
            $documentHash = hash_file('sha256', $outputPath);

            if ($fileSize === false || $documentHash === false || ! preg_match('/^[0-9a-f]{64}$/', $documentHash)) {
                throw new DocumentFinalizationException;
            }

            return [
                'document_hash' => $documentHash,
                'file_size' => $fileSize,
                'page_count' => $inputPages + 1,
                'verification_url' => $verificationUrl,
            ];
        } catch (DocumentFinalizationException $exception) {
            File::delete($outputPath);

            throw $exception;
        } catch (Throwable) {
            File::delete($outputPath);

            throw new DocumentFinalizationException;
        } finally {
            if (is_string($qrPath)) {
                File::delete($qrPath);
            }
        }
    }

    private function writeQrImage(string $verificationUrl, string $directory): string
    {
        $qrPath = tempnam($directory, 'qr-');

        if ($qrPath === false) {
            throw new DocumentFinalizationException;
        }

        try {
            $qrCode = new QrCode(
                data: $verificationUrl,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 300,
                margin: 10,
            );
            $result = (new PngWriter)->write($qrCode);

            if (file_put_contents($qrPath, $result->getString()) === false) {
                throw new DocumentFinalizationException;
            }
        } catch (Throwable $exception) {
            File::delete($qrPath);

            if ($exception instanceof DocumentFinalizationException) {
                throw $exception;
            }

            throw new DocumentFinalizationException;
        }

        return $qrPath;
    }

    /**
     * @param  array{title: string, institution: string, document_date: string}  $document
     * @param  list<array{name: string, position_title: string, sign_order: int}>  $signers
     */
    private function addApprovalPage(
        Fpdi $pdf,
        array $document,
        array $signers,
        string $verificationUrl,
        string $qrPath,
    ): void {
        $pdf->AddPage('P', 'A4');
        $pdf->SetMargins(20, 18, 20);
        $pdf->SetFont('Helvetica', 'B', 15);
        $pdf->Cell(0, 9, $this->pdfText('HALAMAN PENGESAHAN'), 0, 1, 'C');
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(38, 7, 'Judul Dokumen', 0, 0);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->MultiCell(0, 7, ': '.$this->pdfText($document['title']));
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(38, 7, 'Institusi', 0, 0);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->MultiCell(0, 7, ': '.$this->pdfText($document['institution']));
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(38, 7, 'Tanggal Dokumen', 0, 0);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 7, ': '.$this->pdfText($document['document_date']), 0, 1);
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Daftar Penandatangan', 0, 1);
        $pdf->SetFont('Helvetica', '', 10);

        foreach ($signers as $signer) {
            $pdf->MultiCell(
                0,
                6,
                sprintf(
                    "%d. %s\n    %s",
                    $signer['sign_order'],
                    $this->pdfText($signer['name']),
                    $this->pdfText($signer['position_title']),
                ),
            );
        }

        $pdf->Ln(5);
        $qrY = $pdf->GetY();
        $pdf->Image($qrPath, 75, $qrY, 60, 60, 'PNG');
        $pdf->SetY($qrY + 64);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 7, 'Verifikasi:', 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->MultiCell(0, 5, $this->pdfText($verificationUrl), 0, 'C');
    }

    private function pdfText(string $value): string
    {
        $converted = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value);

        return $converted === false ? '' : $converted;
    }
}
